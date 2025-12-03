<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 重設密碼請求驗證器
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 任何人都可以執行重設密碼請求
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
            // Token：必填、字串
            'token' => ['required', 'string'],

            // Email：必填、Email 格式
            'email' => ['required', 'email'],

            // 新密碼：必填、最少 8 字元、需要確認
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // 密碼確認：自動驗證 (confirmed 規則)
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * 取得自訂驗證錯誤訊息
     */
    public function messages(): array
    {
        return [
            'token.required' => '重設密碼 Token 為必填欄位',
            'token.string' => '重設密碼 Token 格式不正確',

            'email.required' => 'Email 為必填欄位',
            'email.email' => 'Email 格式不正確',

            'password.required' => '密碼為必填欄位',
            'password.min' => '密碼長度至少需要 8 個字元',
            'password.confirmed' => '密碼確認不一致',

            'password_confirmation.required' => '密碼確認為必填欄位',
        ];
    }
}
