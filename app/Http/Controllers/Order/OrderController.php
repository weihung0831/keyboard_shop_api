<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * 訂單控制器
 * 處理訂單相關 HTTP 請求
 */
class OrderController extends Controller
{
    /**
     * 訂單服務
     */
    private OrderService $order_service;

    /**
     * 建構函式
     */
    public function __construct(OrderService $order_service)
    {
        $this->order_service = $order_service;
    }

    /**
     * 取得訂單列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 取得會員 ID
            $user_id = $request->user()->id;

            // 取得篩選條件
            $filters = $request->only(['status', 'start_date', 'end_date']);

            // 取得每頁筆數
            $per_page = $request->input('per_page', 10);

            // 取得訂單列表
            $orders = $this->order_service->getOrderList($user_id, $filters, $per_page);

            return response()->json([
                'message' => '取得訂單列表成功',
                'data' => OrderResource::collection($orders),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得訂單列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 建立訂單
     *
     * @param CreateOrderRequest $request
     * @return JsonResponse
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            // 取得會員 ID
            $user_id = $request->user()->id;

            // 取得驗證後的收件資訊
            $shipping_data = $request->validated();

            // 建立訂單
            $order = $this->order_service->createOrder($user_id, $shipping_data);

            // 取得時間軸
            $timeline = $this->order_service->getOrderTimeline($order);

            return response()->json([
                'message' => '訂單建立成功',
                'data' => (new OrderResource($order))->withTimeline($timeline),
            ], 201);

        } catch (InvalidArgumentException $e) {
            // 嘗試解析 JSON 錯誤訊息（庫存不足等）
            $error_data = json_decode($e->getMessage(), true);

            if ($error_data && isset($error_data['message'])) {
                return response()->json([
                    'message' => $error_data['message'],
                    'errors' => $error_data['items'] ?? null,
                ], 422);
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '建立訂單失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得訂單詳情
     *
     * @param Request $request
     * @param int $id 訂單 ID
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // 取得會員 ID
            $user_id = $request->user()->id;

            // 取得訂單詳情
            $order = $this->order_service->getOrderDetail($id, $user_id);

            // 取得時間軸
            $timeline = $this->order_service->getOrderTimeline($order);

            return response()->json([
                'message' => '取得訂單詳情成功',
                'data' => (new OrderResource($order))->withTimeline($timeline),
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得訂單詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取消訂單
     *
     * @param Request $request
     * @param int $id 訂單 ID
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            // 取得會員 ID
            $user_id = $request->user()->id;

            // 取消訂單
            $order = $this->order_service->cancelOrder($id, $user_id);

            return response()->json([
                'message' => '訂單已取消',
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'cancelled_at' => $order->cancelled_at->toIso8601String(),
                ],
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取消訂單失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得會員訂單統計
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            // 取得會員 ID
            $user_id = $request->user()->id;

            // 取得訂單統計
            $stats = $this->order_service->getUserOrderStats($user_id);

            return response()->json([
                'message' => '取得訂單統計成功',
                'data' => $stats,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得訂單統計失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
