<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 忘記密碼請求驗證器
 */
class ForgotPasswordRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 任何人都可以執行忘記密碼請求
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
            // Email：必填、Email 格式
            'email' => ['required', 'email'],
        ];
    }

    /**
     * 取得自訂驗證錯誤訊息
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email 為必填欄位',
            'email.email' => 'Email 格式不正確',
        ];
    }
}
