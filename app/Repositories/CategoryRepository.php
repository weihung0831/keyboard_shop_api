<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * 分類資料存取層
 * 只處理資料庫操作
 */
class CategoryRepository
{
    /**
     * 取得所有啟用的分類
     *
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        return ProductCategory::withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * 根據 ID 查詢分類
     *
     * @param int $id
     * @param bool $activeOnly
     * @return ProductCategory|null
     */
    public function findById(int $id, bool $activeOnly = true): ?ProductCategory
    {
        $query = ProductCategory::withCount(['products' => function ($query) {
            $query->where('is_active', true);
        }]);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->find($id);
    }

    /**
     * 根據 Slug 查詢分類
     *
     * @param string $slug
     * @param bool $activeOnly
     * @return ProductCategory|null
     */
    public function findBySlug(string $slug, bool $activeOnly = true): ?ProductCategory
    {
        $query = ProductCategory::withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->where('slug', $slug);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->first();
    }
}
