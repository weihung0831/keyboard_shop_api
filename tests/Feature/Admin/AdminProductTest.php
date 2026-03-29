<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 管理員商品管理 API 測試
 * TC-ADMIN-PRODUCT-001 ~ TC-ADMIN-PRODUCT-020
 */
class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理員用戶
     */
    private User $admin;

    /**
     * 商品分類
     */
    private ProductCategory $category;

    /**
     * 測試前準備
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->category = ProductCategory::factory()->create();

        // 初始化虛擬磁盤
        Storage::fake('public');
    }

    /**
     * TC-ADMIN-PRODUCT-001: 管理員 GET /admin/products → 回傳含 inactive 商品
     * 驗證列表包含已上架和未上架的商品
     *
     * @test
     */
    public function admin_can_view_all_products_including_inactive(): void
    {
        // Arrange: 建立已上架和未上架的商品
        $activeProduct = Product::factory()
            ->active()
            ->create([
                'category_id' => $this->category->id,
                'name' => 'Active Keyboard',
            ]);

        $inactiveProduct = Product::factory()
            ->inactive()
            ->create([
                'category_id' => $this->category->id,
                'name' => 'Inactive Keyboard',
            ]);

        // Act: 以管理員身份取得商品列表
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products');

        // Assert: 驗證兩個商品都出現在列表中
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Active Keyboard'])
            ->assertJsonFragment(['name' => 'Inactive Keyboard']);
    }

    /**
     * TC-ADMIN-PRODUCT-002: 管理員 POST /admin/products（完整資料）→ 201
     * 驗證建立商品且同時建立規格
     *
     * @test
     */
    public function admin_can_create_product_with_specifications(): void
    {
        // Arrange: 準備商品和規格資料
        $payload = [
            'name' => 'Premium Mechanical Keyboard',
            'category_id' => $this->category->id,
            'price' => 5000,
            'stock' => 50,
            'sku' => 'KB-PREMIUM-001',
            'slug' => 'premium-mechanical-keyboard',
            'description' => '高級機械鍵盤',
            'content' => '<p>詳細描述</p>',
            'original_price' => 6000,
            'is_active' => true,
            'sort_order' => 1,
            'specifications' => [
                [
                    'spec_name' => '軸體',
                    'spec_value' => 'Cherry MX Red',
                    'sort_order' => 1,
                ],
                [
                    'spec_name' => '配置',
                    'spec_value' => '80%',
                    'sort_order' => 2,
                ],
            ],
        ];

        // Act: 以管理員身份建立商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 驗證商品和規格建立成功
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Premium Mechanical Keyboard')
            ->assertJsonPath('data.sku', 'KB-PREMIUM-001')
            ->assertJsonPath('data.price', 5000);

        $this->assertDatabaseHas('products', [
            'sku' => 'KB-PREMIUM-001',
            'name' => 'Premium Mechanical Keyboard',
        ]);

        // 驗證規格被建立
        $product = Product::where('sku', 'KB-PREMIUM-001')->first();
        $this->assertEquals(2, $product->specifications()->count());
    }

    /**
     * TC-ADMIN-PRODUCT-003: 管理員 POST /admin/products（缺必填欄位）→ 422
     * 驗證驗證規則正確執行
     *
     * @test
     */
    public function admin_cannot_create_product_with_missing_required_fields(): void
    {
        // Arrange: 準備缺少必填欄位的資料
        $payload = [
            // 缺少 name、category_id、price、stock、sku
            'description' => '不完整的商品資料',
        ];

        // Act: 嘗試建立商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 應該回傳 422 驗證失敗
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id', 'price', 'stock', 'sku']);
    }

    /**
     * TC-ADMIN-PRODUCT-004: 管理員 POST /admin/products（重複 SKU）→ 422
     * 驗證 SKU 唯一性驗證
     *
     * @test
     */
    public function admin_cannot_create_product_with_duplicate_sku(): void
    {
        // Arrange: 先建立一個商品
        Product::factory()->create([
            'category_id' => $this->category->id,
            'sku' => 'KB-DUP-001',
        ]);

        $payload = [
            'name' => 'Another Keyboard',
            'category_id' => $this->category->id,
            'price' => 3000,
            'stock' => 30,
            'sku' => 'KB-DUP-001', // 重複的 SKU
        ];

        // Act: 嘗試建立重複 SKU 的商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 應該回傳 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /**
     * TC-ADMIN-PRODUCT-005: 管理員 PUT /admin/products/{id} → 200 更新成功
     * 驗證產品更新功能
     *
     * @test
     */
    public function admin_can_update_product(): void
    {
        // Arrange: 建立商品
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Original Name',
            'price' => 2000,
        ]);

        $updatePayload = [
            'name' => 'Updated Name',
            'category_id' => $this->category->id,
            'price' => 2500,
            'stock' => $product->stock,
            'sku' => $product->sku,
        ];

        // Act: 更新商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", $updatePayload);

        // Assert: 驗證更新成功
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', 2500);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'price' => 2500,
        ]);
    }

    /**
     * TC-ADMIN-PRODUCT-006: 管理員 DELETE /admin/products/{id}（無訂單關聯）→ 200
     * 驗證刪除沒有訂單的商品
     *
     * @test
     */
    public function admin_can_delete_product_without_orders(): void
    {
        // Arrange: 建立商品（不關聯訂單）
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $productId = $product->id;

        // Act: 刪除商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$productId}");

        // Assert: 驗證刪除成功
        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    /**
     * TC-ADMIN-PRODUCT-007: 管理員 DELETE /admin/products/{id}（有訂單關聯）→ 422
     * 驗證無法刪除有訂單的商品
     *
     * @test
     */
    public function admin_cannot_delete_product_with_orders(): void
    {
        // Arrange: 建立商品和訂單
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
        ]);

        // Act: 嘗試刪除有訂單的商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$product->id}");

        // Assert: 應該回傳 422
        $response->assertStatus(422);

        // 驗證商品仍然存在
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /**
     * TC-ADMIN-PRODUCT-008: 刪除商品時 specifications + images 級聯刪除
     * 驗證級聯刪除功能
     *
     * @test
     */
    public function deleting_product_cascades_delete_specifications_and_images(): void
    {
        // Arrange: 建立商品及其規格和圖片
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        ProductSpecification::factory(3)->create(['product_id' => $product->id]);
        ProductImage::factory(2)->create(['product_id' => $product->id]);

        $productId = $product->id;
        $specIds = $product->specifications->pluck('id')->toArray();
        $imageIds = $product->images->pluck('id')->toArray();

        // Act: 刪除商品
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$productId}");

        // Assert: 驗證商品、規格和圖片都被刪除
        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseMissing('product_specifications', ['product_id' => $productId]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $productId]);
    }

    /**
     * TC-ADMIN-PRODUCT-009: 管理員 GET /admin/products?search=keyword → 回傳搜尋結果
     * 驗證搜尋功能
     *
     * @test
     */
    public function admin_can_search_products_by_keyword(): void
    {
        // Arrange: 建立多個商品
        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Mechanical Keyboard Pro',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Budget Keyboard',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Mechanical Keycaps Set',
        ]);

        // Act: 搜尋包含 "Mechanical" 的商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products?search=Mechanical');

        // Assert: 應該只回傳 2 個包含 "Mechanical" 的商品
        $response->assertStatus(200);
        $products = $response->json('data');
        $this->assertCount(2, $products);
        $names = array_column($products, 'name');
        $this->assertContains('Mechanical Keyboard Pro', $names);
        $this->assertContains('Mechanical Keycaps Set', $names);
    }

    /**
     * TC-ADMIN-PRODUCT-010: 管理員 GET /admin/products?is_active=false → 只回傳下架商品
     * 驗證狀態篩選功能
     *
     * @test
     */
    public function admin_can_filter_products_by_is_active_status(): void
    {
        // Arrange: 建立已上架和未上架的商品
        Product::factory(3)->active()->create([
            'category_id' => $this->category->id,
        ]);

        Product::factory(2)->inactive()->create([
            'category_id' => $this->category->id,
        ]);

        // Act: 篩選未上架的商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products?is_active=0');

        // Assert: 應該只回傳 2 個未上架的商品
        $response->assertStatus(200);
        $products = $response->json('data');
        $this->assertCount(2, $products);
        foreach ($products as $product) {
            $this->assertFalse($product['is_active']);
        }
    }

    /**
     * TC-ADMIN-PRODUCT-011: 管理員 POST /admin/products/batch-toggle → 批次更新 is_active
     * 驗證批次操作功能
     *
     * @test
     */
    public function admin_can_batch_toggle_product_status(): void
    {
        // Arrange: 建立多個商品
        $product1 = Product::factory()->active()->create([
            'category_id' => $this->category->id,
        ]);

        $product2 = Product::factory()->inactive()->create([
            'category_id' => $this->category->id,
        ]);

        $payload = [
            'product_ids' => [$product1->id, $product2->id],
            'is_active' => false,
        ];

        // Act: 批次設定為不啟用
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products/batch-toggle', $payload);

        // Assert: 驗證更新成功
        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product1->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product2->id,
            'is_active' => false,
        ]);
    }

    /**
     * TC-ADMIN-PRODUCT-012: 管理員 POST /admin/products/batch-toggle (空陣列) → 422
     * 驗證批次操作的驗證規則
     *
     * @test
     */
    public function admin_cannot_batch_toggle_with_empty_product_ids(): void
    {
        // Arrange: 準備空的 product_ids 陣列
        $payload = [
            'product_ids' => [],
            'is_active' => false,
        ];

        // Act: 嘗試批次操作
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products/batch-toggle', $payload);

        // Assert: 應該回傳 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_ids']);
    }

    /**
     * TC-ADMIN-PRODUCT-013: 管理員 GET /admin/products/low-stock?threshold=5 → 回傳庫存不足商品
     * 驗證低庫存篩選功能
     *
     * @test
     */
    public function admin_can_view_low_stock_products(): void
    {
        // Arrange: 建立不同庫存的商品
        Product::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'name' => 'Good Stock',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 3,
            'name' => 'Low Stock 1',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 2,
            'name' => 'Low Stock 2',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 0,
            'name' => 'Out of Stock',
        ]);

        // Act: 取得庫存少於 5 的商品
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products/low-stock?threshold=5');

        // Assert: 應該回傳 3 個庫存不足的商品
        $response->assertStatus(200);
        // 回應格式為 { data: [...] } 或直接 [...]，根據 controller 實現
        $products = is_array($response->json('data')) && isset($response->json('data')[0])
            ? $response->json('data')
            : $response->json();
        $this->assertGreaterThanOrEqual(3, count($products));
        foreach ($products as $product) {
            $this->assertLessThan(5, $product['stock']);
        }
    }

    /**
     * TC-ADMIN-PRODUCT-014: 圖片上傳非圖片檔案 → 422
     * 驗證檔案類型驗證
     *
     * @test
     */
    public function admin_cannot_upload_non_image_file(): void
    {
        // Arrange: 建立商品
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        // 建立假的非圖片檔案
        $file = UploadedFile::fake()->create('document.txt', 100);

        $payload = [
            'images' => [$file],
        ];

        // Act: 嘗試上傳非圖片檔案
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$product->id}/images", $payload);

        // Assert: 應該回傳 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /**
     * TC-ADMIN-PRODUCT-015: 圖片上傳超過 5MB → 422
     * 驗證檔案大小限制
     *
     * @test
     */
    public function admin_cannot_upload_image_exceeding_size_limit(): void
    {
        // Arrange: 建立商品
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        // 建立超過大小限制的假圖片（6MB）
        $file = UploadedFile::fake()->image('large.jpg')->size(6000);

        $payload = [
            'images' => [$file],
        ];

        // Act: 嘗試上傳超大圖片
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$product->id}/images", $payload);

        // Assert: 應該回傳 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /**
     * TC-ADMIN-PRODUCT-016: GET /admin/products/{id}/specs → 回傳該商品所有規格
     * 驗證規格列表功能
     *
     * @test
     */
    public function admin_can_list_product_specifications(): void
    {
        // Arrange: 建立商品和規格
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $specs = ProductSpecification::factory(3)->create([
            'product_id' => $product->id,
        ]);

        // Act: 取得商品規格列表
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/products/{$product->id}/specs");

        // Assert: 驗證規格列表
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        foreach ($response->json('data') as $spec) {
            $this->assertArrayHasKey('id', $spec);
            $this->assertArrayHasKey('spec_name', $spec);
            $this->assertArrayHasKey('spec_value', $spec);
        }
    }

    /**
     * TC-ADMIN-PRODUCT-017: POST /admin/products/{id}/specs → 新增規格
     * 驗證新增規格功能
     *
     * @test
     */
    public function admin_can_create_product_specification(): void
    {
        // Arrange: 建立商品
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $payload = [
            'spec_name' => '連接方式',
            'spec_value' => '藍牙 5.0',
            'sort_order' => 1,
        ];

        // Act: 新增規格
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$product->id}/specs", $payload);

        // Assert: 驗證規格建立成功
        $response->assertStatus(201)
            ->assertJsonPath('data.spec_name', '連接方式')
            ->assertJsonPath('data.spec_value', '藍牙 5.0');

        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $product->id,
            'spec_name' => '連接方式',
            'spec_value' => '藍牙 5.0',
        ]);
    }

    /**
     * TC-ADMIN-PRODUCT-018: PUT /admin/products/{id}/specs/{specId} → 更新規格
     * 驗證規格更新功能
     *
     * @test
     */
    public function admin_can_update_product_specification(): void
    {
        // Arrange: 建立商品和規格
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $spec = ProductSpecification::factory()->create([
            'product_id' => $product->id,
            'spec_name' => '軸體',
            'spec_value' => 'Cherry MX Red',
        ]);

        $updatePayload = [
            'spec_name' => '軸體',
            'spec_value' => 'Cherry MX Blue',
        ];

        // Act: 更新規格
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}/specs/{$spec->id}", $updatePayload);

        // Assert: 驗證規格更新成功
        $response->assertStatus(200)
            ->assertJsonPath('data.spec_value', 'Cherry MX Blue');

        $this->assertDatabaseHas('product_specifications', [
            'id' => $spec->id,
            'spec_value' => 'Cherry MX Blue',
        ]);
    }

    /**
     * TC-ADMIN-PRODUCT-019: DELETE /admin/products/{id}/specs/{specId} → 刪除規格
     * 驗證規格刪除功能
     *
     * @test
     */
    public function admin_can_delete_product_specification(): void
    {
        // Arrange: 建立商品和規格
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $spec = ProductSpecification::factory()->create([
            'product_id' => $product->id,
        ]);
        $specId = $spec->id;

        // Act: 刪除規格
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$product->id}/specs/{$specId}");

        // Assert: 驗證規格刪除成功
        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_specifications', ['id' => $specId]);
    }

    /**
     * TC-ADMIN-PRODUCT-020: POST 規格到不存在的商品 → 404
     * 驗證商品存在性檢查
     *
     * @test
     */
    public function admin_cannot_add_spec_to_non_existent_product(): void
    {
        // Arrange: 使用不存在的商品 ID
        $nonExistentProductId = 99999;

        $payload = [
            'spec_name' => '軸體',
            'spec_value' => 'Cherry MX Red',
        ];

        // Act: 嘗試為不存在的商品新增規格
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$nonExistentProductId}/specs", $payload);

        // Assert: 應該回傳 404
        $response->assertStatus(404);
    }
}
