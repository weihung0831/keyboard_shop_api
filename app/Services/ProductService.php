<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 產品服務層
 * 處理產品相關商業邏輯
 */
class ProductService
{
    /**
     * 產品資料存取層
     */
    private ProductRepository $product_repository;

    /**
     * 建構函式
     */
    public function __construct(ProductRepository $product_repository)
    {
        $this->product_repository = $product_repository;
    }

    /**
     * 取得產品列表（支援分頁、搜尋、篩選）
     *
     * @param  array  $filters  篩選條件
     */
    public function getProductList(array $filters = []): LengthAwarePaginator
    {
        // 建立查詢
        $query = Product::query()
            ->with(['category', 'images', 'specifications'])
            ->active();

        // 關鍵字搜尋（支援 search 或 keyword 參數）
        $searchKeyword = $filters['search'] ?? $filters['keyword'] ?? null;
        if (! empty($searchKeyword)) {
            $query->search($searchKeyword);
        }

        // 分類篩選
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // 價格區間篩選
        if (! empty($filters['min_price']) || ! empty($filters['max_price'])) {
            $query->priceRange(
                $filters['min_price'] ?? null,
                $filters['max_price'] ?? null
            );
        }

        // 排序
        $sort = $filters['sort'] ?? 'created_at_desc';
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_at_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // 分頁（預設每頁 12 筆，最大 50 筆）
        $perPage = $filters['per_page'] ?? 12;
        $perPage = min($perPage, 50); // 限制最大值為 50

        return $query->paginate($perPage);
    }

    /**
     * 取得單一產品詳情（by ID）
     *
     * @param  int  $id  產品 ID
     *
     * @throws ModelNotFoundException
     */
    public function getProductById(int $id): Product
    {
        return Product::with(['category', 'images', 'specifications'])
            ->active()
            ->findOrFail($id);
    }

    /**
     * 取得單一產品詳情（by slug）
     *
     * @param  string  $slug  產品 slug
     *
     * @throws ModelNotFoundException
     */
    public function getProductBySlug(string $slug): Product
    {
        return Product::with(['category', 'images', 'specifications'])
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * 取得搜尋建議
     *
     * @param  string  $keyword  搜尋關鍵字（至少 2 字元）
     * @param  int  $limit  回傳筆數（預設 10 筆）
     */
    public function getSearchSuggestions(string $keyword, int $limit = 10): \Illuminate\Support\Collection
    {
        // 確保關鍵字長度至少 2 字元
        if (mb_strlen($keyword) < 2) {
            return collect([]);
        }

        // 限制最多回傳 10 筆
        $limit = min($limit, 10);

        // 查詢產品（只取必要欄位）
        return Product::query()
            ->select(['id', 'name', 'slug', 'price'])
            ->with(['images' => function ($query) {
                // 只取主圖
                $query->where('is_primary', true)
                    ->select(['id', 'product_id', 'image_url']);
            }])
            ->active()
            ->search($keyword)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                // 轉換為建議格式
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'image_url' => $product->images->first()?->image_url,
                    'price' => (float) $product->price,
                ];
            });
    }
}
