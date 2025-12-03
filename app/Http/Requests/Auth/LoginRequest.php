<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 會員登入請求驗證
 */
class LoginRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 登入功能所有人都可以使用
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
            // Email：必填，Email 格式
            'email' => ['required', 'string', 'email'],

            // 密碼：必填，字串
            'password' => ['required', 'string'],
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
            'email.required' => 'Email 為必填欄位',
            'email.email' => 'Email 格式不正確',
            'password.required' => '密碼為必填欄位',
        ];
    }
}
