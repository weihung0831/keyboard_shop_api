<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 會員註冊請求驗證
 */
class RegisterRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 註冊功能所有人都可以使用
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
            // 姓名：必填，字串，最多 255 字元
            'name' => ['required', 'string', 'max:255'],

            // Email：必填，Email 格式，唯一（users 表），最多 255 字元
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],

            // 密碼：必填，字串，至少 8 字元，需要確認
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // 電話：選填，字串，最多 20 字元，台灣手機號碼格式 09XX-XXX-XXX
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^09\d{8}$/'],

            // 地址：選填，字串
            'address' => ['nullable', 'string'],
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
            'name.required' => '姓名為必填欄位',
            'name.max' => '姓名不得超過 255 個字元',

            'email.required' => 'Email 為必填欄位',
            'email.email' => 'Email 格式不正確',
            'email.unique' => '此 Email 已被註冊',
            'email.max' => 'Email 不得超過 255 個字元',

            'password.required' => '密碼為必填欄位',
            'password.min' => '密碼至少需要 8 個字元',
            'password.confirmed' => '密碼確認不一致',

            'phone.max' => '電話號碼不得超過 20 個字元',
        ];
    }
}
