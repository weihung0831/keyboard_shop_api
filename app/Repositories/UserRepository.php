<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 使用者資料存取層
 * 只處理資料庫操作
 */
class UserRepository
{
    /**
     * 根據 Email 查詢使用者
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * 根據 ID 查詢使用者
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * 建立新使用者
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * 更新使用者資料
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);
        $user->refresh();
        return $user;
    }

    /**
     * 刪除使用者所有 tokens
     *
     * @param User $user
     * @return int 刪除數量
     */
    public function deleteAllTokens(User $user): int
    {
        return $user->tokens()->delete();
    }

    /**
     * 取得密碼重設記錄
     *
     * @param string $email
     * @return object|null
     */
    public function getPasswordResetRecord(string $email): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();
    }

    /**
     * 刪除密碼重設記錄
     *
     * @param string $email
     * @return int
     */
    public function deletePasswordResetRecord(string $email): int
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    /**
     * 建立密碼重設記錄
     *
     * @param string $email
     * @param string $hashedToken
     * @return bool
     */
    public function createPasswordResetRecord(string $email, string $hashedToken): bool
    {
        return DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
        ]);
    }
}
