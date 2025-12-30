<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 加入購物車請求驗證
 */
class AddToCartRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        // 所有人都可以加入購物車（會員和訪客）
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
            // 商品 ID：必填，整數，必須存在於 products 表
            'product_id' => ['required', 'integer', 'exists:products,id'],

            // 數量：必填，整數，最少 1
            'quantity' => ['required', 'integer', 'min:1'],
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
            'product_id.required' => '請選擇商品',
            'product_id.integer' => '商品 ID 格式不正確',
            'product_id.exists' => '商品不存在',

            'quantity.required' => '請輸入數量',
            'quantity.integer' => '數量必須為整數',
            'quantity.min' => '數量至少為 1',
        ];
    }
}
