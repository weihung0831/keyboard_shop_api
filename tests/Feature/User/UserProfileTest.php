<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 建立已登入的用戶並返回 Token
     */
    private function createAuthenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->create(array_merge([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ], $attributes));

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        return [
            'user' => $user,
            'token' => $loginResponse->json('token'),
        ];
    }

    /**
     * TC-PROFILE-001: 取得會員資料
     *
     * @test
     */
    public function authenticated_user_can_get_profile()
    {
        // Arrange
        ['user' => $user, 'token' => $token] = $this->createAuthenticatedUser([
            'name' => '測試用戶',
            'phone' => '0912345678',
            'address' => '台北市信義區',
        ]);

        // Act
        $response = $this->getJson('/api/v1/user/profile', [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'created_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => '測試用戶',
                    'email' => 'test@example.com',
                    'phone' => '0912345678',
                    'address' => '台北市信義區',
                ],
            ]);

        // 確認不包含敏感資訊
        $response->assertJsonMissing(['password']);
    }

    /**
     * TC-PROFILE-002: 更新會員資料
     *
     * @test
     */
    public function authenticated_user_can_update_profile()
    {
        // Arrange
        ['user' => $user, 'token' => $token] = $this->createAuthenticatedUser();

        $updateData = [
            'name' => '更新後的姓名',
            'phone' => '0987654321',
            'address' => '新北市板橋區',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/profile', $updateData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => '更新後的姓名',
                    'phone' => '0987654321',
                    'address' => '新北市板橋區',
                ],
            ]);

        // 驗證資料庫已更新
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新後的姓名',
            'phone' => '0987654321',
            'address' => '新北市板橋區',
        ]);
    }

    /**
     * TC-PROFILE-003: 未認證無法取得資料
     *
     * @test
     */
    public function unauthenticated_user_cannot_get_profile()
    {
        // Act: 不提供 Token
        $response = $this->getJson('/api/v1/user/profile');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-PROFILE-004: 未認證無法更新資料
     *
     * @test
     */
    public function unauthenticated_user_cannot_update_profile()
    {
        // Act
        $response = $this->putJson('/api/v1/user/profile', [
            'name' => '新名字',
        ]);

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-PROFILE-005: 修改密碼成功
     *
     * @test
     */
    public function authenticated_user_can_change_password()
    {
        // Arrange
        ['user' => $user, 'token' => $token] = $this->createAuthenticatedUser();

        $passwordData = [
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/change-password', $passwordData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => '密碼修改成功',
            ]);

        // 驗證可以用新密碼登入
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'newpassword456',
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * TC-PROFILE-006: 舊密碼錯誤無法修改
     *
     * @test
     */
    public function cannot_change_password_with_wrong_current_password()
    {
        // Arrange
        ['token' => $token] = $this->createAuthenticatedUser();

        $passwordData = [
            'current_password' => 'wrong_password', // 錯誤的舊密碼
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/change-password', $passwordData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * TC-PROFILE-007: 新密碼長度不足
     *
     * @test
     */
    public function cannot_change_password_with_short_new_password()
    {
        // Arrange
        ['token' => $token] = $this->createAuthenticatedUser();

        $passwordData = [
            'current_password' => 'password123',
            'new_password' => 'short', // 少於 8 字元
            'new_password_confirmation' => 'short',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/change-password', $passwordData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * TC-PROFILE-008: 新密碼確認不一致
     *
     * @test
     */
    public function cannot_change_password_with_mismatched_confirmation()
    {
        // Arrange
        ['token' => $token] = $this->createAuthenticatedUser();

        $passwordData = [
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'different456', // 不一致
        ];

        // Act
        $response = $this->putJson('/api/v1/user/change-password', $passwordData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * TC-PROFILE-009: 新密碼不能與舊密碼相同
     *
     * @test
     */
    public function new_password_must_be_different_from_current()
    {
        // Arrange
        ['token' => $token] = $this->createAuthenticatedUser();

        $passwordData = [
            'current_password' => 'password123',
            'new_password' => 'password123', // 與舊密碼相同
            'new_password_confirmation' => 'password123',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/change-password', $passwordData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * TC-PROFILE-010: 更新資料時驗證欄位
     *
     * @test
     */
    public function validates_fields_when_updating_profile()
    {
        // Arrange
        ['token' => $token] = $this->createAuthenticatedUser();

        // 提供過長的名字
        $updateData = [
            'name' => str_repeat('a', 256), // 超過 255 字元
        ];

        // Act
        $response = $this->putJson('/api/v1/user/profile', $updateData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * TC-PROFILE-011: 可以只更新部分欄位
     *
     * @test
     */
    public function can_update_partial_profile_fields()
    {
        // Arrange
        ['user' => $user, 'token' => $token] = $this->createAuthenticatedUser([
            'name' => '原始名字',
            'phone' => '0912345678',
        ]);

        // 只更新名字
        $updateData = [
            'name' => '新名字',
        ];

        // Act
        $response = $this->putJson('/api/v1/user/profile', $updateData, [
            'Authorization' => "Bearer $token",
        ]);

        // Assert
        $response->assertStatus(200);

        // 驗證名字已更新,但電話沒變
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '新名字',
            'phone' => '0912345678', // 保持不變
        ]);
    }
}
