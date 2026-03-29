<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員建立/更新商品規格請求驗證
 */
class StoreProductSpecRequest extends FormRequest
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
            // 規格名稱：必填，最多 100 字元
            'spec_name' => ['required', 'string', 'max:100'],

            // 規格值：必填，最多 255 字元
            'spec_value' => ['required', 'string', 'max:255'],

            // 排序順序：選填，整數，最小 0
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'spec_name.required' => '請輸入規格名稱',
            'spec_name.max' => '規格名稱不得超過 100 個字元',
            'spec_value.required' => '請輸入規格值',
            'spec_value.max' => '規格值不得超過 255 個字元',
            'sort_order.integer' => '排序順序必須是整數',
            'sort_order.min' => '排序順序不得低於 0',
        ];
    }
}
