<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 管理後台儀表板統計服務
 */
class AdminDashboardService
{
    /**
     * 取得儀表板統計資料
     *
     * @param  string  $period  統計期間：today | 7d | 30d | all
     * @return array<string, mixed>
     */
    public function getStats(string $period = '30d'): array
    {
        [$current_start, $current_end, $previous_start, $previous_end] = $this->resolvePeriodDates($period);

        $is_all = $period === 'all';

        return [
            'revenue' => $this->getRevenueStats(
                $current_start,
                $current_end,
                $previous_start,
                $previous_end,
                $is_all
            ),
            'orders' => $this->getOrderStats(
                $current_start,
                $current_end,
                $previous_start,
                $previous_end,
                $is_all
            ),
            'members' => $this->getMemberStats(
                $current_start,
                $current_end,
                $previous_start,
                $previous_end,
                $is_all
            ),
            'top_products' => $this->getTopProducts($current_start, $current_end, $is_all),
        ];
    }

    /**
     * 解析期間對應的日期範圍
     * 回傳 [current_start, current_end, previous_start, previous_end]
     *
     * @return array<int, Carbon|null>
     */
    private function resolvePeriodDates(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            '7d' => [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(13)->startOfDay(),
                $now->copy()->subDays(7)->endOfDay(),
            ],
            '30d' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(59)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
            ],
            'all' => [null, null, null, null],
            default => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(59)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
            ],
        };
    }

    /**
     * 計算趨勢百分比
     * - 若 is_all 為 true，回傳 null
     * - 若前期 = 0 且當期 > 0，回傳 100
     * - 若前後期皆為 0，回傳 0
     * - 其餘計算 (current - previous) / previous * 100，取 1 位小數
     */
    private function calculateTrend(float $current, float $previous, bool $is_all): ?float
    {
        if ($is_all) {
            return null;
        }

        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * 建立有效訂單查詢（非取消、已付款）
     *
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    private function validOrderQuery(?Carbon $start, ?Carbon $end)
    {
        $query = Order::query()
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->whereNotNull('paid_at');

        if ($start !== null && $end !== null) {
            $query->whereBetween('paid_at', [$start, $end]);
        }

        return $query;
    }

    /**
     * 取得營收統計
     *
     * @return array<string, mixed>
     */
    private function getRevenueStats(
        ?Carbon $current_start,
        ?Carbon $current_end,
        ?Carbon $previous_start,
        ?Carbon $previous_end,
        bool $is_all
    ): array {
        // 決定 period 字串以取得標籤
        $period_label = $this->resolvePeriodLabelFromDates($current_start, $is_all);

        $current_total = (float) $this->validOrderQuery($current_start, $current_end)->sum('total_amount');
        $previous_total = $is_all ? 0.0 : (float) $this->validOrderQuery($previous_start, $previous_end)->sum('total_amount');

        return [
            'total' => $current_total,
            'trend_percentage' => $this->calculateTrend($current_total, $previous_total, $is_all),
            'period_label' => $period_label,
        ];
    }

    /**
     * 取得訂單統計
     *
     * @return array<string, mixed>
     */
    private function getOrderStats(
        ?Carbon $current_start,
        ?Carbon $current_end,
        ?Carbon $previous_start,
        ?Carbon $previous_end,
        bool $is_all
    ): array {
        // 計算非取消、已付款的當期訂單數
        $current_count = (float) $this->validOrderQuery($current_start, $current_end)->count();
        $previous_count = $is_all ? 0.0 : (float) $this->validOrderQuery($previous_start, $previous_end)->count();

        // 計算當前 pending / processing 數量（不限期間）
        $pending_count = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->count();

        $processing_count = Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->count();

        return [
            'total' => (int) $current_count,
            'trend_percentage' => $this->calculateTrend($current_count, $previous_count, $is_all),
            'pending_count' => $pending_count,
            'processing_count' => $processing_count,
        ];
    }

    /**
     * 取得會員統計
     *
     * @return array<string, mixed>
     */
    private function getMemberStats(
        ?Carbon $current_start,
        ?Carbon $current_end,
        ?Carbon $previous_start,
        ?Carbon $previous_end,
        bool $is_all
    ): array {
        // 總會員數
        $total = User::query()->count();

        // 期間新增會員數
        $new_query = User::query();
        if ($current_start !== null && $current_end !== null) {
            $new_query->whereBetween('created_at', [$current_start, $current_end]);
        }
        $new_count = (float) $new_query->count();

        $previous_new = 0.0;
        if (! $is_all && $previous_start !== null && $previous_end !== null) {
            $previous_new = (float) User::query()
                ->whereBetween('created_at', [$previous_start, $previous_end])
                ->count();
        }

        return [
            'total' => $total,
            'new_count' => (int) $new_count,
            'trend_percentage' => $this->calculateTrend($new_count, $previous_new, $is_all),
        ];
    }

    /**
     * 取得熱門商品（前5名，依銷售數量排序）
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTopProducts(?Carbon $start, ?Carbon $end, bool $is_all): array
    {
        $query = OrderItem::query()
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.subtotal) as total_revenue')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->whereNotNull('orders.paid_at')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_quantity')
            ->limit(5);

        if (! $is_all && $start !== null && $end !== null) {
            $query->whereBetween('orders.paid_at', [$start, $end]);
        }

        return $query->get()->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ];
        })->toArray();
    }

    /**
     * 根據日期範圍反推期間標籤（供 revenue stats 使用）
     */
    private function resolvePeriodLabelFromDates(?Carbon $start, bool $is_all): string
    {
        if ($is_all) {
            return '全部時間';
        }

        if ($start === null) {
            return '近30天';
        }

        $days = (int) Carbon::now()->startOfDay()->diffInDays($start->copy()->startOfDay());

        return match (true) {
            $days === 0 => '今日',
            $days <= 7 => '近7天',
            $days <= 30 => '近30天',
            default => '近30天',
        };
    }
}
