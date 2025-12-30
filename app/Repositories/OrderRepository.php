<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * 訂單資料存取層
 * 只處理訂單相關的資料庫操作
 */
class OrderRepository
{
    /**
     * 根據 ID 查找訂單
     *
     * @param int $id 訂單 ID
     * @param int|null $user_id 限制會員 ID（可選，用於權限檢查）
     * @return Order|null 訂單物件，不存在時返回 null
     */
    public function findById(int $id, ?int $user_id = null): ?Order
    {
        $query = Order::with(['items', 'user']);

        // 如果指定會員 ID，則限制只能查詢該會員的訂單
        if ($user_id !== null) {
            $query->forUser($user_id);
        }

        return $query->find($id);
    }

    /**
     * 根據訂單編號查找訂單
     *
     * @param string $order_number 訂單編號
     * @param int|null $user_id 限制會員 ID（可選）
     * @return Order|null 訂單物件，不存在時返回 null
     */
    public function findByOrderNumber(string $order_number, ?int $user_id = null): ?Order
    {
        $query = Order::with(['items', 'user'])
            ->where('order_number', $order_number);

        if ($user_id !== null) {
            $query->forUser($user_id);
        }

        return $query->first();
    }

    /**
     * 取得會員訂單列表（支援分頁、篩選）
     *
     * @param int $user_id 會員 ID
     * @param array $filters 篩選條件 (status, start_date, end_date)
     * @param int $per_page 每頁筆數
     * @return LengthAwarePaginator 分頁結果
     */
    public function getByUserId(int $user_id, array $filters = [], int $per_page = 10): LengthAwarePaginator
    {
        $query = Order::with(['items'])
            ->forUser($user_id);

        // 狀態篩選
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // 開始日期篩選
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        // 結束日期篩選
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // 按建立時間倒序排列
        $query->latest();

        return $query->paginate($per_page);
    }

    /**
     * 取得會員所有訂單（不分頁）
     *
     * @param int $user_id 會員 ID
     * @param string|null $status 訂單狀態篩選（可選）
     * @return Collection 訂單集合
     */
    public function getAllByUserId(int $user_id, ?string $status = null): Collection
    {
        $query = Order::with(['items'])
            ->forUser($user_id);

        if ($status !== null) {
            $query->status($status);
        }

        return $query->latest()->get();
    }

    /**
     * 統計會員訂單數量
     *
     * @param int $user_id 會員 ID
     * @param string|null $status 訂單狀態篩選（可選）
     * @return int 訂單數量
     */
    public function countByUserId(int $user_id, ?string $status = null): int
    {
        $query = Order::forUser($user_id);

        if ($status !== null) {
            $query->status($status);
        }

        return $query->count();
    }

    /**
     * 建立訂單
     *
     * @param array $data 訂單資料
     * @return Order 新建立的訂單
     */
    public function create(array $data): Order
    {
        // 產生訂單編號
        $data['order_number'] = Order::generateOrderNumber();

        // 設定預設狀態
        if (!isset($data['status'])) {
            $data['status'] = Order::STATUS_PENDING;
        }

        $order = Order::create($data);

        return $order->fresh(['items', 'user']);
    }

    /**
     * 批量新增訂單項目
     *
     * @param Order $order 訂單物件
     * @param array $items 項目資料陣列
     * @return SupportCollection 新建立的訂單項目集合
     */
    public function createItems(Order $order, array $items): SupportCollection
    {
        $created_items = collect();

        foreach ($items as $item_data) {
            $item = $order->items()->create([
                'product_id' => $item_data['product_id'],
                'product_name' => $item_data['product_name'],
                'product_sku' => $item_data['product_sku'],
                'quantity' => $item_data['quantity'],
                'price' => $item_data['price'],
                'subtotal' => $item_data['quantity'] * $item_data['price'],
            ]);

            $created_items->push($item);
        }

        return $created_items;
    }

    /**
     * 更新訂單狀態
     *
     * @param Order $order 訂單物件
     * @param string $status 新狀態
     * @param array $additional_data 額外更新的資料（如時間戳記）
     * @return Order 更新後的訂單
     */
    public function updateStatus(Order $order, string $status, array $additional_data = []): Order
    {
        $update_data = array_merge(['status' => $status], $additional_data);

        $order->update($update_data);

        return $order->fresh(['items', 'user']);
    }

    /**
     * 取消訂單
     *
     * @param Order $order 訂單物件
     * @return Order 更新後的訂單
     */
    public function cancel(Order $order): Order
    {
        return $this->updateStatus($order, Order::STATUS_CANCELLED, [
            'cancelled_at' => now(),
        ]);
    }

    /**
     * 標記訂單為已付款
     *
     * @param Order $order 訂單物件
     * @return Order 更新後的訂單
     */
    public function markAsPaid(Order $order): Order
    {
        return $this->updateStatus($order, Order::STATUS_PROCESSING, [
            'paid_at' => now(),
        ]);
    }

    /**
     * 標記訂單為已出貨
     *
     * @param Order $order 訂單物件
     * @return Order 更新後的訂單
     */
    public function markAsShipped(Order $order): Order
    {
        return $this->updateStatus($order, Order::STATUS_SHIPPED, [
            'shipped_at' => now(),
        ]);
    }

    /**
     * 標記訂單為已完成
     *
     * @param Order $order 訂單物件
     * @return Order 更新後的訂單
     */
    public function markAsCompleted(Order $order): Order
    {
        return $this->updateStatus($order, Order::STATUS_COMPLETED, [
            'completed_at' => now(),
        ]);
    }

    /**
     * 更新訂單資料
     *
     * @param Order $order 訂單物件
     * @param array $data 要更新的資料
     * @return Order 更新後的訂單
     */
    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh(['items', 'user']);
    }

    /**
     * 刪除訂單
     *
     * @param Order $order 訂單物件
     * @return bool 是否成功刪除
     */
    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    /**
     * 批量刪除訂單項目
     *
     * @param Order $order 訂單物件
     * @return int 刪除的項目數量
     */
    public function deleteItems(Order $order): int
    {
        return $order->items()->delete();
    }

    /**
     * 取得指定日期範圍內的訂單統計
     *
     * @param string $start_date 開始日期
     * @param string $end_date 結束日期
     * @return array 統計資料
     */
    public function getStatsByDateRange(string $start_date, string $end_date): array
    {
        $query = Order::whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date);

        return [
            'total_orders' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'by_status' => $query->selectRaw('status, COUNT(*) as count, SUM(total_amount) as amount')
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->toArray(),
        ];
    }

    /**
     * 檢查訂單是否屬於指定會員
     *
     * @param int $order_id 訂單 ID
     * @param int $user_id 會員 ID
     * @return bool 是否屬於該會員
     */
    public function belongsToUser(int $order_id, int $user_id): bool
    {
        return Order::where('id', $order_id)
            ->where('user_id', $user_id)
            ->exists();
    }
}
