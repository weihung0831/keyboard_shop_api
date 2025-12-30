<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * 購物車控制器
 * 處理購物車相關 HTTP 請求
 */
class CartController extends Controller
{
    /**
     * 購物車服務
     */
    private CartService $cart_service;

    /**
     * 建構函式
     */
    public function __construct(CartService $cart_service)
    {
        $this->cart_service = $cart_service;
    }

    /**
     * 取得購物車
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 取得使用者識別資訊（手動嘗試 Sanctum 認證）
            $user_id = Auth::guard('sanctum')->user()?->id;
            $session_id = $request->header('X-Session-Id');

            // 驗證至少有一個識別參數
            if ($user_id === null && empty($session_id)) {
                return response()->json([
                    'message' => '購物車是空的',
                    'data' => [
                        'id' => null,
                        'items' => [],
                        'total_items' => 0,
                        'total_amount' => 0,
                    ],
                ], 200);
            }

            // 取得購物車
            $cart = $this->cart_service->getCart($user_id, $session_id);

            return response()->json([
                'message' => '取得購物車成功',
                'data' => new CartResource($cart),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得購物車失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 加入購物車
     *
     * @param AddToCartRequest $request
     * @return JsonResponse
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        try {
            // 取得使用者識別資訊（手動嘗試 Sanctum 認證）
            $user_id = Auth::guard('sanctum')->user()?->id;
            $session_id = $request->header('X-Session-Id');

            // 驗證至少有一個識別參數
            if ($user_id === null && empty($session_id)) {
                return response()->json([
                    'message' => '請提供 Session ID 或登入會員',
                ], 400);
            }

            // 取得驗證後的資料
            $validated = $request->validated();

            // 加入購物車
            $cart = $this->cart_service->addItem(
                $user_id,
                $session_id,
                $validated['product_id'],
                $validated['quantity']
            );

            return response()->json([
                'message' => '商品已加入購物車',
                'data' => new CartResource($cart),
            ], 201);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '加入購物車失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新購物車項目數量
     *
     * @param UpdateCartItemRequest $request
     * @param int $id 購物車項目 ID
     * @return JsonResponse
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        try {
            // 取得使用者識別資訊（手動嘗試 Sanctum 認證）
            $user_id = Auth::guard('sanctum')->user()?->id;
            $session_id = $request->header('X-Session-Id');

            // 驗證至少有一個識別參數
            if ($user_id === null && empty($session_id)) {
                return response()->json([
                    'message' => '請提供 Session ID 或登入會員',
                ], 400);
            }

            // 取得驗證後的資料
            $validated = $request->validated();

            // 更新購物車項目
            $cart = $this->cart_service->updateItemQuantity(
                $id,
                $validated['quantity'],
                $user_id,
                $session_id
            );

            return response()->json([
                'message' => '購物車已更新',
                'data' => new CartResource($cart),
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '更新購物車失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 移除購物車項目
     *
     * @param Request $request
     * @param int $id 購物車項目 ID
     * @return JsonResponse
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        try {
            // 取得使用者識別資訊（手動嘗試 Sanctum 認證）
            $user_id = Auth::guard('sanctum')->user()?->id;
            $session_id = $request->header('X-Session-Id');

            // 驗證至少有一個識別參數
            if ($user_id === null && empty($session_id)) {
                return response()->json([
                    'message' => '請提供 Session ID 或登入會員',
                ], 400);
            }

            // 移除購物車項目
            $cart = $this->cart_service->removeItem($id, $user_id, $session_id);

            return response()->json([
                'message' => '商品已從購物車移除',
                'data' => new CartResource($cart),
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '移除商品失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 清空購物車
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            // 取得使用者識別資訊（手動嘗試 Sanctum 認證）
            $user_id = Auth::guard('sanctum')->user()?->id;
            $session_id = $request->header('X-Session-Id');

            // 驗證至少有一個識別參數
            if ($user_id === null && empty($session_id)) {
                return response()->json([
                    'message' => '請提供 Session ID 或登入會員',
                ], 400);
            }

            // 清空購物車
            $this->cart_service->clearCart($user_id, $session_id);

            return response()->json([
                'message' => '購物車已清空',
                'data' => [
                    'id' => null,
                    'items' => [],
                    'total_items' => 0,
                    'total_amount' => 0,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '清空購物車失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 合併購物車（登入時呼叫）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(Request $request): JsonResponse
    {
        try {
            // 必須登入
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'message' => '請先登入',
                ], 401);
            }

            // 取得訪客 Session ID
            $session_id = $request->header('X-Session-Id');
            if (empty($session_id)) {
                // 沒有訪客購物車，直接返回會員購物車
                $cart = $this->cart_service->getCart($user->id, null);

                return response()->json([
                    'message' => '取得購物車成功',
                    'data' => new CartResource($cart),
                ], 200);
            }

            // 合併購物車
            $cart = $this->cart_service->mergeCarts($session_id, $user->id);

            return response()->json([
                'message' => '購物車已合併',
                'data' => new CartResource($cart),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '合併購物車失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
