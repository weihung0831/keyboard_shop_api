<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理員更新商品請求驗證
 */
class UpdateProductRequest extends FormRequest
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
        // 取得目前編輯的商品 ID（忽略自身唯一性驗證）
        $productId = $this->route('id');

        return [
            // 商品名稱：必填
            'name' => ['required', 'string', 'max:255'],

            // 所屬分類：必填，須存在
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],

            // 售價：必填，數字，最小 0
            'price' => ['required', 'numeric', 'min:0'],

            // 庫存：必填，整數，最小 0
            'stock' => ['required', 'integer', 'min:0'],

            // 商品編號：必填，唯一（忽略自身）
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],

            // URL slug：選填，唯一（忽略自身）
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],

            // 商品描述：選填
            'description' => ['nullable', 'string'],

            // 商品內容：選填
            'content' => ['nullable', 'string'],

            // 原價：選填，數字，最小 0
            'original_price' => ['nullable', 'numeric', 'min:0'],

            // 是否啟用：布林值
            'is_active' => ['boolean'],

            // 排序順序：整數
            'sort_order' => ['integer'],

            // 規格列表：選填陣列（若提供則同步取代現有規格）
            'specifications' => ['nullable', 'array'],

            // 規格名稱：規格存在時必填
            'specifications.*.spec_name' => ['required_with:specifications', 'string', 'max:100'],

            // 規格值：規格存在時必填
            'specifications.*.spec_value' => ['required_with:specifications', 'string', 'max:255'],

            // 規格排序：選填
            'specifications.*.sort_order' => ['nullable', 'integer', 'min:0'],
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
            'name.required' => '請輸入商品名稱',
            'name.max' => '商品名稱不得超過 255 個字元',
            'category_id.required' => '請選擇商品分類',
            'category_id.exists' => '所選分類不存在',
            'price.required' => '請輸入售價',
            'price.numeric' => '售價必須是數字',
            'price.min' => '售價不得低於 0',
            'stock.required' => '請輸入庫存數量',
            'stock.integer' => '庫存必須是整數',
            'stock.min' => '庫存不得低於 0',
            'sku.required' => '請輸入商品編號',
            'sku.max' => '商品編號不得超過 100 個字元',
            'sku.unique' => '此商品編號已存在',
            'slug.unique' => '此 Slug 已被使用',
            'original_price.numeric' => '原價必須是數字',
            'original_price.min' => '原價不得低於 0',
            'specifications.*.spec_name.required_with' => '請輸入規格名稱',
            'specifications.*.spec_name.max' => '規格名稱不得超過 100 個字元',
            'specifications.*.spec_value.required_with' => '請輸入規格值',
            'specifications.*.spec_value.max' => '規格值不得超過 255 個字元',
        ];
    }
}
