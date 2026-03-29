<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員更新分類請求驗證
 */
class UpdateCategoryRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * 取得驗證規則
     * slug 唯一性忽略自身（編輯時不衝突）
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category_id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', "unique:product_categories,slug,{$category_id}"],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
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
            'name.required' => '請輸入分類名稱',
            'name.max' => '分類名稱不得超過 255 個字元',
            'slug.unique' => '此 Slug 已被使用，請更換',
            'slug.max' => 'Slug 不得超過 255 個字元',
            'is_active.boolean' => '啟用狀態必須為布林值',
            'sort_order.integer' => '排序必須為整數',
            'sort_order.min' => '排序不得小於 0',
        ];
    }
}
