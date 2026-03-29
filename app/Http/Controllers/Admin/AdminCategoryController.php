<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\EscapesLikeWildcards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 管理員分類控制器
 * 處理分類 CRUD（包含停用分類）
 */
class AdminCategoryController extends Controller
{
    use EscapesLikeWildcards;

    /**
     * 取得分類列表（含停用，附產品數量）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductCategory::query()->withCount('products');

            // 搜尋分類名稱（跳脫 LIKE 萬用字元）
            if ($request->filled('search')) {
                $keyword = $this->escapeLike($request->input('search'));
                $query->where('name', 'like', "%{$keyword}%");
            }

            $query->orderBy('sort_order')->orderBy('name');

            $per_page = min((int) $request->input('per_page', 15), 100);
            $categories = $query->paginate($per_page);

            return response()->json([
                'message' => '取得分類列表成功',
                'data' => $categories->map(fn ($cat) => $this->formatCategory($cat)),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'last_page' => $categories->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得分類列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 新增分類（若未提供 slug 則自動產生）
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // 未提供 slug 時，依名稱自動產生（確保唯一）
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }

            // is_active 預設為 true
            $data['is_active'] = $data['is_active'] ?? true;

            $category = ProductCategory::create($data);
            $category->loadCount('products');

            return response()->json([
                'message' => '分類建立成功',
                'data' => $this->formatCategory($category),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '建立分類失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新分類
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = ProductCategory::find($id);

            if (! $category) {
                return response()->json(['message' => '分類不存在'], 404);
            }

            $data = $request->validated();

            // 未提供 slug 時，依名稱自動產生（確保唯一，排除自身）
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $id);
            }

            $category->update($data);
            $category->loadCount('products');

            return response()->json([
                'message' => '分類更新成功',
                'data' => $this->formatCategory($category),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '更新分類失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除分類（若有關聯產品則拒絕）
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $category = ProductCategory::withCount('products')->find($id);

            if (! $category) {
                return response()->json(['message' => '分類不存在'], 404);
            }

            if ($category->products_count > 0) {
                return response()->json([
                    'message' => "此分類下有 {$category->products_count} 個產品，無法刪除",
                ], 422);
            }

            $category->delete();

            return response()->json([
                'message' => '分類已刪除',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '刪除分類失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 產生唯一 slug
     *
     * @param  int|null  $exclude_id  更新時排除自身 ID
     */
    private function generateUniqueSlug(string $name, ?int $exclude_id = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = ProductCategory::where('slug', $slug);
            if ($exclude_id !== null) {
                $query->where('id', '!=', $exclude_id);
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
     * 格式化分類資料為回應陣列
     *
     * @return array<string, mixed>
     */
    private function formatCategory(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'products_count' => $category->products_count ?? 0,
            'created_at' => $category->created_at->toIso8601String(),
            'updated_at' => $category->updated_at->toIso8601String(),
        ];
    }

}
