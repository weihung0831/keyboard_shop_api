<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新密碼請求驗證
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 只有登入的使用者可以更新自己的密碼
        return true;
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 當前密碼：必填，字串
            'current_password' => ['required', 'string'],

            // 新密碼：必填，字串，至少 8 字元，需要確認，且不能與當前密碼相同
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * 取得自訂錯誤訊息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => '當前密碼為必填欄位',

            'new_password.required' => '新密碼為必填欄位',
            'new_password.min' => '新密碼至少需要 8 個字元',
            'new_password.confirmed' => '新密碼確認不一致',
            'new_password.different' => '新密碼不能與當前密碼相同',
        ];
    }
}
