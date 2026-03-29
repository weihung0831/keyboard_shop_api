<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理後台分類管理測試
 * 測試 /api/v1/admin/categories 端點的 CRUD 操作
 */
class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * TC-CAT-001: POST /admin/categories（完整資料）→ 201
     * 驗證可以成功建立新分類
     *
     * @test
     */
    public function can_create_category_with_complete_data(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Mechanical Keyboards',
                'slug' => 'mechanical-keyboards',
                'description' => 'High-quality mechanical keyboards',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'is_active',
                    'sort_order',
                    'products_count',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.name', 'Mechanical Keyboards')
            ->assertJsonPath('data.slug', 'mechanical-keyboards')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Mechanical Keyboards',
            'slug' => 'mechanical-keyboards',
        ]);
    }

    /**
     * TC-CAT-002: POST /admin/categories（自動產生 slug）→ 201
     * 驗證未提供 slug 時，自動依名稱產生
     *
     * @test
     */
    public function can_create_category_with_auto_generated_slug(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Mechanical Keyboards',
                'description' => 'Some description',
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'mechanical-keyboards');

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Mechanical Keyboards',
            'slug' => 'mechanical-keyboards',
        ]);
    }

    /**
     * TC-CAT-003: POST /admin/categories（重複 slug）→ 422
     * 驗證重複的 slug 會被拒絕
     *
     * @test
     */
    public function cannot_create_category_with_duplicate_slug(): void
    {
        // Arrange: 建立既有分類
        ProductCategory::factory()->create(['slug' => 'mechanical-keyboards']);

        // Act: 嘗試建立相同 slug 的分類
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Another Keyboards',
                'slug' => 'mechanical-keyboards',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * TC-CAT-004: POST /admin/categories（缺少名稱）→ 422
     * 驗證必填欄位驗證
     *
     * @test
     */
    public function cannot_create_category_without_name(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'slug' => 'test-category',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * TC-CAT-005: PUT /admin/categories/{id} → 200 更新成功
     * 驗證可以更新分類資訊
     *
     * @test
     */
    public function can_update_category(): void
    {
        // Arrange: 建立分類
        $category = ProductCategory::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'New Name',
                'slug' => 'new-name',
                'is_active' => false,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'new-name')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    /**
     * TC-CAT-006: PUT /admin/categories/{id}（自動產生 slug）→ 200
     * 驗證更新時未提供 slug 會自動產生
     *
     * @test
     */
    public function can_update_category_with_auto_generated_slug(): void
    {
        // Arrange
        $category = ProductCategory::factory()->create();

        // Act: 不提供 slug
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'updated-category');
    }

    /**
     * TC-CAT-007: PUT /admin/categories/{id}（不存在的 ID）→ 404
     * 驗證更新不存在的分類會返回 404
     *
     * @test
     */
    public function cannot_update_nonexistent_category(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/categories/99999', [
                'name' => 'Some Name',
            ]);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-CAT-008: DELETE /admin/categories/{id}（無關聯商品）→ 200
     * 驗證可以刪除沒有關聯商品的分類
     *
     * @test
     */
    public function can_delete_empty_category(): void
    {
        // Arrange: 建立空的分類（無商品）
        $category = ProductCategory::factory()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/categories/{$category->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '分類已刪除');

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    /**
     * TC-CAT-009: DELETE /admin/categories/{id}（有關聯商品）→ 422
     * 驗證無法刪除有關聯商品的分類
     *
     * @test
     */
    public function cannot_delete_category_with_products(): void
    {
        // Arrange: 建立分類並關聯商品
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/categories/{$category->id}");

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', '此分類下有 1 個產品，無法刪除');

        // 驗證分類仍存在
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    /**
     * TC-CAT-010: DELETE /admin/categories/{id}（不存在的 ID）→ 404
     *
     * @test
     */
    public function cannot_delete_nonexistent_category(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/v1/admin/categories/99999');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-CAT-011: GET /admin/categories → 列表成功
     * 驗證可以取得分類列表及商品數量
     *
     * @test
     */
    public function can_fetch_categories_list(): void
    {
        // Arrange: 建立分類及關聯商品
        $category1 = ProductCategory::factory()->create();
        Product::factory(3)->create(['category_id' => $category1->id]);

        $category2 = ProductCategory::factory()->create();
        Product::factory(1)->create(['category_id' => $category2->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/categories');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'products_count',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /**
     * TC-CAT-012: GET /admin/categories?search=keywords → 搜尋功能
     * 驗證可以按名稱搜尋分類
     *
     * @test
     */
    public function can_search_categories_by_name(): void
    {
        // Arrange
        ProductCategory::factory()->create(['name' => 'Mechanical Keyboards']);
        ProductCategory::factory()->create(['name' => 'Keycaps']);
        ProductCategory::factory()->create(['name' => 'Switches']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/categories?search=mechanical');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Mechanical Keyboards', $data[0]['name']);
    }

    /**
     * TC-CAT-013: POST /admin/categories（name 超過 255 字元）→ 422
     * 驗證欄位長度限制
     *
     * @test
     */
    public function cannot_create_category_with_long_name(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => str_repeat('a', 256),
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * TC-CAT-014: POST /admin/categories（is_active 不是布林值）→ 422
     * 驗證布林欄位驗證
     *
     * @test
     */
    public function cannot_create_category_with_invalid_is_active(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Test Category',
                'is_active' => 'yes', // 應為 true/false
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    /**
     * TC-CAT-015: is_active 預設為 true
     * 驗證建立分類時若未提供 is_active，預設為 true
     *
     * @test
     */
    public function is_active_defaults_to_true(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Test Category',
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.is_active', true);
    }

    /**
     * TC-CAT-016: GET /admin/categories 按 sort_order 排序
     * 驗證分類列表按 sort_order 排序
     *
     * @test
     */
    public function categories_list_is_sorted_by_sort_order(): void
    {
        // Arrange
        ProductCategory::factory()->create(['name' => 'First', 'sort_order' => 1]);
        ProductCategory::factory()->create(['name' => 'Third', 'sort_order' => 3]);
        ProductCategory::factory()->create(['name' => 'Second', 'sort_order' => 2]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/categories');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('First', $data[0]['name']);
        $this->assertEquals('Second', $data[1]['name']);
        $this->assertEquals('Third', $data[2]['name']);
    }

    /**
     * TC-CAT-017: 普通使用者可存取 GET 分類（當前設計）
     * 注意：GET /admin/categories 未驗證管理員權限（只有修改操作驗證）
     * 未來應添加權限檢查以限制非管理員存取
     *
     * @test
     */
    public function regular_user_can_currently_access_admin_categories_index(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/categories');

        // Assert: 當前設計允許任何認證使用者存取 GET
        $response->assertStatus(200);
    }

    /**
     * TC-CAT-018: 未認證使用者無法存取
     *
     * @test
     */
    public function unauthenticated_user_cannot_access_admin_categories(): void
    {
        // Act
        $response = $this->getJson('/api/v1/admin/categories');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-CAT-019: 更新時 slug 唯一性檢驗排除自身
     * 驗證更新分類時，slug 唯一性檢驗應排除自身
     *
     * @test
     */
    public function can_update_category_keeping_same_slug(): void
    {
        // Arrange
        $category = ProductCategory::factory()->create(['slug' => 'test-slug']);

        // Act: 更新其他欄位但保持相同 slug
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Name',
                'slug' => 'test-slug',
            ]);

        // Assert: 應成功
        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'test-slug');
    }

    /**
     * TC-CAT-020: GET /admin/categories 分頁
     * 驗證分類列表支援分頁
     *
     * @test
     */
    public function categories_list_supports_pagination(): void
    {
        // Arrange: 建立 20 個分類
        ProductCategory::factory(20)->create();

        // Act: 請求第一頁，每頁 10 筆
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/categories?per_page=10');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 20);

        $data = $response->json('data');
        $this->assertCount(10, $data);
    }

    /**
     * TC-CAT-021: 刪除分類時產品計數正確
     * 驗證刪除分類時，檢查關聯產品數量
     *
     * @test
     */
    public function delete_category_checks_product_count_accurately(): void
    {
        // Arrange: 建立分類及多個商品
        $category = ProductCategory::factory()->create();
        Product::factory(5)->create(['category_id' => $category->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/categories/{$category->id}");

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', '此分類下有 5 個產品，無法刪除');
    }

    /**
     * TC-CAT-022: POST /admin/categories 多個分類同時建立
     * 驗證可以連續建立多個不同的分類
     *
     * @test
     */
    public function can_create_multiple_categories_in_sequence(): void
    {
        // Act: 建立第一個分類
        $response1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Category 1',
            ]);

        // 建立第二個分類
        $response2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Category 2',
            ]);

        // Assert
        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertCount(2, ProductCategory::all());
    }
}
