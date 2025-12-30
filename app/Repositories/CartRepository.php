<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;

/**
 * 購物車資料存取層
 * 只處理購物車相關的資料庫操作
 */
class CartRepository
{
    /**
     * 根據會員 ID 查找購物車
     *
     * @param int $user_id 會員 ID
     * @return Cart|null 購物車物件，不存在時返回 null
     */
    public function findByUserId(int $user_id): ?Cart
    {
        return Cart::with(['items.product.images'])
            ->forUser($user_id)
            ->first();
    }

    /**
     * 根據 Session ID 查找訪客購物車
     *
     * @param string $session_id 訪客 Session ID
     * @return Cart|null 購物車物件，不存在時返回 null
     */
    public function findBySessionId(string $session_id): ?Cart
    {
        return Cart::with(['items.product.images'])
            ->forSession($session_id)
            ->first();
    }

    /**
     * 取得或建立會員購物車
     * 如果會員已有購物車則返回，否則建立新的購物車
     *
     * @param int $user_id 會員 ID
     * @return Cart 購物車物件
     */
    public function getOrCreateForUser(int $user_id): Cart
    {
        // 先嘗試查找現有購物車
        $cart = $this->findByUserId($user_id);

        // 如果不存在則建立新的購物車
        if (!$cart) {
            $cart = Cart::create(['user_id' => $user_id]);
            // 重新載入關聯
            $cart->load(['items.product.images']);
        }

        return $cart;
    }

    /**
     * 取得或建立訪客購物車
     * 如果訪客已有購物車則返回，否則建立新的購物車
     *
     * @param string $session_id 訪客 Session ID
     * @return Cart 購物車物件
     */
    public function getOrCreateForSession(string $session_id): Cart
    {
        // 先嘗試查找現有購物車
        $cart = $this->findBySessionId($session_id);

        // 如果不存在則建立新的購物車
        if (!$cart) {
            $cart = Cart::create(['session_id' => $session_id]);
            // 重新載入關聯
            $cart->load(['items.product.images']);
        }

        return $cart;
    }

    /**
     * 根據 ID 查找購物車項目
     *
     * @param int $item_id 購物車項目 ID
     * @return CartItem|null 購物車項目物件，不存在時返回 null
     */
    public function findItemById(int $item_id): ?CartItem
    {
        return CartItem::with(['product.images', 'cart'])
            ->find($item_id);
    }

    /**
     * 查找購物車中的特定商品
     *
     * @param Cart $cart 購物車物件
     * @param int $product_id 商品 ID
     * @return CartItem|null 購物車項目物件，不存在時返回 null
     */
    public function findItemByProductId(Cart $cart, int $product_id): ?CartItem
    {
        return CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product_id)
            ->first();
    }

    /**
     * 新增購物車項目
     *
     * @param Cart $cart 購物車物件
     * @param array $data 項目資料 (product_id, quantity, price)
     * @return CartItem 新建立的購物車項目
     */
    public function addItem(Cart $cart, array $data): CartItem
    {
        // 建立購物車項目
        $item = $cart->items()->create([
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
        ]);

        // 載入商品關聯
        $item->load(['product.images']);

        return $item;
    }

    /**
     * 更新購物車項目數量
     *
     * @param CartItem $item 購物車項目物件
     * @param int $quantity 新的數量
     * @return CartItem 更新後的購物車項目
     */
    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->update(['quantity' => $quantity]);

        return $item->fresh(['product.images']);
    }

    /**
     * 移除購物車項目
     *
     * @param CartItem $item 購物車項目物件
     * @return bool 是否成功移除
     */
    public function removeItem(CartItem $item): bool
    {
        return $item->delete();
    }

    /**
     * 批量移除購物車項目
     *
     * @param array $item_ids 購物車項目 ID 陣列
     * @return int 成功刪除的項目數量
     */
    public function removeItemsByIds(array $item_ids): int
    {
        return CartItem::whereIn('id', $item_ids)->delete();
    }

    /**
     * 批量移除購物車中指定的商品
     *
     * @param Cart $cart 購物車物件
     * @param array $product_ids 商品 ID 陣列
     * @return int 成功刪除的項目數量
     */
    public function removeItemsByProductIds(Cart $cart, array $product_ids): int
    {
        return CartItem::where('cart_id', $cart->id)
            ->whereIn('product_id', $product_ids)
            ->delete();
    }

    /**
     * 清空購物車所有項目
     *
     * @param Cart $cart 購物車物件
     * @return int 刪除的項目數量
     */
    public function clearItems(Cart $cart): int
    {
        return $cart->items()->delete();
    }

    /**
     * 刪除購物車
     *
     * @param Cart $cart 購物車物件
     * @return bool 是否成功刪除
     */
    public function deleteCart(Cart $cart): bool
    {
        return $cart->delete();
    }

    /**
     * 將訪客購物車轉換為會員購物車
     * 用於登入後合併購物車
     *
     * @param Cart $cart 訪客購物車
     * @param int $user_id 會員 ID
     * @return Cart 更新後的購物車
     */
    public function convertToUserCart(Cart $cart, int $user_id): Cart
    {
        $cart->update([
            'user_id' => $user_id,
            'session_id' => null,
        ]);

        return $cart->fresh(['items.product.images']);
    }

    /**
     * 批量更新購物車項目
     * 用於購物車合併時增加數量
     *
     * @param CartItem $item 購物車項目物件
     * @param int $additional_quantity 要增加的數量
     * @return CartItem 更新後的購物車項目
     */
    public function incrementItemQuantity(CartItem $item, int $additional_quantity): CartItem
    {
        $item->increment('quantity', $additional_quantity);

        return $item->fresh(['product.images']);
    }

    /**
     * 將購物車項目移動到另一個購物車
     *
     * @param CartItem $item 購物車項目物件
     * @param Cart $target_cart 目標購物車
     * @return CartItem 更新後的購物車項目
     */
    public function moveItemToCart(CartItem $item, Cart $target_cart): CartItem
    {
        $item->update(['cart_id' => $target_cart->id]);

        return $item->fresh(['product.images', 'cart']);
    }
}
