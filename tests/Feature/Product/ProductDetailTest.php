<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-DETAIL-001: 取得商品詳情 (使用 ID)
     *
     * @test
     */
    public function can_get_product_detail_by_id()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'name' => '機械鍵盤',
            'slug' => 'mechanical-keyboards',
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Keychron K2',
            'slug' => 'keychron-k2',
            'description' => '75% 配置無線機械鍵盤',
            'content' => '<p>完整的商品介紹</p>',
            'sku' => 'KEY-K2-RGB-RED',
            'price' => 2990,
            'original_price' => 3490,
            'stock' => 50,
            'specifications' => [
                '軸體' => '紅軸',
                '配置' => '75%',
                '連接方式' => '藍牙/有線',
                '背光' => 'RGB',
                '熱插拔' => '支援',
            ],
            'is_active' => true,
        ]);

        // 建立商品圖片
        ProductImage::factory()->count(3)->create([
            'product_id' => $product->id,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'is_primary' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'content',
                    'sku',
                    'price',
                    'original_price',
                    'stock',
                    'category' => [
                        'id',
                        'name',
                        'slug',
                    ],
                    'images' => [
                        '*' => [
                            'id',
                            'url',
                            'is_primary',
                            'sort_order',
                        ],
                    ],
                    'specifications',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'name' => 'Keychron K2',
                    'slug' => 'keychron-k2',
                    'sku' => 'KEY-K2-RGB-RED',
                    'price' => 2990,
                    'specifications' => [
                        '軸體' => '紅軸',
                        '配置' => '75%',
                    ],
                ],
            ]);

        // 驗證有 4 張圖片
        $this->assertCount(4, $response->json('data.images'));
    }

    /**
     * TC-DETAIL-002: 取得商品詳情 (使用 Slug)
     *
     * @test
     */
    public function can_get_product_detail_by_slug()
    {
        // Arrange
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'keychron-k2',
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/keychron-k2");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'slug' => 'keychron-k2',
                ],
            ]);
    }

    /**
     * TC-DETAIL-003: 商品不存在
     *
     * @test
     */
    public function returns_404_when_product_not_found()
    {
        // Act
        $response = $this->getJson('/api/v1/products/999');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'message' => '找不到該商品',
            ]);
    }

    /**
     * TC-DETAIL-004: 下架商品無法查看
     *
     * @test
     */
    public function cannot_get_inactive_product_detail()
    {
        // Arrange: 建立下架商品
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false, // 已下架
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert: 應該返回 404
        $response->assertStatus(404);
    }

    /**
     * TC-DETAIL-005: 商品圖片排序正確
     *
     * @test
     */
    public function product_images_are_sorted_correctly()
    {
        // Arrange
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // 建立不同排序的圖片
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 3,
            'is_primary' => false,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert: 圖片應該按 sort_order 排序
        $response->assertStatus(200);
        $images = $response->json('data.images');

        $this->assertEquals(1, $images[0]['sort_order']);
        $this->assertEquals(2, $images[1]['sort_order']);
        $this->assertEquals(3, $images[2]['sort_order']);

        // 主圖應該是第一張
        $this->assertTrue($images[0]['is_primary']);
    }

    /**
     * TC-DETAIL-006: 沒有圖片的商品
     *
     * @test
     */
    public function product_without_images_returns_empty_array()
    {
        // Arrange: 建立沒有圖片的商品
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        // 不建立圖片

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'images' => [],
                ],
            ]);
    }

    /**
     * TC-DETAIL-007: Specifications 為 null 時處理
     *
     * @test
     */
    public function handles_null_specifications()
    {
        // Arrange
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'specifications' => null,
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'specifications' => null,
                ],
            ]);
    }

    /**
     * TC-DETAIL-008: 使用不存在的 Slug
     *
     * @test
     */
    public function returns_404_for_nonexistent_slug()
    {
        // Act
        $response = $this->getJson('/api/v1/products/nonexistent-product');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-DETAIL-009: 商品包含完整分類資訊
     *
     * @test
     */
    public function product_includes_full_category_details()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'name' => '機械鍵盤',
            'slug' => 'mechanical-keyboards',
            'description' => '各式機械鍵盤',
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'name' => '機械鍵盤',
                        'slug' => 'mechanical-keyboards',
                    ],
                ],
            ]);
    }
}
