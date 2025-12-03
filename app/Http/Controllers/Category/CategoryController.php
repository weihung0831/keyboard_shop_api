<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * 分類控制器
 * 處理產品分類相關 HTTP 請求
 */
class CategoryController extends Controller
{
    /**
     * 分類服務
     */
    private CategoryService $category_service;

    /**
     * 建構函式
     */
    public function __construct(CategoryService $category_service)
    {
        $this->category_service = $category_service;
    }

    /**
     * 取得所有分類列表
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // 取得分類列表（包含產品數量）
            $categories = $this->category_service->getCategoryList(true);

            // 回傳成功回應
            return response()->json([
                'message' => '取得分類列表成功',
                'data' => ProductCategoryResource::collection($categories),
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '取得分類列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得單一分類詳情（by ID 或 slug）
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
                $category = $this->category_service->getCategoryById((int) $idOrSlug);
            } else {
                // 非數字，視為 slug
                $category = $this->category_service->getCategoryBySlug($idOrSlug);
            }

            // 回傳成功回應
            return response()->json([
                'message' => '取得分類詳情成功',
                'data' => new ProductCategoryResource($category),
            ], 200);
        } catch (ModelNotFoundException $e) {
            // 分類不存在
            return response()->json([
                'message' => '找不到該分類',
            ], 404);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '取得分類詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
