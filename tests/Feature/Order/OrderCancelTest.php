<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 建立有庫存的商品
     */
    private function createProductWithStock(int $stock = 10): Product
    {
        $category = ProductCategory::factory()->create();

        return Product::factory()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    /**
     * 為會員建立訂單含項目（使用真實商品）
     */
    private function createOrderWithProduct(User $user, Product $product, int $qty = 1, string $status = 'pending'): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku ?? '',
            'quantity' => $qty,
            'price' => $product->price,
            'subtotal' => $qty * $product->price,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * TC-ORDER-021: 成功取消待付款訂單
     *
     * @test
     */
    public function can_cancel_pending_order()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $order = $this->createOrderWithProduct($user, $product, 2, Order::STATUS_PENDING);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);
    }

    /**
     * TC-ORDER-022: 取消訂單後庫存回補
     *
     * @test
     */
    public function stock_is_restored_after_order_cancellation()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(7); // 原始庫存 7（模擬已扣減後）
        $order = $this->createOrderWithProduct($user, $product, 3, Order::STATUS_PENDING);

        // Act: 取消訂單
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        // Assert: 庫存應從 7 回補 3 變為 10
        $this->assertEquals(10, $product->fresh()->stock);
    }

    /**
     * TC-ORDER-023: 已出貨訂單無法取消
     *
     * @test
     */
    public function cannot_cancel_shipped_order()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $order = $this->createOrderWithProduct($user, $product, 1, Order::STATUS_SHIPPED);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        // Assert
        $response->assertStatus(400);
    }

    /**
     * TC-ORDER-024: 已完成訂單無法取消
     *
     * @test
     */
    public function cannot_cancel_completed_order()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $order = $this->createOrderWithProduct($user, $product, 1, Order::STATUS_COMPLETED);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        // Assert
        $response->assertStatus(400);
    }

    /**
     * TC-ORDER-025: 無法取消他人訂單
     *
     * @test
     */
    public function cannot_cancel_other_users_order()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $order = $this->createOrderWithProduct($user1, $product, 1, Order::STATUS_PENDING);

        // Act: user2 嘗試取消 user1 的訂單
        $response = $this->actingAs($user2, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        // Assert
        $response->assertStatus(400);
    }
}
