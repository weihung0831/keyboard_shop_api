<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 購物車服務層
 * 處理購物車相關商業邏輯
 */
class CartService
{
    /**
     * 購物車資料存取層
     */
    private CartRepository $cart_repository;

    /**
     * 產品資料存取層
     */
    private ProductRepository $product_repository;

    /**
     * 建構函式
     */
    public function __construct(
        CartRepository $cart_repository,
        ProductRepository $product_repository
    ) {
        $this->cart_repository = $cart_repository;
        $this->product_repository = $product_repository;
    }

    /**
     * 取得購物車
     * 根據會員 ID 或 Session ID 取得購物車
     *
     * @param int|null $user_id 會員 ID
     * @param string|null $session_id 訪客 Session ID
     * @return Cart 購物車物件
     * @throws InvalidArgumentException 當兩者皆為空時
     */
    public function getCart(?int $user_id, ?string $session_id): Cart
    {
        // 驗證至少有一個識別參數
        if ($user_id === null && $session_id === null) {
            throw new InvalidArgumentException('必須提供 user_id 或 session_id');
        }

        // 優先使用會員 ID
        if ($user_id !== null) {
            return $this->cart_repository->getOrCreateForUser($user_id);
        }

        return $this->cart_repository->getOrCreateForSession($session_id);
    }

    /**
     * 加入購物車
     *
     * @param int|null $user_id 會員 ID
     * @param string|null $session_id 訪客 Session ID
     * @param int $product_id 商品 ID
     * @param int $quantity 數量
     * @return Cart 更新後的購物車
     * @throws InvalidArgumentException 當商品不存在、未上架或庫存不足時
     */
    public function addItem(?int $user_id, ?string $session_id, int $product_id, int $quantity): Cart
    {
        // 驗證數量
        if ($quantity < 1) {
            throw new InvalidArgumentException('數量必須大於 0');
        }

        // 取得商品並驗證
        $product = $this->product_repository->findById($product_id);
        if (!$product) {
            throw new InvalidArgumentException('商品不存在或已下架');
        }

        // 取得購物車
        $cart = $this->getCart($user_id, $session_id);

        // 檢查商品是否已在購物車中
        $existing_item = $this->cart_repository->findItemByProductId($cart, $product_id);

        if ($existing_item) {
            // 商品已存在，累加數量
            $new_quantity = $existing_item->quantity + $quantity;

            // 驗證庫存
            if ($new_quantity > $product->stock) {
                throw new InvalidArgumentException(
                    "庫存不足，目前庫存：{$product->stock}，購物車已有：{$existing_item->quantity}"
                );
            }

            $this->cart_repository->updateItemQuantity($existing_item, $new_quantity);
        } else {
            // 新商品，驗證庫存
            if ($quantity > $product->stock) {
                throw new InvalidArgumentException("庫存不足，目前庫存：{$product->stock}");
            }

            // 新增購物車項目（儲存當時價格作為快照）
            $this->cart_repository->addItem($cart, [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $product->price,
            ]);
        }

        // 重新載入購物車
        return $cart->fresh(['items.product.images']);
    }

    /**
     * 更新購物車項目數量
     *
     * @param int $item_id 購物車項目 ID
     * @param int $quantity 新數量
     * @param int|null $user_id 會員 ID（用於權限驗證）
     * @param string|null $session_id 訪客 Session ID（用於權限驗證）
     * @return Cart 更新後的購物車
     * @throws InvalidArgumentException 當項目不存在、無權限或庫存不足時
     */
    public function updateItemQuantity(int $item_id, int $quantity, ?int $user_id, ?string $session_id): Cart
    {
        // 驗證數量
        if ($quantity < 1) {
            throw new InvalidArgumentException('數量必須大於 0');
        }

        // 取得購物車項目
        $item = $this->cart_repository->findItemById($item_id);
        if (!$item) {
            throw new InvalidArgumentException('購物車項目不存在');
        }

        // 驗證權限（項目是否屬於該購物車）
        $cart = $item->cart;
        if (!$this->verifyCartOwnership($cart, $user_id, $session_id)) {
            throw new InvalidArgumentException('無權限修改此購物車項目');
        }

        // 取得商品並驗證庫存
        $product = $this->product_repository->findById($item->product_id);
        if (!$product) {
            throw new InvalidArgumentException('商品不存在或已下架');
        }

        if ($quantity > $product->stock) {
            throw new InvalidArgumentException("庫存不足，目前庫存：{$product->stock}");
        }

        // 更新數量
        $this->cart_repository->updateItemQuantity($item, $quantity);

        // 重新載入購物車
        return $cart->fresh(['items.product.images']);
    }

    /**
     * 移除購物車項目
     *
     * @param int $item_id 購物車項目 ID
     * @param int|null $user_id 會員 ID（用於權限驗證）
     * @param string|null $session_id 訪客 Session ID（用於權限驗證）
     * @return Cart 更新後的購物車
     * @throws InvalidArgumentException 當項目不存在或無權限時
     */
    public function removeItem(int $item_id, ?int $user_id, ?string $session_id): Cart
    {
        // 取得購物車項目
        $item = $this->cart_repository->findItemById($item_id);
        if (!$item) {
            throw new InvalidArgumentException('購物車項目不存在');
        }

        // 驗證權限
        $cart = $item->cart;
        if (!$this->verifyCartOwnership($cart, $user_id, $session_id)) {
            throw new InvalidArgumentException('無權限修改此購物車項目');
        }

        // 移除項目
        $this->cart_repository->removeItem($item);

        // 重新載入購物車
        return $cart->fresh(['items.product.images']);
    }

    /**
     * 批量移除購物車項目
     *
     * @param array $item_ids 購物車項目 ID 陣列
     * @param int|null $user_id 會員 ID
     * @param string|null $session_id 訪客 Session ID
     * @return Cart 更新後的購物車
     * @throws InvalidArgumentException 當項目不存在或無權限時
     */
    public function removeItems(array $item_ids, ?int $user_id, ?string $session_id): Cart
    {
        // 取得購物車
        $cart = $this->getCart($user_id, $session_id);

        // 驗證所有項目都屬於該購物車
        $cart_item_ids = $cart->items->pluck('id')->toArray();
        foreach ($item_ids as $item_id) {
            if (!in_array($item_id, $cart_item_ids)) {
                throw new InvalidArgumentException("購物車項目 {$item_id} 不存在或無權限移除");
            }
        }

        // 批量移除
        $this->cart_repository->removeItemsByIds($item_ids);

        // 重新載入購物車
        return $cart->fresh(['items.product.images']);
    }

    /**
     * 清空購物車
     *
     * @param int|null $user_id 會員 ID
     * @param string|null $session_id 訪客 Session ID
     * @return bool 是否成功清空
     */
    public function clearCart(?int $user_id, ?string $session_id): bool
    {
        $cart = $this->getCart($user_id, $session_id);
        $this->cart_repository->clearItems($cart);

        return true;
    }

    /**
     * 合併購物車（訪客登入時）
     * 將訪客購物車的商品合併到會員購物車
     *
     * @param string $session_id 訪客 Session ID
     * @param int $user_id 會員 ID
     * @return Cart 合併後的會員購物車
     */
    public function mergeCarts(string $session_id, int $user_id): Cart
    {
        return DB::transaction(function () use ($session_id, $user_id) {
            // 取得訪客購物車
            $guest_cart = $this->cart_repository->findBySessionId($session_id);

            // 如果訪客購物車不存在或為空，直接返回會員購物車
            if (!$guest_cart || $guest_cart->items->isEmpty()) {
                return $this->cart_repository->getOrCreateForUser($user_id);
            }

            // 取得會員購物車
            $user_cart = $this->cart_repository->getOrCreateForUser($user_id);

            // 合併每個訪客購物車項目
            foreach ($guest_cart->items as $guest_item) {
                // 檢查商品是否仍然有效
                $product = $this->product_repository->findById($guest_item->product_id);
                if (!$product) {
                    continue; // 跳過已下架商品
                }

                // 檢查會員購物車是否已有相同商品
                $existing_item = $this->cart_repository->findItemByProductId(
                    $user_cart,
                    $guest_item->product_id
                );

                if ($existing_item) {
                    // 累加數量，但不超過庫存
                    $new_quantity = min(
                        $existing_item->quantity + $guest_item->quantity,
                        $product->stock
                    );
                    $this->cart_repository->updateItemQuantity($existing_item, $new_quantity);
                } else {
                    // 新增商品到會員購物車，數量不超過庫存
                    $quantity = min($guest_item->quantity, $product->stock);
                    if ($quantity > 0) {
                        $this->cart_repository->addItem($user_cart, [
                            'product_id' => $guest_item->product_id,
                            'quantity' => $quantity,
                            'price' => $product->price, // 使用最新價格
                        ]);
                    }
                }
            }

            // 刪除訪客購物車
            $this->cart_repository->deleteCart($guest_cart);

            // 重新載入並返回會員購物車
            return $user_cart->fresh(['items.product.images']);
        });
    }

    /**
     * 驗證購物車所有權
     *
     * @param Cart $cart 購物車物件
     * @param int|null $user_id 會員 ID
     * @param string|null $session_id 訪客 Session ID
     * @return bool 是否為該購物車的擁有者
     */
    private function verifyCartOwnership(Cart $cart, ?int $user_id, ?string $session_id): bool
    {
        // 會員購物車驗證
        if ($cart->user_id !== null) {
            return $cart->user_id === $user_id;
        }

        // 訪客購物車驗證
        if ($cart->session_id !== null) {
            return $cart->session_id === $session_id;
        }

        return false;
    }

    /**
     * 驗證購物車庫存
     * 檢查購物車中所有商品的庫存是否足夠
     *
     * @param Cart $cart 購物車物件
     * @return array 庫存不足的商品列表（空陣列表示全部充足）
     */
    public function validateCartStock(Cart $cart): array
    {
        $insufficient_items = [];

        foreach ($cart->items as $item) {
            $product = $this->product_repository->findById($item->product_id);

            if (!$product) {
                // 商品已下架
                $insufficient_items[] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? '未知商品',
                    'requested' => $item->quantity,
                    'available' => 0,
                    'reason' => '商品已下架',
                ];
            } elseif ($item->quantity > $product->stock) {
                // 庫存不足
                $insufficient_items[] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $product->name,
                    'requested' => $item->quantity,
                    'available' => $product->stock,
                    'reason' => '庫存不足',
                ];
            }
        }

        return $insufficient_items;
    }

    /**
     * 同步購物車價格
     * 將購物車中所有商品的價格更新為最新價格
     *
     * @param Cart $cart 購物車物件
     * @return Cart 更新後的購物車
     */
    public function syncCartPrices(Cart $cart): Cart
    {
        foreach ($cart->items as $item) {
            $product = $this->product_repository->findById($item->product_id);

            if ($product && $item->price != $product->price) {
                $item->update(['price' => $product->price]);
            }
        }

        return $cart->fresh(['items.product.images']);
    }
}
