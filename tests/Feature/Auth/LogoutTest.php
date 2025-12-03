<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-LOGOUT-001: 成功登出
     *
     * @test
     */
    public function authenticated_user_can_logout()
    {
        // Arrange: 建立並登入用戶
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 先登入取得 Token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        // Act: 執行登出
        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        // Assert: 驗證登出成功
        $response->assertStatus(200)
            ->assertJson([
                'message' => '登出成功',
            ]);
    }

    /**
     * TC-LOGOUT-002: 未認證無法登出
     *
     * @test
     */
    public function unauthenticated_user_cannot_logout()
    {
        // Arrange: 不提供 Token

        // Act: 嘗試登出
        $response = $this->postJson('/api/v1/auth/logout');

        // Assert: 應該返回 401 未認證
        $response->assertStatus(401);
    }

    /**
     * TC-LOGOUT-003: 登出後 Token 失效
     *
     * @test
     */
    public function token_is_revoked_after_logout()
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

        // 驗證 Token 可用
        $this->getJson('/api/v1/user/profile', [
            'Authorization' => "Bearer $token",
        ])->assertStatus(200);

        // Act: 登出
        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        // Assert: 驗證資料庫中 token 已被刪除
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * TC-LOGOUT-004: 無效的 Token 無法登出
     *
     * @test
     */
    public function cannot_logout_with_invalid_token()
    {
        // Arrange: 使用無效的 Token
        $invalidToken = 'invalid-token-string';

        // Act
        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => "Bearer $invalidToken",
        ]);

        // Assert
        $response->assertStatus(401);
    }
}
