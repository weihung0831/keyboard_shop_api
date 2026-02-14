<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
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
     * 建立訪客購物車含商品
     */
    private function createGuestCartWithItem(string $session_id, Product $product, int $qty = 1): Cart
    {
        $cart = Cart::factory()->forGuest()->create(['session_id' => $session_id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'price' => $product->price,
        ]);

        return $cart->fresh(['items.product']);
    }

    /**
     * 建立會員購物車含商品
     */
    private function createMemberCartWithItem(User $user, Product $product, int $qty = 1): Cart
    {
        $cart = Cart::factory()->withUser($user)->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'price' => $product->price,
        ]);

        return $cart->fresh(['items.product']);
    }

    // ========================================
    // 取得購物車（3 個）
    // ========================================

    /**
     * TC-CART-001: 訪客無購物車時返回空結構
     *
     * @test
     */
    public function guest_can_get_empty_cart()
    {
        // Arrange
        $session_id = 'test-session-001';

        // Act
        $response = $this->getJson('/api/v1/cart', [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.total_amount', 0);
    }

    /**
     * TC-CART-002: 訪客取得含商品的購物車
     *
     * @test
     */
    public function guest_can_get_cart_with_items()
    {
        // Arrange
        $session_id = 'test-session-002';
        $product = $this->createProductWithStock(10);
        $this->createGuestCartWithItem($session_id, $product, 2);

        // Act
        $response = $this->getJson('/api/v1/cart', [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonCount(1, 'data.items');
    }

    /**
     * TC-CART-003: 會員取得購物車
     *
     * @test
     */
    public function member_can_get_cart()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $this->createMemberCartWithItem($user, $product, 3);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/cart');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 3)
            ->assertJsonCount(1, 'data.items');
    }

    // ========================================
    // 加入購物車（5 個）
    // ========================================

    /**
     * TC-CART-004: 訪客加入商品
     *
     * @test
     */
    public function guest_can_add_item_to_cart()
    {
        // Arrange
        $session_id = 'test-session-004';
        $product = $this->createProductWithStock(10);

        // Act
        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('message', '商品已加入購物車')
            ->assertJsonPath('data.total_items', 2);
    }

    /**
     * TC-CART-005: 會員加入商品
     *
     * @test
     */
    public function member_can_add_item_to_cart()
    {
        // Arrange
        $user = User::factory()->create();
        $product = $this->createProductWithStock(10);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('message', '商品已加入購物車')
            ->assertJsonPath('data.total_items', 1);
    }

    /**
     * TC-CART-006: 加入相同商品累加數量
     *
     * @test
     */
    public function adding_existing_item_increments_quantity()
    {
        // Arrange
        $session_id = 'test-session-006';
        $product = $this->createProductWithStock(10);
        $this->createGuestCartWithItem($session_id, $product, 2);

        // Act: 再加入 3 個相同商品
        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert: 數量應為 2 + 3 = 5
        $response->assertStatus(201)
            ->assertJsonPath('data.total_items', 5);
    }

    /**
     * TC-CART-007: 加入不存在商品失敗
     *
     * @test
     */
    public function cannot_add_nonexistent_product()
    {
        // Arrange
        $session_id = 'test-session-007';

        // Act
        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => 99999,
            'quantity' => 1,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    /**
     * TC-CART-008: 超過庫存數量失敗
     *
     * @test
     */
    public function cannot_add_item_exceeding_stock()
    {
        // Arrange
        $session_id = 'test-session-008';
        $product = $this->createProductWithStock(5);

        // Act: 嘗試加入 10 個（庫存只有 5）
        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 10,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    // ========================================
    // 更新購物車（4 個）
    // ========================================

    /**
     * TC-CART-009: 正常更新數量
     *
     * @test
     */
    public function can_update_cart_item_quantity()
    {
        // Arrange
        $session_id = 'test-session-009';
        $product = $this->createProductWithStock(10);
        $cart = $this->createGuestCartWithItem($session_id, $product, 1);
        $item = $cart->items->first();

        // Act: 更新數量為 5
        $response = $this->putJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 5,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 5);
    }

    /**
     * TC-CART-010: 更新超過庫存失敗
     *
     * @test
     */
    public function cannot_update_quantity_exceeding_stock()
    {
        // Arrange
        $session_id = 'test-session-010';
        $product = $this->createProductWithStock(5);
        $cart = $this->createGuestCartWithItem($session_id, $product, 1);
        $item = $cart->items->first();

        // Act: 嘗試更新數量為 10（庫存只有 5）
        $response = $this->putJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 10,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    /**
     * TC-CART-011: 更新不存在項目失敗
     *
     * @test
     */
    public function cannot_update_nonexistent_item()
    {
        // Arrange
        $session_id = 'test-session-011';

        // Act
        $response = $this->putJson('/api/v1/cart/items/99999', [
            'quantity' => 1,
        ], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    /**
     * TC-CART-012: 無法更新他人的購物車項目
     *
     * @test
     */
    public function cannot_update_other_users_cart_item()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $cart = $this->createMemberCartWithItem($user1, $product, 1);
        $item = $cart->items->first();

        // Act: user2 嘗試更新 user1 的購物車項目
        $response = $this->actingAs($user2, 'sanctum')
            ->putJson("/api/v1/cart/items/{$item->id}", [
                'quantity' => 5,
            ]);

        // Assert
        $response->assertStatus(422);
    }

    // ========================================
    // 移除購物車項目（3 個）
    // ========================================

    /**
     * TC-CART-013: 正常移除項目
     *
     * @test
     */
    public function can_remove_cart_item()
    {
        // Arrange
        $session_id = 'test-session-013';
        $product = $this->createProductWithStock(10);
        $cart = $this->createGuestCartWithItem($session_id, $product, 1);
        $item = $cart->items->first();

        // Act
        $response = $this->deleteJson("/api/v1/cart/items/{$item->id}", [], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 0);
    }

    /**
     * TC-CART-014: 移除不存在項目失敗
     *
     * @test
     */
    public function cannot_remove_nonexistent_item()
    {
        // Arrange
        $session_id = 'test-session-014';

        // Act
        $response = $this->deleteJson('/api/v1/cart/items/99999', [], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(422);
    }

    /**
     * TC-CART-015: 無法移除他人的購物車項目
     *
     * @test
     */
    public function cannot_remove_other_users_cart_item()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = $this->createProductWithStock(10);
        $cart = $this->createMemberCartWithItem($user1, $product, 1);
        $item = $cart->items->first();

        // Act: user2 嘗試移除 user1 的購物車項目
        $response = $this->actingAs($user2, 'sanctum')
            ->deleteJson("/api/v1/cart/items/{$item->id}");

        // Assert
        $response->assertStatus(422);
    }

    // ========================================
    // 清空購物車（2 個）
    // ========================================

    /**
     * TC-CART-016: 清空購物車後項目為空
     *
     * @test
     */
    public function can_clear_cart()
    {
        // Arrange
        $session_id = 'test-session-016';
        $product = $this->createProductWithStock(10);
        $this->createGuestCartWithItem($session_id, $product, 3);

        // Act
        $response = $this->deleteJson('/api/v1/cart', [], [
            'X-Session-Id' => $session_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.items', []);
    }

    /**
     * TC-CART-017: 無識別資訊時返回空購物車
     *
     * @test
     */
    public function returns_empty_cart_without_identification()
    {
        // Arrange: 不提供任何識別資訊

        // Act
        $response = $this->getJson('/api/v1/cart');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.items', []);
    }

    // ========================================
    // 合併購物車（4 個）
    // ========================================

    /**
     * TC-CART-018: 登入後合併訪客購物車
     *
     * @test
     */
    public function can_merge_guest_cart_to_member_cart()
    {
        // Arrange
        $user = User::factory()->create();
        $session_id = 'test-session-018';
        $product = $this->createProductWithStock(10);

        // 訪客購物車有 2 個商品
        $this->createGuestCartWithItem($session_id, $product, 2);

        // Act: 登入後合併
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [], [
                'X-Session-Id' => $session_id,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 2);
    }

    /**
     * TC-CART-019: 合併時相同商品累加數量
     *
     * @test
     */
    public function merge_increments_quantity_for_same_product()
    {
        // Arrange
        $user = User::factory()->create();
        $session_id = 'test-session-019';
        $product = $this->createProductWithStock(20);

        // 訪客購物車有 3 個
        $this->createGuestCartWithItem($session_id, $product, 3);

        // 會員購物車已有 2 個
        $this->createMemberCartWithItem($user, $product, 2);

        // Act: 合併後應為 2 + 3 = 5
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [], [
                'X-Session-Id' => $session_id,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.total_items', 5);
    }

    /**
     * TC-CART-020: 合併後數量不超過庫存上限
     *
     * @test
     */
    public function merge_does_not_exceed_stock_limit()
    {
        // Arrange
        $user = User::factory()->create();
        $session_id = 'test-session-020';
        $product = $this->createProductWithStock(5);

        // 訪客購物車有 4 個
        $this->createGuestCartWithItem($session_id, $product, 4);

        // 會員購物車已有 3 個
        $this->createMemberCartWithItem($user, $product, 3);

        // Act: 合併後應為 min(3 + 4, 5) = 5（不超過庫存）
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [], [
                'X-Session-Id' => $session_id,
            ]);

        // Assert: 數量不超過庫存上限
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->json('data.total_items'));
    }

    /**
     * TC-CART-021: 合併後訪客購物車被刪除
     *
     * @test
     */
    public function merge_deletes_guest_cart()
    {
        // Arrange
        $user = User::factory()->create();
        $session_id = 'test-session-021';
        $product = $this->createProductWithStock(10);
        $guest_cart = $this->createGuestCartWithItem($session_id, $product, 2);

        // Act: 合併
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [], [
                'X-Session-Id' => $session_id,
            ]);

        // Assert: 訪客購物車已被刪除
        $this->assertDatabaseMissing('carts', ['id' => $guest_cart->id]);
    }
}
