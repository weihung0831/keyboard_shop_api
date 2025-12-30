<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 訂單項目資源轉換器
 */
class OrderItemResource extends JsonResource
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

            // 商品 ID
            'product_id' => $this->product_id,

            // 商品名稱（快照）
            'product_name' => $this->product_name,

            // 商品 SKU（快照）
            'product_sku' => $this->product_sku,

            // 數量
            'quantity' => $this->quantity,

            // 單價（快照）
            'price' => (float) $this->price,

            // 小計
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
