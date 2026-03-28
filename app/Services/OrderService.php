<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 訂單服務層
 * 處理訂單相關商業邏輯
 */
class OrderService
{
    /**
     * 訂單資料存取層
     */
    private OrderRepository $order_repository;

    /**
     * 購物車資料存取層
     */
    private CartRepository $cart_repository;

    /**
     * 產品資料存取層
     */
    private ProductRepository $product_repository;

    /**
     * 購物車服務層
     */
    private CartService $cart_service;

    /**
     * 建構函式
     */
    public function __construct(
        OrderRepository $order_repository,
        CartRepository $cart_repository,
        ProductRepository $product_repository,
        CartService $cart_service
    ) {
        $this->order_repository = $order_repository;
        $this->cart_repository = $cart_repository;
        $this->product_repository = $product_repository;
        $this->cart_service = $cart_service;
    }

    /**
     * 建立訂單
     * 從購物車建立訂單，並清空購物車
     *
     * @param  int  $user_id  會員 ID
     * @param  array  $shipping_data  收件資訊
     * @return Order 新建立的訂單
     *
     * @throws InvalidArgumentException 當購物車為空或庫存不足時
     */
    public function createOrder(int $user_id, array $shipping_data): Order
    {
        return DB::transaction(function () use ($user_id, $shipping_data) {
            // 取得會員購物車
            $cart = $this->cart_repository->findByUserId($user_id);

            // 驗證購物車不為空
            if (! $cart || $cart->items->isEmpty()) {
                throw new InvalidArgumentException('購物車是空的，無法建立訂單');
            }

            // 驗證所有商品庫存
            $stock_errors = $this->cart_service->validateCartStock($cart);
            if (! empty($stock_errors)) {
                throw new InvalidArgumentException(json_encode([
                    'message' => '部分商品庫存不足',
                    'items' => $stock_errors,
                ]));
            }

            // 計算訂單金額
            $shipping_fee = $this->calculateShippingFee($shipping_data['shipping_method'] ?? 'standard');
            $totals = $this->calculateOrderTotals($cart, $shipping_fee);

            // 建立訂單
            $order = $this->order_repository->create([
                'user_id' => $user_id,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $totals['subtotal'],
                'shipping_fee' => $totals['shipping_fee'],
                'total_amount' => $totals['total_amount'],
                'shipping_name' => $shipping_data['shipping_name'],
                'shipping_phone' => $shipping_data['shipping_phone'],
                'shipping_email' => $shipping_data['shipping_email'] ?? null,
                'shipping_postal_code' => $shipping_data['shipping_postal_code'] ?? null,
                'shipping_city' => $shipping_data['shipping_city'] ?? null,
                'shipping_address' => $shipping_data['shipping_address'],
                'shipping_method' => $shipping_data['shipping_method'] ?? 'standard',
                'notes' => $shipping_data['notes'] ?? null,
            ]);

            // 建立訂單項目（儲存商品快照）並扣減庫存
            $order_items = [];
            foreach ($cart->items as $cart_item) {
                $product = $cart_item->product;
                $order_items[] = [
                    'product_id' => $cart_item->product_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku ?? '',
                    'quantity' => $cart_item->quantity,
                    'price' => $cart_item->price,
                ];

                // 扣減庫存
                $product->decrement('stock', $cart_item->quantity);
            }
            $this->order_repository->createItems($order, $order_items);

            // 清空購物車
            $this->cart_repository->clearItems($cart);

            // 重新載入訂單（含項目）
            return $order->fresh(['items', 'user']);
        });
    }

    /**
     * 取得會員訂單列表
     *
     * @param  int  $user_id  會員 ID
     * @param  array  $filters  篩選條件 (status, start_date, end_date)
     * @param  int  $per_page  每頁筆數
     * @return LengthAwarePaginator 分頁結果
     */
    public function getOrderList(int $user_id, array $filters = [], int $per_page = 10): LengthAwarePaginator
    {
        // 限制每頁最大筆數
        $per_page = min($per_page, 50);

        return $this->order_repository->getByUserId($user_id, $filters, $per_page);
    }

    /**
     * 取得訂單詳情
     *
     * @param  int  $order_id  訂單 ID
     * @param  int  $user_id  會員 ID（用於權限驗證）
     * @return Order 訂單物件
     *
     * @throws InvalidArgumentException 當訂單不存在或無權限時
     */
    public function getOrderDetail(int $order_id, int $user_id): Order
    {
        $order = $this->order_repository->findById($order_id, $user_id);

        if (! $order) {
            throw new InvalidArgumentException('訂單不存在或無權限查看');
        }

        return $order;
    }

    /**
     * 取消訂單
     * pending 訂單直接取消，processing 訂單需先退款
     *
     * @param  int  $order_id  訂單 ID
     * @param  int  $user_id  會員 ID（用於權限驗證）
     * @return Order 更新後的訂單
     *
     * @throws InvalidArgumentException 當訂單不存在、無權限或無法取消時
     */
    public function cancelOrder(int $order_id, int $user_id): Order
    {
        $order = $this->order_repository->findById($order_id, $user_id);

        if (! $order) {
            throw new InvalidArgumentException('訂單不存在或無權限操作');
        }

        if (! $order->canBeCancelled()) {
            throw new InvalidArgumentException('此訂單狀態無法取消');
        }

        return DB::transaction(function () use ($order, $order_id, $user_id) {
            // 已付款（processing）→ 先退款，同一 transaction 內執行
            if ($order->status === Order::STATUS_PROCESSING) {
                app(PaymentService::class)->refundPayment($order_id, $user_id);
            }

            // 回補庫存並取消訂單
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

            return $this->order_repository->cancel($order);
        });
    }

    /**
     * 取得訂單時間軸
     *
     * @param  Order  $order  訂單物件
     * @return array 時間軸資料
     */
    public function getOrderTimeline(Order $order): array
    {
        $timeline = [];

        // 訂單建立
        $timeline[] = [
            'status' => 'created',
            'label' => '訂單建立',
            'time' => $order->created_at->toIso8601String(),
            'is_completed' => true,
            'is_current' => $order->status === Order::STATUS_PENDING,
        ];

        // 已付款
        if ($order->paid_at) {
            $timeline[] = [
                'status' => Order::STATUS_PROCESSING,
                'label' => '已付款',
                'time' => $order->paid_at->toIso8601String(),
                'is_completed' => true,
                'is_current' => $order->status === Order::STATUS_PROCESSING,
            ];
        } else {
            $timeline[] = [
                'status' => Order::STATUS_PROCESSING,
                'label' => '待付款',
                'time' => null,
                'is_completed' => false,
                'is_current' => false,
            ];
        }

        // 已出貨
        if ($order->shipped_at) {
            $timeline[] = [
                'status' => Order::STATUS_SHIPPED,
                'label' => '已出貨',
                'time' => $order->shipped_at->toIso8601String(),
                'is_completed' => true,
                'is_current' => $order->status === Order::STATUS_SHIPPED,
            ];
        } elseif ($order->status !== Order::STATUS_CANCELLED) {
            $timeline[] = [
                'status' => Order::STATUS_SHIPPED,
                'label' => '待出貨',
                'time' => null,
                'is_completed' => false,
                'is_current' => false,
            ];
        }

        // 已完成
        if ($order->completed_at) {
            $timeline[] = [
                'status' => Order::STATUS_COMPLETED,
                'label' => '已完成',
                'time' => $order->completed_at->toIso8601String(),
                'is_completed' => true,
                'is_current' => $order->status === Order::STATUS_COMPLETED,
            ];
        } elseif ($order->status !== Order::STATUS_CANCELLED) {
            $timeline[] = [
                'status' => Order::STATUS_COMPLETED,
                'label' => '待完成',
                'time' => null,
                'is_completed' => false,
                'is_current' => false,
            ];
        }

        // 已取消（如果有）
        if ($order->cancelled_at) {
            $timeline[] = [
                'status' => Order::STATUS_CANCELLED,
                'label' => '已取消',
                'time' => $order->cancelled_at->toIso8601String(),
                'is_completed' => true,
                'is_current' => $order->status === Order::STATUS_CANCELLED,
            ];
        }

        return $timeline;
    }

    /**
     * 計算訂單金額
     *
     * @param  Cart  $cart  購物車物件
     * @param  float  $shipping_fee  運費
     * @return array 金額資料 (subtotal, shipping_fee, total_amount)
     */
    public function calculateOrderTotals(Cart $cart, float $shipping_fee = 0): array
    {
        // 計算商品小計
        $subtotal = $cart->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });

        return [
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping_fee,
            'total_amount' => $subtotal + $shipping_fee,
        ];
    }

    /**
     * 計算運費
     *
     * @param  string  $shipping_method  運送方式
     * @return float 運費金額
     */
    public function calculateShippingFee(string $shipping_method): float
    {
        // 運費設定（可依需求調整或從設定檔讀取）
        $shipping_fees = [
            'standard' => 60,       // 標準配送
            'express' => 150,       // 快速配送
            'store_pickup' => 0,    // 門市取貨
        ];

        return $shipping_fees[$shipping_method] ?? 60;
    }

    /**
     * 統計會員訂單數量
     *
     * @param  int  $user_id  會員 ID
     * @param  string|null  $status  訂單狀態篩選（可選）
     * @return int 訂單數量
     */
    public function countUserOrders(int $user_id, ?string $status = null): int
    {
        return $this->order_repository->countByUserId($user_id, $status);
    }

    /**
     * 取得會員訂單統計
     *
     * @param  int  $user_id  會員 ID
     * @return array 統計資料
     */
    public function getUserOrderStats(int $user_id): array
    {
        return [
            'total' => $this->order_repository->countByUserId($user_id),
            'pending' => $this->order_repository->countByUserId($user_id, Order::STATUS_PENDING),
            'processing' => $this->order_repository->countByUserId($user_id, Order::STATUS_PROCESSING),
            'shipped' => $this->order_repository->countByUserId($user_id, Order::STATUS_SHIPPED),
            'completed' => $this->order_repository->countByUserId($user_id, Order::STATUS_COMPLETED),
            'cancelled' => $this->order_repository->countByUserId($user_id, Order::STATUS_CANCELLED),
        ];
    }

    /**
     * 驗證訂單是否屬於指定會員
     *
     * @param  int  $order_id  訂單 ID
     * @param  int  $user_id  會員 ID
     * @return bool 是否屬於該會員
     */
    public function verifyOrderOwnership(int $order_id, int $user_id): bool
    {
        return $this->order_repository->belongsToUser($order_id, $user_id);
    }

    /**
     * 標記訂單為已付款（供後台或金流回調使用）
     *
     * @param  int  $order_id  訂單 ID
     * @return Order 更新後的訂單
     *
     * @throws InvalidArgumentException 當訂單不存在或狀態不正確時
     */
    public function markOrderAsPaid(int $order_id): Order
    {
        $order = $this->order_repository->findById($order_id);

        if (! $order) {
            throw new InvalidArgumentException('訂單不存在');
        }

        if ($order->status !== Order::STATUS_PENDING) {
            throw new InvalidArgumentException('只有待付款訂單可標記為已付款');
        }

        return $this->order_repository->markAsPaid($order);
    }

    /**
     * 標記訂單為已出貨（供後台使用）
     *
     * @param  int  $order_id  訂單 ID
     * @return Order 更新後的訂單
     *
     * @throws InvalidArgumentException 當訂單不存在或狀態不正確時
     */
    public function markOrderAsShipped(int $order_id): Order
    {
        $order = $this->order_repository->findById($order_id);

        if (! $order) {
            throw new InvalidArgumentException('訂單不存在');
        }

        if ($order->status !== Order::STATUS_PROCESSING) {
            throw new InvalidArgumentException('只有處理中訂單可標記為已出貨');
        }

        return $this->order_repository->markAsShipped($order);
    }

    /**
     * 標記訂單為已完成（供後台使用）
     *
     * @param  int  $order_id  訂單 ID
     * @return Order 更新後的訂單
     *
     * @throws InvalidArgumentException 當訂單不存在或狀態不正確時
     */
    public function markOrderAsCompleted(int $order_id): Order
    {
        $order = $this->order_repository->findById($order_id);

        if (! $order) {
            throw new InvalidArgumentException('訂單不存在');
        }

        if ($order->status !== Order::STATUS_SHIPPED) {
            throw new InvalidArgumentException('只有已出貨訂單可標記為已完成');
        }

        return $this->order_repository->markAsCompleted($order);
    }
}
