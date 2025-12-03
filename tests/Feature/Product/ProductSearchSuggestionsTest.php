<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 產品搜尋建議測試
 */
class ProductSearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-SEARCH-001: 可以取得搜尋建議
     *
     * @test
     */
    public function can_get_search_suggestions()
    {
        // Arrange: 建立測試分類和產品
        $category = ProductCategory::factory()->create();

        // 建立包含 "Keychron" 關鍵字的產品（確保時間順序）
        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2 機械鍵盤',
            'slug' => 'keychron-k2',
            'price' => 2990.00,
            'is_active' => true,
            'created_at' => now()->subMinute(), // 較早創建
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K8 機械鍵盤',
            'slug' => 'keychron-k8',
            'price' => 3490.00,
            'is_active' => true,
            'created_at' => now(), // 較晚創建
        ]);

        // 建立主圖
        ProductImage::factory()->create([
            'product_id' => $product1->id,
            'image_url' => '/storage/products/k2.jpg',
            'is_primary' => true,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product2->id,
            'image_url' => '/storage/products/k8.jpg',
            'is_primary' => true,
        ]);

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Key');

        // Assert: 驗證回應
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'suggestions' => [
                '*' => ['id', 'name', 'slug', 'image_url', 'price'],
            ],
        ]);

        // 驗證包含兩個產品
        $this->assertCount(2, $response->json('suggestions'));

        // 驗證資料格式
        $suggestions = $response->json('suggestions');
        $this->assertEquals($product2->id, $suggestions[0]['id']); // 最新的在前面
        $this->assertEquals('Keychron K8 機械鍵盤', $suggestions[0]['name']);
        $this->assertEquals('keychron-k8', $suggestions[0]['slug']);
        $this->assertEquals('/storage/products/k8.jpg', $suggestions[0]['image_url']);
        $this->assertEquals(3490.00, $suggestions[0]['price']);
    }

    /**
     * TC-SEARCH-002: 搜尋關鍵字至少需要 2 個字元
     *
     * @test
     */
    public function validates_minimum_keyword_length()
    {
        // Act: 發送只有 1 字元的搜尋請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=K');

        // Assert: 驗證回應
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    /**
     * TC-SEARCH-003: 沒有匹配的產品回傳空陣列
     *
     * @test
     */
    public function returns_empty_array_when_no_matches()
    {
        // Arrange: 建立測試產品（不包含搜尋關鍵字）
        $category = ProductCategory::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Logitech MX Keys',
            'is_active' => true,
        ]);

        // Act: 搜尋不存在的關鍵字
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 驗證回應
        $response->assertStatus(200);
        $response->assertJson(['suggestions' => []]);
    }

    /**
     * TC-SEARCH-004: 只回傳啟用的產品
     *
     * @test
     */
    public function only_returns_active_products()
    {
        // Arrange: 建立啟用和停用的產品
        $category = ProductCategory::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2 機械鍵盤',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K3 機械鍵盤',
            'is_active' => false, // 停用
        ]);

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 只回傳 1 個啟用的產品
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('suggestions'));
        $this->assertEquals('Keychron K2 機械鍵盤', $response->json('suggestions.0.name'));
    }

    /**
     * TC-SEARCH-005: 最多回傳 10 筆建議
     *
     * @test
     */
    public function limits_suggestions_to_10_items()
    {
        // Arrange: 建立 15 個產品
        $category = ProductCategory::factory()->create();

        for ($i = 1; $i <= 15; $i++) {
            Product::factory()->create([
                'category_id' => $category->id,
                'name' => "Keychron K{$i} 機械鍵盤",
                'is_active' => true,
            ]);
        }

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 最多回傳 10 筆
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('suggestions'));
    }

    /**
     * TC-SEARCH-006: 支援描述欄位搜尋
     *
     * @test
     */
    public function searches_in_description_field()
    {
        // Arrange: 建立產品（關鍵字只在描述中）
        $category = ProductCategory::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Premium Keyboard',
            'description' => 'Keychron Red Switch',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Standard Keyboard',
            'description' => 'Blue Switch',
            'is_active' => true,
        ]);

        // Act: 搜尋只在描述中的關鍵字
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 驗證回應（能找到描述中包含關鍵字的產品）
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('suggestions'));
        $this->assertEquals('Premium Keyboard', $response->json('suggestions.0.name'));
    }

    /**
     * TC-SEARCH-007: 產品沒有圖片時 image_url 為 null
     *
     * @test
     */
    public function returns_null_image_url_when_no_images()
    {
        // Arrange: 建立沒有圖片的產品
        $category = ProductCategory::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2 機械鍵盤',
            'is_active' => true,
        ]);
        // 不建立圖片

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: image_url 應該為 null
        $response->assertStatus(200);
        $this->assertNull($response->json('suggestions.0.image_url'));
    }

    /**
     * TC-SEARCH-008: 只回傳主圖
     *
     * @test
     */
    public function only_returns_primary_image()
    {
        // Arrange: 建立有多張圖片的產品
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2 機械鍵盤',
            'is_active' => true,
        ]);

        // 建立多張圖片
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => '/storage/products/k2-secondary.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => '/storage/products/k2-primary.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 只回傳主圖
        $response->assertStatus(200);
        $this->assertEquals('/storage/products/k2-primary.jpg', $response->json('suggestions.0.image_url'));
    }

    /**
     * TC-SEARCH-009: 搜尋關鍵字缺少時回傳驗證錯誤
     *
     * @test
     */
    public function validates_missing_keyword()
    {
        // Act: 沒有提供搜尋關鍵字
        $response = $this->getJson('/api/v1/products/search/suggestions');

        // Assert: 驗證回應
        $response->assertStatus(422);
    }

    /**
     * TC-SEARCH-010: 搜尋結果依建立時間排序（最新在前）
     *
     * @test
     */
    public function results_ordered_by_created_at_desc()
    {
        // Arrange: 建立產品（有時間順序）
        $category = ProductCategory::factory()->create();

        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2',
            'is_active' => true,
            'created_at' => now()->subDays(2),
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K8',
            'is_active' => true,
            'created_at' => now()->subDay(),
        ]);

        $product3 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron Q1',
            'is_active' => true,
            'created_at' => now(),
        ]);

        // Act: 發送搜尋建議請求
        $response = $this->getJson('/api/v1/products/search/suggestions?q=Keychron');

        // Assert: 驗證排序（最新在前）
        $response->assertStatus(200);
        $suggestions = $response->json('suggestions');
        $this->assertEquals($product3->id, $suggestions[0]['id']); // 最新
        $this->assertEquals($product2->id, $suggestions[1]['id']);
        $this->assertEquals($product1->id, $suggestions[2]['id']); // 最舊
    }
}
