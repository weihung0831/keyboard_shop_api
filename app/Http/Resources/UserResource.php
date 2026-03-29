<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 使用者資源轉換器
 */
class UserResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 使用者 ID
            'id' => $this->id,

            // 使用者姓名
            'name' => $this->name,

            // 使用者 Email
            'email' => $this->email,

            // Email 是否已驗證
            'email_verified' => !is_null($this->email_verified_at),

            // Email 驗證時間
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),

            // 電話號碼（選填）
            'phone' => $this->phone,

            // 地址（選填）
            'address' => $this->address,

            // 使用者角色
            'role' => $this->role ?? 'user',

            // 建立時間
            'created_at' => $this->created_at->toIso8601String(),

            // 更新時間
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
