<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 分類服務層
 * 處理產品分類相關商業邏輯
 */
class CategoryService
{
    /**
     * 分類資料存取層
     */
    private CategoryRepository $category_repository;

    /**
     * 建構函式
     */
    public function __construct(CategoryRepository $category_repository)
    {
        $this->category_repository = $category_repository;
    }

    /**
     * 取得所有啟用的分類列表（依排序順序）
     *
     * @param bool $withCount 是否載入產品數量
     * @return Collection
     */
    public function getCategoryList(bool $withCount = false): Collection
    {
        // 建立查詢
        $query = ProductCategory::active()->ordered();

        // 如果需要產品數量，載入關聯計數
        if ($withCount) {
            $query->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }]);
        }

        return $query->get();
    }

    /**
     * 取得單一分類詳情（by ID）
     *
     * @param int $id 分類 ID
     * @return ProductCategory
     * @throws ModelNotFoundException
     */
    public function getCategoryById(int $id): ProductCategory
    {
        return ProductCategory::active()
            ->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->findOrFail($id);
    }

    /**
     * 取得單一分類詳情（by slug）
     *
     * @param string $slug 分類 slug
     * @return ProductCategory
     * @throws ModelNotFoundException
     */
    public function getCategoryBySlug(string $slug): ProductCategory
    {
        return ProductCategory::active()
            ->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
