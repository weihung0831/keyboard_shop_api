<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\EscapesLikeWildcards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchToggleProductRequest;
use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 管理員商品控制器
 * 處理商品的 CRUD、批次操作、圖片管理
 */
class AdminProductController extends Controller
{
    use EscapesLikeWildcards;

    /**
     * 取得所有商品列表（含未上架，支援搜尋與篩選）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::query()->with(['category', 'images']);

            if ($request->filled('search')) {
                $escaped = $this->escapeLike($request->input('search'));
                $query->where(function ($q) use ($escaped) {
                    $q->where('name', 'like', "%{$escaped}%")
                        ->orWhere('sku', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%");
                });
            }

            // 篩選是否啟用
            if ($request->has('is_active')) {
                $query->where('is_active', (bool) $request->input('is_active'));
            }

            // 篩選分類
            if ($categoryId = $request->input('category_id')) {
                $query->where('category_id', (int) $categoryId);
            }

            // 排序
            $query->orderBy('sort_order')->orderBy('created_at', 'desc');

            $perPage = min((int) $request->input('per_page', 20), 100);
            $products = $query->paginate($perPage);

            return response()->json([
                'message' => '取得商品列表成功',
                'data' => AdminProductResource::collection($products),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'last_page' => $products->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得商品列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 建立新商品（含選填規格）
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // 若未提供 slug，自動由名稱產生並確保唯一
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }

            $product = DB::transaction(function () use ($data) {
                $product = Product::create([
                    'category_id' => $data['category_id'],
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'] ?? null,
                    'content' => $data['content'] ?? null,
                    'sku' => $data['sku'],
                    'price' => $data['price'],
                    'original_price' => $data['original_price'] ?? null,
                    'stock' => $data['stock'],
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);

                // 建立規格
                if (! empty($data['specifications'])) {
                    foreach ($data['specifications'] as $index => $spec) {
                        $product->specifications()->create([
                            'spec_name' => $spec['spec_name'],
                            'spec_value' => $spec['spec_value'],
                            'sort_order' => $spec['sort_order'] ?? $index,
                        ]);
                    }
                }

                return $product;
            });

            $product->load(['category', 'specifications', 'images']);

            return response()->json([
                'message' => '商品建立成功',
                'data' => new AdminProductResource($product),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品建立失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得單一商品詳情（含規格、圖片、分類）
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = Product::with(['category', 'specifications', 'images'])->find($id);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            return response()->json([
                'message' => '取得商品詳情成功',
                'data' => new AdminProductResource($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得商品詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新商品（可同步規格）
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        try {
            $product = Product::find($id);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            $data = $request->validated();

            // 若未提供 slug，自動由名稱產生並確保唯一（忽略自身）
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $id);
            }

            DB::transaction(function () use ($product, $data) {
                $product->update([
                    'category_id' => $data['category_id'],
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'] ?? null,
                    'content' => $data['content'] ?? null,
                    'sku' => $data['sku'],
                    'price' => $data['price'],
                    'original_price' => $data['original_price'] ?? null,
                    'stock' => $data['stock'],
                    'is_active' => $data['is_active'] ?? $product->is_active,
                    'sort_order' => $data['sort_order'] ?? $product->sort_order,
                ]);

                // 若提供規格，全量同步（刪除舊的，重建新的）
                if (array_key_exists('specifications', $data)) {
                    $product->specifications()->delete();

                    if (! empty($data['specifications'])) {
                        foreach ($data['specifications'] as $index => $spec) {
                            $product->specifications()->create([
                                'spec_name' => $spec['spec_name'],
                                'spec_value' => $spec['spec_value'],
                                'sort_order' => $spec['sort_order'] ?? $index,
                            ]);
                        }
                    }
                }
            });

            $product->load(['category', 'specifications', 'images']);

            return response()->json([
                'message' => '商品更新成功',
                'data' => new AdminProductResource($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品更新失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除商品（若有訂單項目則拒絕）
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $product = Product::find($id);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            // 檢查是否有訂單項目引用此商品
            $hasOrderItems = DB::table('order_items')->where('product_id', $id)->exists();
            if ($hasOrderItems) {
                return response()->json([
                    'message' => '此商品已有訂單記錄，無法刪除',
                ], 422);
            }

            // 刪除所有圖片檔案
            $product->images->each(function (ProductImage $image) {
                $this->deleteImageFile($image->image_url);
            });

            // 刪除商品（規格由 DB cascade 處理）
            $product->delete();

            return response()->json([
                'message' => '商品刪除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '商品刪除失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 批次切換商品啟用狀態
     */
    public function batchToggle(BatchToggleProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            Product::whereIn('id', $data['product_ids'])
                ->update(['is_active' => $data['is_active']]);

            $status = $data['is_active'] ? '上架' : '下架';

            return response()->json([
                'message' => "已批次{$status} ".count($data['product_ids']).' 個商品',
                'data' => [
                    'affected_count' => count($data['product_ids']),
                    'is_active' => $data['is_active'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '批次操作失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得低庫存商品列表
     */
    public function lowStock(Request $request): JsonResponse
    {
        try {
            $threshold = max(0, (int) $request->input('threshold', 10));

            $products = Product::with(['category'])
                ->where('stock', '<', $threshold)
                ->orderBy('stock')
                ->get();

            return response()->json([
                'message' => '取得低庫存商品成功',
                'data' => AdminProductResource::collection($products),
                'meta' => [
                    'threshold' => $threshold,
                    'total' => $products->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得低庫存商品失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上傳商品圖片
     */
    public function uploadImages(StoreProductImageRequest $request, int $id): JsonResponse
    {
        try {
            $product = Product::with('images')->find($id);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            $uploadedImages = collect();
            $hasPrimary = $product->images->where('is_primary', true)->isNotEmpty();
            $sortOrder = $product->images()->count();

            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('products', 'public');
                $imageUrl = Storage::disk('public')->url($path);

                $image = $product->images()->create([
                    'image_url' => $imageUrl,
                    'is_primary' => ! $hasPrimary && $index === 0,
                    'sort_order' => $sortOrder + $index,
                ]);

                $uploadedImages->push($image);
            }

            return response()->json([
                'message' => '圖片上傳成功',
                'data' => $uploadedImages->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => str_starts_with($img->image_url, 'http') ? $img->image_url : url($img->image_url),
                    'is_primary' => $img->is_primary,
                    'sort_order' => $img->sort_order,
                ]),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '圖片上傳失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除商品圖片
     */
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        try {
            $product = Product::find($id);

            if (! $product) {
                return response()->json(['message' => '找不到該商品'], 404);
            }

            $image = ProductImage::where('product_id', $id)->where('id', $imageId)->first();

            if (! $image) {
                return response()->json(['message' => '找不到該圖片'], 404);
            }

            // 刪除磁碟上的圖片檔案
            $this->deleteImageFile($image->image_url);

            $wasPrimary = $image->is_primary;
            $image->delete();

            // 若刪除的是主圖，自動將第一張圖設為主圖
            if ($wasPrimary) {
                $firstImage = $product->images()->orderBy('sort_order')->first();
                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            return response()->json([
                'message' => '圖片刪除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '圖片刪除失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 產生唯一 slug（若已存在則加上流水號）
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = Product::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            if (! $query->exists()) {
                break;
            }
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * 從 public disk 刪除圖片檔案
     */
    private function deleteImageFile(string $imageUrl): void
    {
        $publicUrl = Storage::disk('public')->url('');
        if (str_starts_with($imageUrl, $publicUrl)) {
            $relativePath = substr($imageUrl, strlen($publicUrl));
            Storage::disk('public')->delete($relativePath);
        }
    }
}
