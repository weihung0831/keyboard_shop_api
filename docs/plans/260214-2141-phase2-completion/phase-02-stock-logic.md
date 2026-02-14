# 階段 2：庫存扣減邏輯

> 父計畫：[plan.md](plan.md)
> 相依：無（可與階段 1 平行）
> 被依賴：階段 4（訂單測試 - 庫存斷言）

## 概述

- 日期：2026-02-14
- 說明：在 OrderService 中加入下單扣庫存、取消回補庫存的邏輯
- 優先級：P1
- 實作狀態：completed
- 審查狀態：completed

## 重點

- 庫存扣減策略：下單即扣
- 取消時自動回補庫存
- 所有操作都在 DB Transaction 內（已有 Transaction）
- 暫不處理並發競爭

## 需求

1. `createOrder()`：建立訂單項目後，扣減每個商品的庫存
2. `cancelOrder()`：取消訂單時，回補每個商品的庫存

## 相關檔案

| 檔案 | 用途 |
|------|------|
| `app/Services/OrderService.php` | **需修改** - 主要修改目標 |
| `app/Models/Product.php` | 使用 decrement/increment 方法 |
| `app/Models/Order.php` | 訂單狀態常數 |

## 實作步驟

### 2.1 修改 `createOrder()` - 加入庫存扣減

**位置：** `app/Services/OrderService.php` 第 105-117 行附近（建立訂單項目的 foreach 迴圈後）

**在建立訂單項目的迴圈中加入扣減：**

```php
// 現有：建立訂單項目（儲存商品快照）
foreach ($cart->items as $cart_item) {
    $product = $cart_item->product;
    $order_items[] = [
        'product_id' => $cart_item->product_id,
        'product_name' => $product->name,
        'product_sku' => $product->sku ?? '',
        'quantity' => $cart_item->quantity,
        'price' => $cart_item->price,
    ];

    // 新增：扣減庫存
    $product->decrement('stock', $cart_item->quantity);
}
```

### 2.2 修改 `cancelOrder()` - 加入庫存回補

**位置：** `app/Services/OrderService.php` 的 `cancelOrder()` 方法

**在取消訂單前加入回補邏輯：**

```php
public function cancelOrder(int $order_id, int $user_id): Order
{
    $order = $this->order_repository->findById($order_id, $user_id);

    if (!$order) {
        throw new InvalidArgumentException('訂單不存在或無權限操作');
    }

    if (!$order->canBeCancelled()) {
        throw new InvalidArgumentException('僅待付款訂單可取消');
    }

    // 新增：回補庫存（需在 Transaction 內）
    return DB::transaction(function () use ($order) {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::where('id', $item->product_id)
                    ->increment('stock', $item->quantity);
            }
        }

        return $this->order_repository->cancel($order);
    });
}
```

**注意：** 需在檔案頂部新增 `use App\Models\Product;` 和 `use Illuminate\Support\Facades\DB;`（DB 可能已有引入）

## 待辦清單

- [x] 修改 `createOrder()` 加入庫存扣減
- [x] 修改 `cancelOrder()` 加入庫存回補（含 DB Transaction）
- [x] 確認 import 語句完整
- [x] 手動驗證邏輯正確性

## 成功標準

- 建立訂單後，對應商品的 stock 欄位正確減少
- 取消訂單後，對應商品的 stock 欄位正確回補
- 所有操作都在 Transaction 內，確保原子性
- 商品已刪除時（product_id 為 null），跳過回補不報錯

## 風險評估

- 中風險：修改核心商業邏輯，需確保 Transaction 涵蓋完整
- `cancelOrder()` 原本沒有 Transaction 包覆，需新增
- 需確認 `order->items` 關聯已被載入，避免 N+1

## 安全考量

- 庫存不會變成負數（因建立訂單前已驗證庫存充足）
- Transaction 確保扣減與訂單建立的原子性
- 未來可加入 `where('stock', '>=', $qty)` 防止並發超賣

## 下一步

完成後進入階段 3（購物車測試）
