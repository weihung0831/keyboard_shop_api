<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理員會員詳情資源轉換器（含最近訂單與統計）
 */
class AdminUserDetailResource extends JsonResource
{
    /**
     * 訂單統計資料
     *
     * @var array<string, mixed>
     */
    private array $order_stats = [];

    /**
     * 最近訂單
     *
     * @var array<int, mixed>
     */
    private array $recent_orders = [];

    /**
     * 設定訂單統計與最近訂單
     *
     * @param  array<string, mixed>  $stats
     * @param  array<int, mixed>  $recent_orders
     */
    public function withOrderData(array $stats, array $recent_orders): static
    {
        $this->order_stats = $stats;
        $this->recent_orders = $recent_orders;

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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'orders_count' => $this->orders_count ?? 0,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // 訂單統計
            'order_stats' => $this->order_stats,

            // 最近 10 筆訂單（摘要）
            'recent_orders' => $this->recent_orders,
        ];
    }
}
