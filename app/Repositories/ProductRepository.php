<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * 商品資料存取層
 * 只處理資料庫操作
 */
class ProductRepository
{
    /**
     * 取得商品列表（支援分頁、搜尋、篩選、排序）
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getFilteredProducts(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::with(['category', 'images'])
            ->where('is_active', true);

        // 關鍵字搜尋
        if (!empty($filters['search'])) {
            $keyword = $filters['search'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // 分類篩選
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // 價格區間篩選
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // 排序
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate($perPage);
    }

    /**
     * 根據 ID 查詢商品
     *
     * @param int $id
     * @param bool $activeOnly
     * @return Product|null
     */
    public function findById(int $id, bool $activeOnly = true): ?Product
    {
        $query = Product::with(['category', 'images']);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->find($id);
    }

    /**
     * 根據 Slug 查詢商品
     *
     * @param string $slug
     * @param bool $activeOnly
     * @return Product|null
     */
    public function findBySlug(string $slug, bool $activeOnly = true): ?Product
    {
        $query = Product::with(['category', 'images'])
            ->where('slug', $slug);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->first();
    }

    /**
     * 取得搜尋建議
     *
     * @param string $keyword
     * @param int $limit
     * @return Collection
     */
    public function getSearchSuggestions(string $keyword, int $limit = 10): Collection
    {
        return Product::with(['images' => function ($query) {
                $query->where('is_primary', true);
            }])
            ->where('is_active', true)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
