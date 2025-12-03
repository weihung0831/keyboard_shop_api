<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-LOGIN-001: 成功登入
     *
     * @test
     */
    public function user_can_login_with_valid_credentials()
    {
        // Arrange: 建立一個測試用戶
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act: 執行登入
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 驗證結果
        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'expires_in' => 604800, // 7 天
                'user' => [
                    'id' => $user->id,
                    'email' => 'test@example.com',
                ],
            ]);

        // 驗證 Token 存在且不為空
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * TC-LOGIN-002: 密碼錯誤
     *
     * @test
     */
    public function cannot_login_with_wrong_password()
    {
        // Arrange: 建立一個測試用戶
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrong_password', // 錯誤的密碼
        ];

        // Act: 嘗試登入
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 應該返回 401 未授權
        $response->assertStatus(401)
            ->assertJson([
                'message' => '帳號或密碼錯誤',
            ]);
    }

    /**
     * TC-LOGIN-003: 帳號不存在
     *
     * @test
     */
    public function cannot_login_with_nonexistent_account()
    {
        // Arrange: 不建立任何用戶
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        // Act: 嘗試登入
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 應該返回 401 (不洩漏帳號是否存在)
        $response->assertStatus(401)
            ->assertJson([
                'message' => '帳號或密碼錯誤',
            ]);
    }

    /**
     * TC-LOGIN-004: 登入失敗次數限制
     *
     * @test
     */
    public function login_is_throttled_after_too_many_attempts()
    {
        // Arrange: 建立測試用戶
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ];

        // Act: 連續嘗試登入 5 次 (失敗)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $credentials);
        }

        // 第 6 次嘗試
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 應該返回 429 Too Many Requests
        $response->assertStatus(429);
    }

    /**
     * TC-LOGIN-005: 必填欄位驗證
     *
     * @test
     */
    public function cannot_login_with_missing_credentials()
    {
        // Arrange: 空的登入資料
        $credentials = [];

        // Act
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 應該返回驗證錯誤
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * TC-LOGIN-006: Email 格式驗證
     *
     * @test
     */
    public function validates_email_format_on_login()
    {
        // Arrange
        $credentials = [
            'email' => 'invalid-email', // 不是有效的 Email 格式
            'password' => 'password123',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-LOGIN-007: Token 可用於後續認證
     *
     * @test
     */
    public function returned_token_can_be_used_for_authentication()
    {
        // Arrange: 建立並登入用戶
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        // Act: 使用 Token 訪問需要認證的端點
        $response = $this->getJson('/api/v1/user/profile', [
            'Authorization' => "Bearer $token",
        ]);

        // Assert: 應該能成功訪問
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'email' => 'test@example.com',
                ],
            ]);
    }

    /**
     * TC-LOGIN-008: 未驗證 Email 的用戶可以登入
     * (Email 驗證不阻擋登入,但某些功能可能需要驗證)
     *
     * @test
     */
    public function unverified_user_can_login()
    {
        // Arrange: 建立未驗證 Email 的用戶
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null, // 未驗證
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/login', $credentials);

        // Assert: 應該可以登入
        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }
}
