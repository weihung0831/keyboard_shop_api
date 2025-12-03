<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-REG-001: 成功註冊
     *
     * @test
     */
    public function user_can_register_with_valid_data()
    {
        // Arrange: 準備測試資料
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0912345678',
        ];

        // Act: 執行註冊
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 驗證結果
        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                ],
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'expires_in' => 604800, // 7 天
                'user' => [
                    'name' => '測試用戶',
                    'email' => 'test@example.com',
                    'phone' => '0912345678',
                ],
            ]);

        // 驗證資料庫有新增會員記錄
        $this->assertDatabaseHas('users', [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'phone' => '0912345678',
        ]);

        // 驗證密碼已加密儲存
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    /**
     * TC-REG-002: Email 已存在
     *
     * @test
     */
    public function cannot_register_with_existing_email()
    {
        // Arrange: 先建立一個用戶
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $userData = [
            'name' => '新用戶',
            'email' => 'existing@example.com', // 使用已存在的 Email
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act: 嘗試使用相同 Email 註冊
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 應該返回驗證錯誤
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-REG-003: 密碼長度不足
     *
     * @test
     */
    public function cannot_register_with_short_password()
    {
        // Arrange
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'short', // 少於 8 字元
            'password_confirmation' => 'short',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * TC-REG-004: 密碼確認不一致
     *
     * @test
     */
    public function cannot_register_with_mismatched_password_confirmation()
    {
        // Arrange
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123', // 不一致
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * TC-REG-005: Email 格式錯誤
     *
     * @test
     */
    public function cannot_register_with_invalid_email_format()
    {
        // Arrange
        $userData = [
            'name' => '測試用戶',
            'email' => 'invalid-email', // 不是有效的 Email 格式
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-REG-006: 必填欄位缺失
     *
     * @test
     */
    public function cannot_register_with_missing_required_fields()
    {
        // Arrange: 缺少所有必填欄位
        $userData = [];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 應該返回所有必填欄位的錯誤
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /**
     * TC-REG-007: 電話號碼為選填
     *
     * @test
     */
    public function can_register_without_phone_number()
    {
        // Arrange: 不提供電話號碼
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // phone 不提供
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 應該成功註冊
        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => null,
        ]);
    }

    /**
     * TC-REG-008: 電話號碼格式驗證
     *
     * @test
     */
    public function validates_phone_number_format_when_provided()
    {
        // Arrange: 提供不正確的電話號碼格式
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123', // 不正確的格式
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 應該返回驗證錯誤
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * TC-REG-009: 註冊後 Email 未驗證
     *
     * @test
     */
    public function email_is_not_verified_after_registration()
    {
        // Arrange
        $userData = [
            'name' => '測試用戶',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert: 註冊成功但 email_verified_at 應該是 null
        $response->assertStatus(201);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNull($user->email_verified_at);
    }
}
