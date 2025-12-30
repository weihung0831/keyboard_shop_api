<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 購物車資源轉換器
 */
class CartResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 購物車 ID
            'id' => $this->id,

            // 購物車項目
            'items' => CartItemResource::collection($this->whenLoaded('items')),

            // 總項目數（商品數量加總）
            'total_items' => $this->when(
                $this->relationLoaded('items'),
                fn() => $this->items->sum('quantity'),
                0
            ),

            // 總金額
            'total_amount' => $this->when(
                $this->relationLoaded('items'),
                fn() => (float) $this->items->sum(fn($item) => $item->quantity * $item->price),
                0
            ),

            // 時間戳
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
