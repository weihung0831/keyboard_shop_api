<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 購物車項目資源轉換器
 */
class CartItemResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 項目 ID
            'id' => $this->id,

            // 商品資訊
            'product' => $this->when(
                $this->relationLoaded('product'),
                function () {
                    $product = $this->product;
                    if (!$product) {
                        return null;
                    }

                    // 取得主圖
                    $primary_image = null;
                    if ($product->relationLoaded('images')) {
                        $primary_image = $product->images->firstWhere('is_primary', true);
                    }

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'price' => (float) $product->price,
                        'stock' => $product->stock,
                        'is_active' => (bool) $product->is_active,
                        'primary_image' => $primary_image?->image_url,
                    ];
                }
            ),

            // 數量
            'quantity' => $this->quantity,

            // 加入時的價格（快照）
            'price' => (float) $this->price,

            // 小計
            'subtotal' => (float) $this->subtotal,

            // 時間戳
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
