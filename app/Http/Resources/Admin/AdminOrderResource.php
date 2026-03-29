<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理員訂單資源轉換器（列表用）
 */
class AdminOrderResource extends JsonResource
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

            // 會員資訊（條件載入）
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ]
            ),

            // 訂單項目數量（商品種類數）
            'items_count' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->count(),
                $this->items_count ?? 0
            ),

            // 付款資訊（條件載入）
            'payment' => $this->when(
                $this->relationLoaded('payment') && $this->payment,
                fn () => [
                    'id' => $this->payment->id,
                    'status' => $this->payment->status,
                    'method' => $this->payment->payment_method,
                ]
            ),

            // 時間戳記
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
