<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-PROD-001: 取得商品列表
     *
     * @test
     */
    public function can_get_product_list()
    {
        // Arrange: 建立 50 筆商品
        $category = ProductCategory::factory()->create();
        Product::factory()->count(50)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/products');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'price',
                        'original_price',
                        'stock',
                        'category' => [
                            'id',
                            'name',
                        ],
                        'images' => [
                            '*' => [
                                'id',
                                'url',
                                'is_primary',
                            ],
                        ],
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        // 驗證預設分頁為 12 筆
        $this->assertCount(12, $response->json('data'));
        $this->assertEquals(12, $response->json('meta.per_page'));
        $this->assertEquals(50, $response->json('meta.total'));
    }

    /**
     * TC-PROD-002: 分頁功能
     *
     * @test
     */
    public function product_list_supports_pagination()
    {
        // Arrange: 建立 50 筆商品
        $category = ProductCategory::factory()->create();
        Product::factory()->count(50)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Act: 請求第 2 頁,每頁 20 筆
        $response = $this->getJson('/api/v1/products?page=2&per_page=20');

        // Assert
        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(20, $response->json('meta.per_page'));
        $this->assertEquals(50, $response->json('meta.total'));
        $this->assertCount(20, $response->json('data'));
    }

    /**
     * TC-PROD-003: 關鍵字搜尋
     *
     * @test
     */
    public function can_search_products_by_keyword()
    {
        // Arrange
        $category = ProductCategory::factory()->create();

        // 建立包含 "Keychron" 的商品
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2 機械鍵盤',
            'is_active' => true,
        ]);

        // 建立其他商品（明確設定 description 避免 Factory 隨機產生含 Keychron 的內容）
        Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'name' => 'HHKB Professional',
            'description' => 'HHKB 高品質靜電容鍵盤',
            'is_active' => true,
        ]);

        // Act: 搜尋 "Keychron"
        $response = $this->getJson('/api/v1/products?search=Keychron');

        // Assert: 只返回包含 Keychron 的商品（搜尋涵蓋 name 與 description）
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    /**
     * TC-PROD-004: 分類篩選
     *
     * @test
     */
    public function can_filter_products_by_category()
    {
        // Arrange
        $category1 = ProductCategory::factory()->create(['name' => '機械鍵盤']);
        $category2 = ProductCategory::factory()->create(['name' => '鍵帽']);

        Product::factory()->count(5)->create([
            'category_id' => $category1->id,
            'is_active' => true,
        ]);

        Product::factory()->count(3)->create([
            'category_id' => $category2->id,
            'is_active' => true,
        ]);

        // Act: 只篩選機械鍵盤分類
        $response = $this->getJson("/api/v1/products?category_id={$category1->id}");

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));

        foreach ($response->json('data') as $product) {
            $this->assertEquals($category1->id, $product['category']['id']);
        }
    }

    /**
     * TC-PROD-005: 價格區間篩選
     *
     * @test
     */
    public function can_filter_products_by_price_range()
    {
        // Arrange
        $category = ProductCategory::factory()->create();

        // 建立不同價格的商品
        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 500,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1500,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 2500,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 4000,
            'is_active' => true,
        ]);

        // Act: 篩選價格 1000-3000 的商品
        $response = $this->getJson('/api/v1/products?min_price=1000&max_price=3000');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));

        foreach ($response->json('data') as $product) {
            $this->assertGreaterThanOrEqual(1000, $product['price']);
            $this->assertLessThanOrEqual(3000, $product['price']);
        }
    }

    /**
     * TC-PROD-006: 價格排序
     *
     * @test
     */
    public function can_sort_products_by_price()
    {
        // Arrange
        $category = ProductCategory::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 3000,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'price' => 2000,
            'is_active' => true,
        ]);

        // Act: 價格由低至高排序
        $response = $this->getJson('/api/v1/products?sort=price_asc');

        // Assert
        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('price')->toArray();
        $this->assertEquals([1000, 2000, 3000], $prices);
    }

    /**
     * TC-PROD-007: 複合條件查詢
     *
     * @test
     */
    public function can_use_multiple_filters_together()
    {
        // Arrange
        $category1 = ProductCategory::factory()->create();
        $category2 = ProductCategory::factory()->create();

        // 分類1 的商品,價格 2500
        Product::factory()->create([
            'category_id' => $category1->id,
            'price' => 2500,
            'is_active' => true,
        ]);

        // 分類1 的商品,價格 3500
        Product::factory()->create([
            'category_id' => $category1->id,
            'price' => 3500,
            'is_active' => true,
        ]);

        // 分類2 的商品,價格 2500
        Product::factory()->create([
            'category_id' => $category2->id,
            'price' => 2500,
            'is_active' => true,
        ]);

        // Act: 分類1 + 價格 2000 以上 + 價格降序
        $response = $this->getJson("/api/v1/products?category_id={$category1->id}&min_price=2000&sort=price_desc");

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));

        // 驗證第一筆是價格較高的
        $this->assertEquals(3500, $response->json('data.0.price'));
        $this->assertEquals(2500, $response->json('data.1.price'));
    }

    /**
     * TC-PROD-008: 只顯示上架商品
     *
     * @test
     */
    public function product_list_only_shows_active_products()
    {
        // Arrange
        $category = ProductCategory::factory()->create();

        // 上架商品
        Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // 下架商品
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        // Act
        $response = $this->getJson('/api/v1/products');

        // Assert: 只返回上架的商品
        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));

        foreach ($response->json('data') as $product) {
            // 前端不應該看到 is_active 欄位,但可以驗證資料庫
            $dbProduct = Product::find($product['id']);
            $this->assertTrue($dbProduct->is_active);
        }
    }

    /**
     * TC-PROD-009: 每頁筆數限制
     *
     * @test
     */
    public function per_page_cannot_exceed_maximum()
    {
        // Arrange
        $category = ProductCategory::factory()->create();
        Product::factory()->count(100)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Act: 嘗試請求每頁 100 筆 (超過最大值 50)
        $response = $this->getJson('/api/v1/products?per_page=100');

        // Assert: 應該被限制在最大值 50
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(50, count($response->json('data')));
    }

    /**
     * TC-PROD-010: 空結果處理
     *
     * @test
     */
    public function returns_empty_array_when_no_products_match()
    {
        // Arrange: 不建立任何商品

        // Act
        $response = $this->getJson('/api/v1/products');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }
}
