<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 更新個人資料請求驗證
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 只有登入的使用者可以更新自己的資料
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
            // 姓名：選填，如有提供則為字串，最多 255 字元
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            // Email：選填，如有提供則驗證 Email 格式與唯一性（排除當前使用者），最多 255 字元
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],

            // 電話：選填，字串，最多 20 字元
            'phone' => ['nullable', 'string', 'max:20'],

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
            'email.unique' => '此 Email 已被使用',
            'email.max' => 'Email 不得超過 255 個字元',

            'phone.max' => '電話號碼不得超過 20 個字元',
        ];
    }
}
