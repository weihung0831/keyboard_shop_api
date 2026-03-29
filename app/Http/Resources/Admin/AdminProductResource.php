<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理員商品資源轉換器（含所有欄位，包含未上架商品）
 */
class AdminProductResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 商品 ID
            'id' => $this->id,

            // 商品名稱
            'name' => $this->name,

            // URL slug
            'slug' => $this->slug,

            // 商品描述
            'description' => $this->description,

            // 商品完整介紹
            'content' => $this->content,

            // 商品編號
            'sku' => $this->sku,

            // 售價
            'price' => (float) $this->price,

            // 原價
            'original_price' => $this->original_price ? (float) $this->original_price : null,

            // 庫存數量
            'stock' => $this->stock,

            // 是否啟用
            'is_active' => (bool) $this->is_active,

            // 排序順序
            'sort_order' => $this->sort_order,

            // 所屬分類（需 eager load）
            'category' => new ProductCategoryResource($this->whenLoaded('category')),

            // 商品規格列表（需 eager load）
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),

            // 商品圖片列表（需 eager load）
            'images' => ProductImageResource::collection($this->whenLoaded('images')),

            // 建立時間
            'created_at' => $this->created_at->toIso8601String(),

            // 更新時間
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
