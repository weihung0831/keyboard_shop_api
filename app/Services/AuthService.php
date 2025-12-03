<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 認證服務層
 * 處理會員註冊、登入、登出等商業邏輯
 */
class AuthService
{
    /**
     * 使用者資料存取層
     */
    private UserRepository $user_repository;

    /**
     * 建構函式
     */
    public function __construct(UserRepository $user_repository)
    {
        $this->user_repository = $user_repository;
    }

    /**
     * 會員註冊
     *
     * @param array $data 註冊資料
     * @return array{user: User, token: string}
     * @throws \Exception
     */
    public function register(array $data): array
    {
        try {
            // 建立新使用者
            $user = $this->user_repository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            // 建立 API Token（有效期 7 天）
            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addDays(7)
            )->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            // 記錄錯誤
            \Log::error('註冊失敗: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 會員登入
     *
     * @param array $credentials 登入憑證
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        // 驗證登入憑證（明確使用 web guard 以支援 attempt 方法）
        if (!Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['帳號或密碼錯誤'],
            ]);
        }

        // 取得已驗證的使用者
        $user = Auth::guard('web')->user();

        // 建立 API Token（有效期 7 天）
        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addDays(7)
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * 會員登出
     *
     * @param User $user 當前使用者
     * @return void
     */
    public function logout(User $user): void
    {
        // 刪除該用戶的所有 API tokens
        $this->user_repository->deleteAllTokens($user);
    }

    /**
     * 登出所有裝置
     *
     * @param User $user 當前使用者
     * @return void
     */
    public function logoutAllDevices(User $user): void
    {
        // 刪除所有 tokens
        $this->user_repository->deleteAllTokens($user);
    }

    /**
     * 發送密碼重設連結
     *
     * @param string $email 使用者 Email
     * @return string 狀態訊息
     * @throws \Exception
     */
    public function sendPasswordResetLink(string $email): string
    {
        try {
            // 檢查 Email 是否存在
            $user = $this->user_repository->findByEmail($email);

            if (!$user) {
                // 為了安全性，不透露使用者是否存在
                return '如果該 Email 存在，我們已發送密碼重設連結到您的信箱';
            }

            // 刪除該 Email 的舊 token
            $this->user_repository->deletePasswordResetRecord($email);

            // 建立新的重設 token
            $token = Str::random(60);

            // 儲存到資料庫
            $this->user_repository->createPasswordResetRecord($email, Hash::make($token));

            // TODO: 發送 Email
            \Log::info('密碼重設 Token 已產生', [
                'email' => $email,
                'token' => $token,
            ]);

            return '如果該 Email 存在，我們已發送密碼重設連結到您的信箱';
        } catch (\Exception $e) {
            \Log::error('發送密碼重設連結失敗: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 重設密碼
     *
     * @param array $credentials 包含 email, token, password
     * @return bool
     * @throws ValidationException
     */
    public function resetPassword(array $credentials): bool
    {
        try {
            // 取得重設記錄
            $resetRecord = $this->user_repository->getPasswordResetRecord($credentials['email']);

            // 檢查記錄是否存在
            if (!$resetRecord) {
                throw ValidationException::withMessages([
                    'email' => ['無效的重設密碼連結'],
                ]);
            }

            // 驗證 token
            if (!Hash::check($credentials['token'], $resetRecord->token)) {
                throw ValidationException::withMessages([
                    'token' => ['無效的重設密碼連結'],
                ]);
            }

            // 檢查 token 是否過期（1小時）
            $createdAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($createdAt->addHour()->isPast()) {
                // 刪除過期的 token
                $this->user_repository->deletePasswordResetRecord($credentials['email']);

                throw ValidationException::withMessages([
                    'token' => ['重設密碼連結已過期'],
                ]);
            }

            // 取得使用者
            $user = $this->user_repository->findByEmail($credentials['email']);

            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['找不到該使用者'],
                ]);
            }

            // 更新密碼
            $this->user_repository->update($user, [
                'password' => Hash::make($credentials['password']),
            ]);

            // 刪除已使用的 token
            $this->user_repository->deletePasswordResetRecord($credentials['email']);

            // 刪除所有該使用者的 API tokens（強制重新登入）
            $this->user_repository->deleteAllTokens($user);

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('重設密碼失敗: ' . $e->getMessage());
            throw $e;
        }
    }
}
