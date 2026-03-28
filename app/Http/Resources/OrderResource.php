<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 訂單資源轉換器
 */
class OrderResource extends JsonResource
{
    /**
     * 是否包含時間軸
     */
    private bool $include_timeline = false;

    /**
     * 時間軸資料
     */
    private array $timeline = [];

    /**
     * 設定時間軸資料
     */
    public function withTimeline(array $timeline): self
    {
        $this->include_timeline = true;
        $this->timeline = $timeline;

        return $this;
    }

    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            // 訂單基本資訊
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // 訂單項目
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            // 項目數量（商品種類數）
            'items_count' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->count(),
                0
            ),

            // 金額資訊
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'total_amount' => (float) $this->total_amount,

            // 收件資訊
            'shipping_name' => $this->shipping_name,
            'shipping_phone' => $this->shipping_phone,
            'shipping_email' => $this->shipping_email,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_city' => $this->shipping_city,
            'shipping_address' => $this->shipping_address,
            'shipping_method' => $this->shipping_method,

            // 備註
            'notes' => $this->notes,

            // 時間戳記
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            // 付款狀態（條件載入）
            'payment_status' => $this->when(
                $this->relationLoaded('payment'),
                fn () => $this->payment?->status
            ),
            'payment_status_label' => $this->when(
                $this->relationLoaded('payment'),
                fn () => $this->payment?->status_label
            ),
        ];

        // 如果有時間軸資料，則加入
        if ($this->include_timeline) {
            $data['timeline'] = $this->timeline;
        }

        return $data;
    }
}
