<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 建立訂單請求驗證
 */
class CreateOrderRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 只有登入的會員可以建立訂單
        return $this->user() !== null;
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 收件人姓名：必填，字串，最多 50 字元
            'shipping_name' => ['required', 'string', 'max:50'],

            // 收件人電話：必填，台灣手機號碼格式
            'shipping_phone' => ['required', 'string', 'max:20', 'regex:/^09\d{2}-?\d{3}-?\d{3}$/'],

            // 收件人 Email：選填，Email 格式
            'shipping_email' => ['nullable', 'string', 'email', 'max:100'],

            // 郵遞區號：選填，字串，最多 10 字元
            'shipping_postal_code' => ['nullable', 'string', 'max:10'],

            // 城市：選填，字串，最多 50 字元
            'shipping_city' => ['nullable', 'string', 'max:50'],

            // 收件地址：必填，字串，最多 200 字元
            'shipping_address' => ['required', 'string', 'max:200'],

            // 運送方式：選填，限定值
            'shipping_method' => ['nullable', 'string', 'in:standard,express,store_pickup'],

            // 訂單備註：選填，字串，最多 500 字元
            'notes' => ['nullable', 'string', 'max:500'],
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
            'shipping_name.required' => '請輸入收件人姓名',
            'shipping_name.max' => '收件人姓名不得超過 50 個字元',

            'shipping_phone.required' => '請輸入收件人電話',
            'shipping_phone.max' => '收件人電話不得超過 20 個字元',
            'shipping_phone.regex' => '請輸入有效的手機號碼格式（如：0912-345-678）',

            'shipping_email.email' => 'Email 格式不正確',
            'shipping_email.max' => 'Email 不得超過 100 個字元',

            'shipping_postal_code.max' => '郵遞區號不得超過 10 個字元',

            'shipping_city.max' => '城市不得超過 50 個字元',

            'shipping_address.required' => '請輸入收件地址',
            'shipping_address.max' => '收件地址不得超過 200 個字元',

            'shipping_method.in' => '請選擇有效的運送方式',

            'notes.max' => '訂單備註不得超過 500 個字元',
        ];
    }
}
