<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員上傳商品圖片請求驗證
 */
class StoreProductImageRequest extends FormRequest
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
            // 圖片列表：必填，至少一張
            'images' => ['required', 'array', 'min:1'],

            // 每張圖片：圖片格式，限制類型與大小（最大 5MB）
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'images.required' => '請選擇要上傳的圖片',
            'images.array' => '圖片格式不正確',
            'images.min' => '至少需要上傳一張圖片',
            'images.*.image' => '上傳的檔案必須是圖片',
            'images.*.mimes' => '圖片格式僅支援 JPG、JPEG、PNG、WebP',
            'images.*.max' => '每張圖片大小不得超過 5MB',
        ];
    }
}
