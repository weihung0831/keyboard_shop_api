<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理員訂單管理 API 測試
 * 測試列表、篩選、詳情、狀態更新與庫存回補功能
 */
class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 建立管理員帳號
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * 建立有庫存的商品
     */
    private function createProductWithStock(int $stock = 10, float $price = 1000): Product
    {
        $category = ProductCategory::factory()->create();

        return Product::factory()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    /**
     * TC-ADMIN-ORDER-001: GET /admin/orders 無篩選 → 回傳所有訂單，分頁
     *
     * @test
     */
    public function can_list_all_orders_with_pagination()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Order::factory()->pending()->create(['user_id' => $user1->id]);
        Order::factory()->processing()->create(['user_id' => $user1->id]);
        Order::factory()->completed()->create(['user_id' => $user2->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '取得訂單列表成功')
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'status',
                        'user',
                        'total_amount',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ]);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    /**
     * TC-ADMIN-ORDER-002: GET /admin/orders?status=pending → 只回傳 pending 訂單
     *
     * @test
     */
    public function can_filter_orders_by_status()
    {
        // Arrange
        $user = User::factory()->create();
        Order::factory()->pending()->create(['user_id' => $user->id]);
        Order::factory()->processing()->create(['user_id' => $user->id]);
        Order::factory()->shipped()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?status=pending');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    /**
     * TC-ADMIN-ORDER-003: GET /admin/orders?search=ORD → 搜尋訂單編號
     *
     * @test
     */
    public function can_search_orders_by_order_number()
    {
        // Arrange
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-20260329-ABCDE',
        ]);
        Order::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?search=ORD-20260329-ABCDE');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'ORD-20260329-ABCDE');
    }

    /**
     * TC-ADMIN-ORDER-004: GET /admin/orders?search=name → 搜尋會員姓名
     *
     * @test
     */
    public function can_search_orders_by_user_name()
    {
        // Arrange
        $user1 = User::factory()->create(['name' => 'Wang Ming']);
        $user2 = User::factory()->create(['name' => 'Lee Hua']);

        Order::factory()->create(['user_id' => $user1->id]);
        Order::factory()->create(['user_id' => $user2->id]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?search=Wang');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Wang Ming');
    }

    /**
     * TC-ADMIN-ORDER-005: GET /admin/orders?date_from=X&date_to=Y → 日期區間篩選
     *
     * @test
     */
    public function can_filter_orders_by_date_range()
    {
        // Arrange
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        // Act
        $dateFrom = now()->subDays(3)->toDateString();
        $dateTo = now()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/orders?date_from={$dateFrom}&date_to={$dateTo}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * TC-ADMIN-ORDER-006: GET /admin/orders/{id} → 回傳含 user/items/payment
     *
     * @test
     */
    public function can_get_order_detail_with_relations()
    {
        // Arrange
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000,
            'subtotal' => 2000,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/orders/{$order->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '取得訂單詳情成功')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'items' => [
                        '*' => [
                            'product_name',
                            'quantity',
                            'price',
                        ],
                    ],
                    'timeline',
                ],
            ]);
    }

    /**
     * TC-ADMIN-ORDER-007: PATCH pending → processing → 200 + paid_at 設置
     *
     * @test
     */
    public function can_update_pending_order_to_processing_with_paid_at()
    {
        // Arrange
        $order = Order::factory()->pending()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_PROCESSING,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '訂單狀態已更新')
            ->assertJsonPath('data.status', 'processing');

        $this->assertNotNull($order->fresh()->paid_at);
    }

    /**
     * TC-ADMIN-ORDER-008: PATCH processing → shipped → 200 + shipped_at 更新
     *
     * @test
     */
    public function can_update_processing_order_to_shipped_with_shipped_at()
    {
        // Arrange
        $order = Order::factory()->processing()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_SHIPPED,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'shipped');

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->shipped_at);
    }

    /**
     * TC-ADMIN-ORDER-009: PATCH shipped → completed → 200 + completed_at 更新
     *
     * @test
     */
    public function can_update_shipped_order_to_completed_with_completed_at()
    {
        // Arrange
        $order = Order::factory()->shipped()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_COMPLETED,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->completed_at);
    }

    /**
     * TC-ADMIN-ORDER-010: PATCH pending → cancelled → 200 + cancelled_at 更新 + 庫存回補
     *
     * @test
     */
    public function can_cancel_pending_order_and_restore_stock()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(stock: 10);

        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $initialStock = $product->stock;

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CANCELLED,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->cancelled_at);

        $updatedProduct = $product->fresh();
        $this->assertEquals($initialStock + 2, $updatedProduct->stock);
    }

    /**
     * TC-ADMIN-ORDER-011: PATCH completed → processing → 422 非法狀態轉換（terminal）
     *
     * @test
     */
    public function cannot_update_completed_order_to_processing()
    {
        // Arrange
        $order = Order::factory()->completed()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_PROCESSING,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, '無法'));
    }

    /**
     * TC-ADMIN-ORDER-012: PATCH cancelled → processing → 422 非法狀態轉換（terminal）
     *
     * @test
     */
    public function cannot_update_cancelled_order_to_processing()
    {
        // Arrange
        $order = Order::factory()->cancelled()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_PROCESSING,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, '無法'));
    }

    /**
     * TC-ADMIN-ORDER-013: PATCH shipped → cancelled → 200（管理員可取消 shipped）
     *
     * @test
     */
    public function admin_can_cancel_shipped_order()
    {
        // Arrange
        $order = Order::factory()->shipped()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CANCELLED,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    /**
     * TC-ADMIN-ORDER-014: PATCH pending → shipped（跳過 processing）→ 422 非法
     *
     * @test
     */
    public function cannot_skip_order_status_transitions()
    {
        // Arrange
        $order = Order::factory()->pending()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_SHIPPED,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, '無法'));
    }

    /**
     * TC-ADMIN-ORDER-015: 多項訂單取消時，庫存分別回補
     *
     * @test
     */
    public function can_restore_stock_for_multiple_items_in_order()
    {
        // Arrange
        $product1 = $this->createProductWithStock(stock: 10);
        $product2 = $this->createProductWithStock(stock: 20);

        $order = Order::factory()->pending()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 3,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 5,
        ]);

        // Act
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CANCELLED,
            ]);

        // Assert
        $this->assertEquals(13, $product1->fresh()->stock);
        $this->assertEquals(25, $product2->fresh()->stock);
    }

    /**
     * TC-ADMIN-ORDER-016: 無認證 → 401 Unauthorized
     *
     * @test
     */
    public function cannot_list_orders_without_authentication()
    {
        // Act
        $response = $this->getJson('/api/v1/admin/orders');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-ADMIN-ORDER-017: 非管理員用戶嘗試更新狀態 → 403 Forbidden
     *
     * @test
     */
    public function regular_user_cannot_update_admin_order_status()
    {
        // Arrange
        $regularUser = User::factory()->create();
        $order = Order::factory()->pending()->create();

        // Act
        $response = $this->actingAs($regularUser, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_PROCESSING,
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * TC-ADMIN-ORDER-018: 不存在的訂單 ID → 404 Not Found
     *
     * @test
     */
    public function get_nonexistent_order_returns_404()
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders/99999');

        // Assert
        $response->assertStatus(404)
            ->assertJsonPath('message', '訂單不存在');
    }

    /**
     * TC-ADMIN-ORDER-019: 更新不存在的訂單 → 404 Not Found
     *
     * @test
     */
    public function update_nonexistent_order_returns_404()
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/v1/admin/orders/99999/status', [
                'status' => Order::STATUS_PROCESSING,
            ]);

        // Assert
        $response->assertStatus(404)
            ->assertJsonPath('message', '訂單不存在');
    }

    /**
     * TC-ADMIN-ORDER-020: 測試時間戳欄位在各狀態轉換時正確記錄
     *
     * @test
     */
    public function timestamp_fields_are_correctly_set_on_status_transitions()
    {
        // Arrange
        $order = Order::factory()->pending()->create();
        $this->assertNull($order->paid_at);
        $this->assertNull($order->shipped_at);

        // Act & Assert - pending → processing
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_PROCESSING,
            ]);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->shipped_at);

        // Act & Assert - processing → shipped
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_SHIPPED,
            ]);

        $order->refresh();
        $this->assertNotNull($order->shipped_at);

        // Act & Assert - shipped → completed
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_COMPLETED,
            ]);

        $order->refresh();
        $this->assertNotNull($order->completed_at);
    }

    /**
     * TC-ADMIN-ORDER-021: 訂單列表分頁正確性
     *
     * @test
     */
    public function order_pagination_works_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            Order::factory()->create(['user_id' => $user->id]);
        }

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?per_page=10');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2);
    }
}
