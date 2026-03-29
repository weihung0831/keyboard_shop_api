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
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->content,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'original_price' => $this->original_price ? (float) $this->original_price : null,
            'stock' => $this->stock,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
