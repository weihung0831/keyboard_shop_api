<?php

namespace Tests\Feature\Order;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 有效的收件資訊
     */
    private function validShippingData(): array
    {
        return [
            'shipping_name' => '測試用戶',
            'shipping_phone' => '0912-345-678',
            'shipping_email' => 'test@example.com',
            'shipping_postal_code' => '100',
            'shipping_city' => '台北市',
            'shipping_address' => '中正區測試路100號',
            'shipping_method' => 'standard',
        ];
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
     * 為會員建立含商品的購物車
     */
    private function createCartWithItems(User $user, int $item_count = 1, int $stock = 10, float $price = 1000): Cart
    {
        $cart = Cart::factory()->withUser($user)->create();

        for ($i = 0; $i < $item_count; $i++) {
            $product = $this->createProductWithStock($stock, $price);
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $product->price,
            ]);
        }

        return $cart->fresh(['items.product']);
    }

    /**
     * TC-ORDER-001: 成功從購物車建立訂單
     *
     * @test
     */
    public function can_create_order_from_cart()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createCartWithItems($user, 2);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('message', '訂單建立成功')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'items',
                    'subtotal',
                    'shipping_fee',
                    'total_amount',
                    'shipping_name',
                    'shipping_phone',
                    'timeline',
                ],
            ]);
    }

    /**
     * TC-ORDER-002: 建立訂單後購物車被清空
     *
     * @test
     */
    public function cart_is_cleared_after_order_creation()
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->createCartWithItems($user, 1);

        // Act
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert: 購物車應該被清空
        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * TC-ORDER-003: 建立訂單後商品庫存正確扣減
     *
     * @test
     */
    public function stock_is_deducted_after_order_creation()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10, 500);
        $cart = Cart::factory()->withUser($user)->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => $product->price,
        ]);

        // Act
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert: 庫存應從 10 變為 7
        $this->assertEquals(7, $product->fresh()->stock);
    }

    /**
     * TC-ORDER-004: 購物車為空時返回 400
     *
     * @test
     */
    public function cannot_create_order_with_empty_cart()
    {
        // Arrange: 會員沒有購物車或購物車為空
        $user = User::factory()->create();
        Cart::factory()->withUser($user)->create(); // 空購物車

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert
        $response->assertStatus(400);
    }

    /**
     * TC-ORDER-005: 庫存不足時返回 422
     *
     * @test
     */
    public function cannot_create_order_with_insufficient_stock()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(2, 500);
        $cart = Cart::factory()->withUser($user)->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5, // 購物車有 5 個但庫存只有 2
            'price' => $product->price,
        ]);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert
        $response->assertStatus(422);
    }

    /**
     * TC-ORDER-006: 未登入返回 401
     *
     * @test
     */
    public function unauthenticated_user_cannot_create_order()
    {
        // Arrange: 不登入

        // Act
        $response = $this->postJson('/api/v1/orders', $this->validShippingData());

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-ORDER-007: 缺少必填收件欄位返回 422
     *
     * @test
     */
    public function validates_required_shipping_fields()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createCartWithItems($user, 1);

        // Act: 送空資料
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_name', 'shipping_phone', 'shipping_address']);
    }

    /**
     * TC-ORDER-008: 手機號碼格式錯誤返回 422
     *
     * @test
     */
    public function validates_phone_number_format()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createCartWithItems($user, 1);
        $data = $this->validShippingData();
        $data['shipping_phone'] = '12345'; // 不符合台灣手機格式

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_phone']);
    }

    /**
     * TC-ORDER-009: 訂單編號自動產生且格式正確
     *
     * @test
     */
    public function order_number_is_auto_generated()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createCartWithItems($user, 1);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->validShippingData());

        // Assert: 訂單編號格式為 ORD-YYYYMMDD-XXXXX
        $response->assertStatus(201);
        $order_number = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{5}$/', $order_number);
    }

    /**
     * TC-ORDER-010: 不同運送方式的運費正確
     *
     * @test
     */
    public function shipping_fee_is_calculated_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createCartWithItems($user, 1, 10, 1000);
        $data = $this->validShippingData();
        $data['shipping_method'] = 'express';

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $data);

        // Assert: express 運費 120
        $response->assertStatus(201)
            ->assertJsonPath('data.shipping_fee', 120);
    }
}
