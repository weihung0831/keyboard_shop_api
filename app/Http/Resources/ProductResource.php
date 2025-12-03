<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 產品資源轉換器
 */
class ProductResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 產品 ID
            'id' => $this->id,

            // 產品名稱
            'name' => $this->name,

            // 產品 URL slug
            'slug' => $this->slug,

            // 產品描述
            'description' => $this->description,

            // 產品完整介紹
            'content' => $this->content,

            // 商品編號
            'sku' => $this->sku,

            // 產品規格（JSON）
            'specifications' => $this->specifications,

            // 價格
            'price' => (float) $this->price,

            // 原價（選填）
            'original_price' => $this->original_price ? (float) $this->original_price : null,

            // 庫存數量
            'stock' => $this->stock,

            // 是否啟用
            'is_active' => (bool) $this->is_active,

            // 所屬分類
            'category' => new ProductCategoryResource($this->whenLoaded('category')),

            // 產品圖片列表
            'images' => ProductImageResource::collection($this->whenLoaded('images')),

            // 主圖 URL（快速存取）
            'primary_image' => $this->when(
                $this->relationLoaded('images'),
                function () {
                    $primaryImage = $this->images->firstWhere('is_primary', true);
                    return $primaryImage ? $primaryImage->image_url : null;
                }
            ),

            // 建立時間
            'created_at' => $this->created_at->toIso8601String(),

            // 更新時間
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
