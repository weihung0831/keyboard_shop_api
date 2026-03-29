<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\AdminOrderDetailResource;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 管理員訂單控制器
 * 處理訂單列表、詳情與狀態更新
 */
class AdminOrderController extends Controller
{
    /**
     * 允許的狀態流轉對應表
     * key = 目前狀態，value = 允許轉換的目標狀態陣列
     *
     * @var array<string, array<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
        Order::STATUS_SHIPPED => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_COMPLETED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function __construct(private OrderService $order_service) {}

    /**
     * 取得訂單列表（含篩選）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::query()->with(['user', 'payment'])->withCount('items');

            // 依狀態篩選
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // 搜尋訂單編號或會員姓名 / Email（跳脫 LIKE 萬用字元）
            if ($request->filled('search')) {
                $keyword = $this->escapeLike($request->input('search'));
                $query->where(function ($q) use ($keyword) {
                    $q->where('order_number', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function ($uq) use ($keyword) {
                            $uq->where('name', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%");
                        });
                });
            }

            // 依日期範圍篩選
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $query->latest();

            $per_page = min((int) $request->input('per_page', 15), 50);
            $orders = $query->paginate($per_page);

            return response()->json([
                'message' => '取得訂單列表成功',
                'data' => AdminOrderResource::collection($orders),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得訂單列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得訂單詳情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = Order::with(['user', 'items', 'payment'])->find($id);

            if (! $order) {
                return response()->json(['message' => '訂單不存在'], 404);
            }

            $timeline = $this->order_service->getOrderTimeline($order);

            return response()->json([
                'message' => '取得訂單詳情成功',
                'data' => (new AdminOrderDetailResource($order))->withTimeline($timeline),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得訂單詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新訂單狀態（含嚴格流轉規則與庫存回補）
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        try {
            $order = Order::with('items')->find($id);

            if (! $order) {
                return response()->json(['message' => '訂單不存在'], 404);
            }

            $new_status = $request->input('status');
            $current_status = $order->status;

            // 驗證狀態流轉是否合法
            $allowed = self::ALLOWED_TRANSITIONS[$current_status] ?? [];
            if (! in_array($new_status, $allowed, true)) {
                return response()->json([
                    'message' => "無法從「{$order->status_label}」更新為「".Order::STATUS_LABELS[$new_status].'」',
                ], 422);
            }

            DB::transaction(function () use ($order, $new_status) {
                $timestamp_field = match ($new_status) {
                    Order::STATUS_PROCESSING => 'paid_at',
                    Order::STATUS_SHIPPED => 'shipped_at',
                    Order::STATUS_COMPLETED => 'completed_at',
                    Order::STATUS_CANCELLED => 'cancelled_at',
                    default => null,
                };

                $update_data = ['status' => $new_status];

                if ($timestamp_field !== null) {
                    $update_data[$timestamp_field] = now();
                }

                // 取消訂單時回補庫存
                if ($new_status === Order::STATUS_CANCELLED) {
                    foreach ($order->items as $item) {
                        if ($item->product_id) {
                            Product::where('id', $item->product_id)
                                ->increment('stock', $item->quantity);
                        }
                    }
                }

                $order->update($update_data);
            });

            $order->refresh();

            return response()->json([
                'message' => '訂單狀態已更新',
                'data' => new AdminOrderResource($order),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '更新訂單狀態失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 跳脫 LIKE 查詢的萬用字元（% 和 _）
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
