<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理員訂單詳情資源轉換器（含項目、付款詳情與時間軸）
 */
class AdminOrderDetailResource extends JsonResource
{
    /**
     * 時間軸資料
     */
    private array $timeline = [];

    /**
     * 設定時間軸資料
     */
    public function withTimeline(array $timeline): static
    {
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
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->status_label,

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
            'notes' => $this->notes,

            // 會員資訊
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]
            ),

            // 訂單項目（完整）
            'items' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ])
            ),

            // 付款詳情
            'payment' => $this->when(
                $this->relationLoaded('payment') && $this->payment,
                fn () => [
                    'id' => $this->payment->id,
                    'merchant_trade_no' => $this->payment->merchant_trade_no,
                    'trade_no' => $this->payment->trade_no,
                    'status' => $this->payment->status,
                    'status_label' => $this->payment->status_label,
                    'method' => $this->payment->payment_method,
                    'amount' => (float) $this->payment->amount,
                    'paid_at' => $this->payment->paid_at?->toIso8601String(),
                    'refunded_at' => $this->payment->refunded_at?->toIso8601String(),
                    'refund_amount' => $this->payment->refund_amount ? (float) $this->payment->refund_amount : null,
                ]
            ),

            // 時間戳記
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // 時間軸
            'timeline' => $this->timeline,
        ];
    }
}
