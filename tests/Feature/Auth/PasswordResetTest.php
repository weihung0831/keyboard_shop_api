<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 密碼重設功能測試
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-PASSWORD-RESET-001: 可以發送密碼重設連結
     *
     * @test
     */
    public function can_request_password_reset_link()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // 發送密碼重設請求
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        // 驗證回應
        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        // 驗證資料庫中有建立 token
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    /**
     * TC-PASSWORD-RESET-002: 不存在的 Email 也回傳成功（安全性考量）
     *
     * @test
     */
    public function returns_success_for_nonexistent_email()
    {
        // 發送密碼重設請求（Email 不存在）
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // 驗證回應（為了安全性，不透露使用者是否存在）
        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        // 驗證資料庫中沒有建立 token
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    /**
     * TC-PASSWORD-RESET-003: Email 格式驗證
     *
     * @test
     */
    public function validates_email_format_for_password_reset()
    {
        // 發送密碼重設請求（Email 格式錯誤）
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'invalid-email',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-PASSWORD-RESET-004: Email 必填驗證
     *
     * @test
     */
    public function email_is_required_for_password_reset()
    {
        // 發送密碼重設請求（缺少 Email）
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-PASSWORD-RESET-005: 可以使用 Token 重設密碼
     *
     * @test
     */
    public function can_reset_password_with_valid_token()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // 建立密碼重設 token
        $token = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 執行密碼重設
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 驗證回應
        $response->assertStatus(200);
        $response->assertJson(['message' => '密碼重設成功']);

        // 驗證密碼已更新
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // 驗證 token 已刪除
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    /**
     * TC-PASSWORD-RESET-006: 無效的 Token 無法重設密碼
     *
     * @test
     */
    public function cannot_reset_password_with_invalid_token()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // 建立密碼重設 token
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        // 執行密碼重設（使用錯誤的 token）
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => 'wrong-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);

        // 驗證密碼未更新
        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $user->password));
    }

    /**
     * TC-PASSWORD-RESET-007: 過期的 Token 無法重設密碼
     *
     * @test
     */
    public function cannot_reset_password_with_expired_token()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // 建立過期的密碼重設 token（2小時前）
        $token = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2),
        ]);

        // 執行密碼重設
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);

        // 驗證密碼未更新
        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $user->password));
    }

    /**
     * TC-PASSWORD-RESET-008: 密碼確認驗證
     *
     * @test
     */
    public function validates_password_confirmation()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // 建立密碼重設 token
        $token = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 執行密碼重設（密碼確認不一致）
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * TC-PASSWORD-RESET-009: 密碼長度驗證
     *
     * @test
     */
    public function validates_password_minimum_length()
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // 建立密碼重設 token
        $token = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 執行密碼重設（密碼太短）
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * TC-PASSWORD-RESET-010: 重設密碼後所有 Token 失效
     *
     * @test
     */
    public function all_tokens_revoked_after_password_reset()
    {
        // 建立測試使用者並登入（建立 API token）
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // 建立兩個 API tokens
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        // 確認 tokens 存在
        $this->assertEquals(2, $user->tokens()->count());

        // 建立密碼重設 token
        $resetToken = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($resetToken),
            'created_at' => now(),
        ]);

        // 執行密碼重設
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $resetToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 驗證回應
        $response->assertStatus(200);

        // 驗證所有 API tokens 已被刪除
        $user->refresh();
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * TC-PASSWORD-RESET-011: 不存在的 Email 無法重設密碼
     *
     * @test
     */
    public function cannot_reset_password_for_nonexistent_email()
    {
        // 建立密碼重設 token（但使用者不存在）
        $token = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'nonexistent@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 執行密碼重設
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-PASSWORD-RESET-012: 必填欄位驗證
     *
     * @test
     */
    public function validates_required_fields_for_reset_password()
    {
        // 執行密碼重設（缺少所有欄位）
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        // 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'token', 'password']);
    }
}
