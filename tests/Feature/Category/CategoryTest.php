<?php

namespace Tests\Feature\Category;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-CAT-001: 取得分類列表
     *
     * @test
     */
    public function can_get_category_list()
    {
        // Arrange: 建立多個啟用的分類
        ProductCategory::factory()->count(3)->create([
            'is_active' => true,
        ]);

        // 建立一個未啟用的分類 (不應該出現)
        ProductCategory::factory()->create([
            'is_active' => false,
        ]);

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image_url',
                        'products_count',
                    ],
                ],
            ]);

        // 只應該返回 3 個啟用的分類
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * TC-CAT-002: 取得分類詳情 (使用 ID)
     *
     * @test
     */
    public function can_get_category_detail_by_id()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'name' => '機械鍵盤',
            'slug' => 'mechanical-keyboards',
            'description' => '各式機械鍵盤',
            'is_active' => true,
        ]);

        // 建立該分類的商品
        Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson("/api/v1/categories/{$category->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => '機械鍵盤',
                    'slug' => 'mechanical-keyboards',
                    'description' => '各式機械鍵盤',
                    'products_count' => 5,
                ],
            ]);
    }

    /**
     * TC-CAT-003: 分類不存在
     *
     * @test
     */
    public function returns_404_when_category_not_found()
    {
        // Act
        $response = $this->getJson('/api/v1/categories/999');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'message' => '找不到該分類',
            ]);
    }

    /**
     * TC-CAT-004: 使用 Slug 取得分類
     *
     * @test
     */
    public function can_get_category_by_slug()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'name' => '機械鍵盤',
            'slug' => 'mechanical-keyboards',
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/categories/mechanical-keyboards');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'slug' => 'mechanical-keyboards',
                ],
            ]);
    }

    /**
     * TC-CAT-005: 分類商品數量正確計算
     *
     * @test
     */
    public function products_count_is_calculated_correctly()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'is_active' => true,
        ]);

        // 建立 10 個上架商品
        Product::factory()->count(10)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // 建立 3 個下架商品 (不應該計算)
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert: 只計算上架商品
        $categoryData = collect($response->json('data'))
            ->firstWhere('id', $category->id);

        $this->assertEquals(10, $categoryData['products_count']);
    }

    /**
     * TC-CAT-006: 分類列表按排序欄位排序
     *
     * @test
     */
    public function categories_are_sorted_by_sort_order()
    {
        // Arrange
        $category1 = ProductCategory::factory()->create([
            'name' => 'Category C',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $category2 = ProductCategory::factory()->create([
            'name' => 'Category A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $category3 = ProductCategory::factory()->create([
            'name' => 'Category B',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert: 應該按 sort_order 排序
        $response->assertStatus(200);
        $categories = $response->json('data');

        $this->assertEquals('Category A', $categories[0]['name']);
        $this->assertEquals('Category B', $categories[1]['name']);
        $this->assertEquals('Category C', $categories[2]['name']);
    }

    /**
     * TC-CAT-007: 未啟用的分類不顯示
     *
     * @test
     */
    public function inactive_categories_are_not_listed()
    {
        // Arrange
        $activeCategory = ProductCategory::factory()->create([
            'name' => '啟用分類',
            'is_active' => true,
        ]);

        $inactiveCategory = ProductCategory::factory()->create([
            'name' => '未啟用分類',
            'is_active' => false,
        ]);

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert
        $response->assertStatus(200);
        $categoryNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('啟用分類', $categoryNames);
        $this->assertNotContains('未啟用分類', $categoryNames);
    }

    /**
     * TC-CAT-008: 未啟用的分類無法查看詳情
     *
     * @test
     */
    public function cannot_get_inactive_category_detail()
    {
        // Arrange
        $category = ProductCategory::factory()->create([
            'is_active' => false,
        ]);

        // Act
        $response = $this->getJson("/api/v1/categories/{$category->id}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-CAT-009: 空分類列表
     *
     * @test
     */
    public function returns_empty_array_when_no_categories()
    {
        // Arrange: 不建立任何分類

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    /**
     * TC-CAT-010: 分類沒有商品時顯示 0
     *
     * @test
     */
    public function shows_zero_count_for_empty_category()
    {
        // Arrange: 建立沒有商品的分類
        $category = ProductCategory::factory()->create([
            'is_active' => true,
        ]);
        // 不建立商品

        // Act
        $response = $this->getJson('/api/v1/categories');

        // Assert
        $categoryData = collect($response->json('data'))
            ->firstWhere('id', $category->id);

        $this->assertEquals(0, $categoryData['products_count']);
    }
}
