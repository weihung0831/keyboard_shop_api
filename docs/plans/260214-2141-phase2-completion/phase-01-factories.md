# 階段 1：建立 Factory

> 父計畫：[plan.md](plan.md)
> 相依：無（此階段為基礎）
> 被依賴：階段 3（購物車測試）、階段 4（訂單測試）

## 概述

- 日期：2026-02-14
- 說明：建立 Cart、CartItem、Order、OrderItem 四個 Factory，供測試使用
- 優先級：P1
- 實作狀態：completed
- 審查狀態：completed

## 重點

- 參考 `ProductFactory` 的 `state()` 模式
- Factory 需正確設定模型關聯
- OrderFactory 需支援 5 種訂單狀態
- 使用 `php artisan make:factory` 建立基礎檔案

## 需求

1. CartFactory：支援會員/訪客兩種模式
2. CartItemFactory：自動關聯 Cart + Product，含價格快照
3. OrderFactory：自動產生訂單編號，支援各狀態 state
4. OrderItemFactory：含商品快照欄位（product_name、product_sku）

## 相關檔案

| 檔案 | 用途 |
|------|------|
| `database/factories/ProductFactory.php` | Factory 模式參考 |
| `database/migrations/2025_12_30_000001_create_carts_table.php` | Cart 欄位結構 |
| `database/migrations/2025_12_30_000002_create_cart_items_table.php` | CartItem 欄位結構 |
| `database/migrations/2025_12_30_000003_create_orders_table.php` | Order 欄位結構 |
| `database/migrations/2025_12_30_000004_create_order_items_table.php` | OrderItem 欄位結構 |

## 實作步驟

### 1.1 建立 CartFactory

```bash
php artisan make:factory CartFactory --no-interaction
```

**定義內容：**
- `definition()`：預設建立會員購物車（自動建立 User）
- `withUser(User $user)`：指定會員
- `forGuest()`：訪客模式（使用 session_id）

**欄位對應：**
| 欄位 | 值 |
|------|-----|
| user_id | User::factory() |
| session_id | null |

### 1.2 建立 CartItemFactory

```bash
php artisan make:factory CartItemFactory --no-interaction
```

**定義內容：**
- `definition()`：自動關聯 Cart + Product，隨機數量 1-5
- `withQuantity(int $qty)`：指定數量

**欄位對應：**
| 欄位 | 值 |
|------|-----|
| cart_id | Cart::factory() |
| product_id | Product::factory() |
| quantity | fake()->numberBetween(1, 5) |
| price | fn() => Product price（從關聯取） |

### 1.3 建立 OrderFactory

```bash
php artisan make:factory OrderFactory --no-interaction
```

**定義內容：**
- `definition()`：預設 pending 狀態，自動產生收件資訊
- `pending()`：待付款狀態
- `processing()`：處理中（含 paid_at）
- `shipped()`：已出貨（含 paid_at + shipped_at）
- `completed()`：已完成（含所有時間戳）
- `cancelled()`：已取消（含 cancelled_at）

**欄位對應：**
| 欄位 | 值 |
|------|-----|
| user_id | User::factory() |
| order_number | Order::generateOrderNumber() |
| status | Order::STATUS_PENDING |
| subtotal | fake()->randomFloat(2, 100, 10000) |
| shipping_fee | 60 |
| total_amount | subtotal + shipping_fee |
| shipping_name | fake()->name() |
| shipping_phone | '09' . fake()->numerify('##-###-###') |
| shipping_email | fake()->email() |
| shipping_postal_code | fake()->postcode() |
| shipping_city | fake()->city() |
| shipping_address | fake()->address() |
| shipping_method | 'standard' |

### 1.4 建立 OrderItemFactory

```bash
php artisan make:factory OrderItemFactory --no-interaction
```

**定義內容：**
- `definition()`：自動關聯 Order + Product，含商品快照

**欄位對應：**
| 欄位 | 值 |
|------|-----|
| order_id | Order::factory() |
| product_id | Product::factory() |
| product_name | fake()->words(3, true) |
| product_sku | fake()->unique()->ean8() |
| quantity | fake()->numberBetween(1, 5) |
| price | fake()->randomFloat(2, 100, 5000) |
| subtotal | quantity * price |

## 待辦清單

- [x] 建立 CartFactory
- [x] 建立 CartItemFactory
- [x] 建立 OrderFactory（含 5 種狀態 state）
- [x] 建立 OrderItemFactory
- [x] 確認所有 Factory 可正常執行

## 成功標準

- 4 個 Factory 都可透過 `Model::factory()->create()` 正常建立資料
- OrderFactory 的每種狀態 state 都能正確設定對應欄位
- CartFactory 的會員/訪客模式都能正常運作

## 風險評估

- 低風險：Factory 是獨立的測試輔助工具，不影響正式程式碼
- 注意 CartItemFactory 的 price 欄位需與 Product price 一致

## 下一步

完成後進入階段 2（庫存扣減邏輯）
