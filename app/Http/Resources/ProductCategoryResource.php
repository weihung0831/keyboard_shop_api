<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 產品分類資源轉換器
 */
class ProductCategoryResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 分類 ID
            'id' => $this->id,

            // 分類名稱
            'name' => $this->name,

            // 分類 URL slug
            'slug' => $this->slug,

            // 分類描述
            'description' => $this->description,

            // 是否啟用
            'is_active' => (bool) $this->is_active,

            // 排序順序
            'sort_order' => $this->sort_order,

            // 該分類下的產品數量（選擇性載入）
            'products_count' => $this->whenCounted('products'),

            // 建立時間
            'created_at' => $this->created_at->toIso8601String(),

            // 更新時間
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
