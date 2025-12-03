<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 使用者服務層
 * 處理使用者個人資料相關商業邏輯
 */
class UserService
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
     * 更新使用者個人資料
     *
     * @param User $user 使用者
     * @param array $data 更新資料
     * @return User
     * @throws \Exception
     */
    public function updateProfile(User $user, array $data): User
    {
        try {
            // 準備要更新的資料（只更新有提供的欄位）
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $updateData['phone'] = $data['phone'];
            }

            if (array_key_exists('address', $data)) {
                $updateData['address'] = $data['address'];
            }

            // 透過 Repository 更新使用者資料
            return $this->user_repository->update($user, $updateData);
        } catch (\Exception $e) {
            // 記錄錯誤
            \Log::error('更新個人資料失敗: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新使用者密碼
     *
     * @param User $user 使用者
     * @param string $currentPassword 當前密碼
     * @param string $newPassword 新密碼
     * @return User
     * @throws ValidationException
     * @throws \Exception
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): User
    {
        // 驗證當前密碼
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['當前密碼錯誤'],
            ]);
        }

        try {
            // 更新密碼
            // 注意：必須直接設定屬性而非透過 update，以避免 hashed cast 問題
            $user->password = Hash::make($newPassword);
            $user->save();

            // 刪除其他裝置的 token（強制重新登入）
            // 檢查當前 token 是否為 PersonalAccessToken（非 TransientToken）
            $currentToken = $user->currentAccessToken();
            if ($currentToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            }

            return $user;
        } catch (\Exception $e) {
            // 記錄錯誤
            \Log::error('更新密碼失敗: ' . $e->getMessage());
            throw $e;
        }
    }
}
