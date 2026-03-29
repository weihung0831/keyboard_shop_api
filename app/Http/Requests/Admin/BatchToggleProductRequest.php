<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員批次切換商品狀態請求驗證
 */
class BatchToggleProductRequest extends FormRequest
{
    /**
     * 只有管理員可以執行此請求
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 商品 ID 列表：必填，至少一筆
            'product_ids' => ['required', 'array', 'min:1'],

            // 每筆商品 ID：須存在於 products 資料表
            'product_ids.*' => ['integer', 'exists:products,id'],

            // 啟用狀態：必填，布林值
            'is_active' => ['required', 'boolean'],
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
            'product_ids.required' => '請選擇要操作的商品',
            'product_ids.array' => '商品 ID 格式不正確',
            'product_ids.min' => '至少需要選擇一個商品',
            'product_ids.*.integer' => '商品 ID 必須是整數',
            'product_ids.*.exists' => '部分商品不存在',
            'is_active.required' => '請指定啟用狀態',
            'is_active.boolean' => '啟用狀態格式不正確',
        ];
    }
}
