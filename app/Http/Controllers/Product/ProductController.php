<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 產品控制器
 * 處理產品相關 HTTP 請求
 */
class ProductController extends Controller
{
    /**
     * 產品服務
     */
    private ProductService $product_service;

    /**
     * 建構函式
     */
    public function __construct(ProductService $product_service)
    {
        $this->product_service = $product_service;
    }

    /**
     * 取得產品列表（支援分頁、搜尋、篩選）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 取得篩選條件
            $filters = $request->only([
                'search',
                'keyword',
                'category_id',
                'min_price',
                'max_price',
                'sort',
                'per_page',
            ]);

            // 取得產品列表
            $products = $this->product_service->getProductList($filters);

            // 回傳成功回應
            return response()->json([
                'message' => '取得產品列表成功',
                'data' => ProductResource::collection($products),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '取得產品列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得單一產品詳情（by ID 或 slug）
     *
     * @param string $idOrSlug
     * @return JsonResponse
     */
    public function show(string $idOrSlug): JsonResponse
    {
        try {
            // 判斷是 ID 還是 slug
            if (is_numeric($idOrSlug)) {
                // 數字，視為 ID
                $product = $this->product_service->getProductById((int) $idOrSlug);
            } else {
                // 非數字，視為 slug
                $product = $this->product_service->getProductBySlug($idOrSlug);
            }

            // 回傳成功回應
            return response()->json([
                'message' => '取得產品詳情成功',
                'data' => new ProductResource($product),
            ], 200);
        } catch (ModelNotFoundException $e) {
            // 產品不存在
            return response()->json([
                'message' => '找不到該商品',
            ], 404);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '取得產品詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得搜尋建議
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        try {
            // 取得搜尋關鍵字
            $keyword = $request->input('q', '');

            // 驗證關鍵字長度（至少 2 字元）
            if (mb_strlen($keyword) < 2) {
                return response()->json([
                    'message' => '搜尋關鍵字至少需要 2 個字元',
                    'errors' => [
                        'q' => ['搜尋關鍵字至少需要 2 個字元'],
                    ],
                ], 422);
            }

            // 取得搜尋建議
            $suggestions = $this->product_service->getSearchSuggestions($keyword);

            // 回傳成功回應
            return response()->json([
                'suggestions' => $suggestions,
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '取得搜尋建議失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
