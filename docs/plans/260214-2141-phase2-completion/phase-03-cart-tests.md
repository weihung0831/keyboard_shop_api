# 階段 3：購物車測試

> 父計畫：[plan.md](plan.md)
> 相依：階段 1（Factory）
> 被依賴：階段 5（驗證與收尾）

## 概述

- 日期：2026-02-14
- 說明：建立購物車模組完整測試，約 21 個測試案例
- 優先級：P1
- 實作狀態：completed
- 審查狀態：completed

## 重點

- 購物車支援會員和訪客兩種身份，需分別測試
- 訪客使用 `X-Session-Id` Header 識別
- 會員使用 Sanctum Token 認證
- 合併功能需登入（auth:sanctum middleware）

## 需求

- 涵蓋所有 6 個 API 端點的正常與錯誤路徑
- 遵循現有測試風格：PHPUnit + RefreshDatabase + AAA + TC 編號

## 架構

### API 端點對應

| 方法 | 路由 | 認證 | 功能 |
|------|------|------|------|
| GET | /api/v1/cart | 選擇性 | 取得購物車 |
| POST | /api/v1/cart/items | 選擇性 | 加入購物車 |
| PUT | /api/v1/cart/items/{id} | 選擇性 | 更新數量 |
| DELETE | /api/v1/cart/items/{id} | 選擇性 | 移除項目 |
| DELETE | /api/v1/cart | 選擇性 | 清空購物車 |
| POST | /api/v1/cart/merge | 必須 | 合併購物車 |

## 相關檔案

| 檔案 | 用途 |
|------|------|
| `tests/Feature/Cart/CartTest.php` | **需建立** - 測試檔案 |
| `app/Http/Controllers/Cart/CartController.php` | 被測試的控制器 |
| `app/Services/CartService.php` | 商業邏輯參考 |
| `tests/Feature/Auth/LoginTest.php` | 測試風格參考 |

## 實作步驟

### 3.1 建立測試檔案

```bash
php artisan make:test Cart/CartTest --phpunit --no-interaction
```

### 3.2 測試案例清單

#### 取得購物車（3 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-001 | `guest_can_get_empty_cart` | 訪客無購物車時返回空結構 |
| TC-CART-002 | `guest_can_get_cart_with_items` | 訪客取得含商品的購物車 |
| TC-CART-003 | `member_can_get_cart` | 會員取得購物車 |

#### 加入購物車（5 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-004 | `guest_can_add_item_to_cart` | 訪客加入商品 |
| TC-CART-005 | `member_can_add_item_to_cart` | 會員加入商品 |
| TC-CART-006 | `adding_existing_item_increments_quantity` | 加入相同商品累加數量 |
| TC-CART-007 | `cannot_add_nonexistent_product` | 加入不存在商品失敗（422） |
| TC-CART-008 | `cannot_add_item_exceeding_stock` | 超過庫存數量失敗（422） |

#### 更新購物車（4 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-009 | `can_update_cart_item_quantity` | 正常更新數量 |
| TC-CART-010 | `cannot_update_quantity_exceeding_stock` | 更新超過庫存失敗 |
| TC-CART-011 | `cannot_update_nonexistent_item` | 更新不存在項目失敗 |
| TC-CART-012 | `cannot_update_other_users_cart_item` | 無法更新他人的購物車項目 |

#### 移除購物車項目（3 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-013 | `can_remove_cart_item` | 正常移除項目 |
| TC-CART-014 | `cannot_remove_nonexistent_item` | 移除不存在項目失敗 |
| TC-CART-015 | `cannot_remove_other_users_cart_item` | 無法移除他人的購物車項目 |

#### 清空購物車（2 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-016 | `can_clear_cart` | 清空購物車後項目為空 |
| TC-CART-017 | `returns_empty_cart_without_identification` | 無識別資訊時返回空購物車 |

#### 合併購物車（4 個）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-CART-018 | `can_merge_guest_cart_to_member_cart` | 登入後合併訪客購物車 |
| TC-CART-019 | `merge_increments_quantity_for_same_product` | 合併時相同商品累加數量 |
| TC-CART-020 | `merge_does_not_exceed_stock_limit` | 合併後數量不超過庫存上限 |
| TC-CART-021 | `merge_deletes_guest_cart` | 合併後訪客購物車被刪除 |

### 3.3 測試輔助方法

建立 `private` 輔助方法減少重複：

```php
private function createProductWithStock(int $stock = 10): Product
private function createGuestCartWithItem(string $sessionId, Product $product, int $qty = 1): Cart
private function createMemberCartWithItem(User $user, Product $product, int $qty = 1): Cart
```

## 待辦清單

- [x] 建立 CartTest.php
- [x] 實作取得購物車測試（3 個）
- [x] 實作加入購物車測試（5 個）
- [x] 實作更新購物車測試（4 個）
- [x] 實作移除購物車項目測試（3 個）
- [x] 實作清空購物車測試（2 個）
- [x] 實作合併購物車測試（4 個）
- [x] 全部 21 個測試通過

## 成功標準

- 21 個測試全部通過
- 涵蓋會員與訪客兩種身份
- 涵蓋正常路徑、錯誤路徑、權限驗證

## 風險評估

- 購物車 API 的認證方式較特殊（手動嘗試 Sanctum），測試時需注意 Header 設定
- 合併功能需同時準備訪客購物車和會員購物車，測試資料準備較複雜

## 下一步

完成後進入階段 4（訂單測試）或與階段 4 平行進行
