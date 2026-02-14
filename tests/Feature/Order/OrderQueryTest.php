<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 為會員建立訂單含項目
     */
    private function createOrderWithItems(User $user, string $status = 'pending', int $item_count = 1): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        $category = ProductCategory::factory()->create();
        for ($i = 0; $i < $item_count; $i++) {
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'is_active' => true,
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
            ]);
        }

        return $order->fresh(['items']);
    }

    /**
     * TC-ORDER-011: 取得訂單列表含分頁資訊
     *
     * @test
     */
    public function can_get_order_list()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createOrderWithItems($user);
        $this->createOrderWithItems($user);
        $this->createOrderWithItems($user);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * TC-ORDER-012: 分頁功能正常
     *
     * @test
     */
    public function order_list_supports_pagination()
    {
        // Arrange
        $user = User::factory()->create();
        for ($i = 0; $i < 15; $i++) {
            $this->createOrderWithItems($user);
        }

        // Act: 每頁 5 筆，取第 2 頁
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders?per_page=5&page=2');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 15);
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * TC-ORDER-013: 依狀態篩選訂單
     *
     * @test
     */
    public function can_filter_orders_by_status()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createOrderWithItems($user, Order::STATUS_PENDING);
        $this->createOrderWithItems($user, Order::STATUS_PENDING);
        $this->createOrderWithItems($user, Order::STATUS_COMPLETED);

        // Act: 只取 pending
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders?status=pending');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * TC-ORDER-014: 依日期範圍篩選
     *
     * @test
     */
    public function can_filter_orders_by_date_range()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createOrderWithItems($user);

        $today = now()->format('Y-m-d');

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders?start_date={$today}&end_date={$today}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * TC-ORDER-015: 只能看到自己的訂單
     *
     * @test
     */
    public function can_only_see_own_orders()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->createOrderWithItems($user1);
        $this->createOrderWithItems($user1);
        $this->createOrderWithItems($user2);

        // Act: user1 查詢
        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/v1/orders');

        // Assert: 只看到 user1 的 2 筆
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * TC-ORDER-016: 未登入無法查詢
     *
     * @test
     */
    public function unauthenticated_user_cannot_query_orders()
    {
        // Act
        $response = $this->getJson('/api/v1/orders');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-ORDER-017: 訂單詳情含時間軸資料
     *
     * @test
     */
    public function can_get_order_detail_with_timeline()
    {
        // Arrange
        $user = User::factory()->create();
        $order = $this->createOrderWithItems($user);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'items',
                    'timeline',
                ],
            ]);

        // 時間軸至少包含建立事件
        $timeline = $response->json('data.timeline');
        $this->assertNotEmpty($timeline);
        $this->assertEquals('created', $timeline[0]['status']);
    }

    /**
     * TC-ORDER-018: 查看不存在的訂單返回 404
     *
     * @test
     */
    public function returns_404_for_nonexistent_order()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders/99999');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-ORDER-019: 無法查看他人訂單
     *
     * @test
     */
    public function cannot_view_other_users_order()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $order = $this->createOrderWithItems($user1);

        // Act: user2 嘗試查看 user1 的訂單
        $response = $this->actingAs($user2, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * TC-ORDER-020: 訂單統計各狀態數量正確
     *
     * @test
     */
    public function can_get_order_stats()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createOrderWithItems($user, Order::STATUS_PENDING);
        $this->createOrderWithItems($user, Order::STATUS_PENDING);
        $this->createOrderWithItems($user, Order::STATUS_PROCESSING);
        $this->createOrderWithItems($user, Order::STATUS_COMPLETED);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders/stats');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.processing', 1)
            ->assertJsonPath('data.completed', 1);
    }
}
