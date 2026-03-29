<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductSpecRequest;
use App\Http\Resources\Admin\ProductSpecificationResource;
use App\Models\Product;
use App\Models\ProductSpecification;
use Illuminate\Http\JsonResponse;

/**
 * 管理員商品規格控制器
 * 處理單一商品的規格 CRUD 操作
 */
class AdminProductSpecController extends Controller
{
    /**
     * 取得商品的所有規格
     */
    public function index(int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            $specs = $product->specifications()->orderBy('sort_order')->get();

            return response()->json([
                'message' => '取得商品規格成功',
                'data' => ProductSpecificationResource::collection($specs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得商品規格失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 新增商品規格
     */
    public function store(StoreProductSpecRequest $request, int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            $data = $request->validated();

            $spec = $product->specifications()->create([
                'spec_name' => $data['spec_name'],
                'spec_value' => $data['spec_value'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            return response()->json([
                'message' => '商品規格新增成功',
                'data' => new ProductSpecificationResource($spec),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品規格新增失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新商品規格（驗證規格屬於指定商品）
     */
    public function update(StoreProductSpecRequest $request, int $productId, int $specId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            // 確認規格屬於此商品
            $spec = ProductSpecification::where('product_id', $productId)->where('id', $specId)->first();

            if (! $spec) {
                return response()->json(['message' => '找不到該規格'], 404);
            }

            $data = $request->validated();

            $spec->update([
                'spec_name' => $data['spec_name'],
                'spec_value' => $data['spec_value'],
                'sort_order' => $data['sort_order'] ?? $spec->sort_order,
            ]);

            return response()->json([
                'message' => '商品規格更新成功',
                'data' => new ProductSpecificationResource($spec->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品規格更新失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除商品規格（驗證規格屬於指定商品）
     */
    public function destroy(int $productId, int $specId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            // 確認規格屬於此商品
            $spec = ProductSpecification::where('product_id', $productId)->where('id', $specId)->first();

            if (! $spec) {
                return response()->json(['message' => '找不到該規格'], 404);
            }

            $spec->delete();

            return response()->json([
                'message' => '商品規格刪除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品規格刪除失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
