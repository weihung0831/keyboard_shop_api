# 階段 4：訂單測試

> 父計畫：[plan.md](plan.md)
> 相依：階段 1（Factory）、階段 2（庫存邏輯）
> 被依賴：階段 5（驗證與收尾）

## 概述

- 日期：2026-02-14
- 說明：建立訂單模組完整測試，約 25 個測試案例，拆分為 3 個測試檔案
- 優先級：P1
- 實作狀態：completed
- 審查狀態：completed

## 重點

- 訂單 API 全部需要認證（auth:sanctum）
- 建立訂單從購物車轉換，需先準備購物車資料
- 庫存扣減/回補測試依賴階段 2 的邏輯修改
- 訂單詳情包含時間軸（timeline）資料

## 需求

- 涵蓋 5 個 API 端點的正常與錯誤路徑
- 驗證庫存扣減和回補的正確性
- 遵循現有測試風格

## 架構

### API 端點對應

| 方法 | 路由 | 功能 |
|------|------|------|
| POST | /api/v1/orders | 建立訂單 |
| GET | /api/v1/orders | 訂單列表 |
| GET | /api/v1/orders/stats | 訂單統計 |
| GET | /api/v1/orders/{id} | 訂單詳情 |
| PUT | /api/v1/orders/{id}/cancel | 取消訂單 |

## 相關檔案

| 檔案 | 用途 |
|------|------|
| `tests/Feature/Order/OrderCreateTest.php` | **需建立** |
| `tests/Feature/Order/OrderQueryTest.php` | **需建立** |
| `tests/Feature/Order/OrderCancelTest.php` | **需建立** |
| `app/Http/Controllers/Order/OrderController.php` | 被測試的控制器 |
| `app/Services/OrderService.php` | 商業邏輯參考 |
| `app/Http/Requests/Order/CreateOrderRequest.php` | 驗證規則參考 |

## 實作步驟

### 4.1 建立測試檔案

```bash
php artisan make:test Order/OrderCreateTest --phpunit --no-interaction
php artisan make:test Order/OrderQueryTest --phpunit --no-interaction
php artisan make:test Order/OrderCancelTest --phpunit --no-interaction
```

### 4.2 OrderCreateTest.php（10 個測試）

#### 測試輔助方法

```php
private function validShippingData(): array  // 返回有效收件資訊
private function createCartWithItems(User $user, int $itemCount = 1): Cart  // 建立含商品的購物車
```

#### 測試案例

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-ORDER-001 | `can_create_order_from_cart` | 成功從購物車建立訂單，驗證回傳結構 |
| TC-ORDER-002 | `cart_is_cleared_after_order_creation` | 建立訂單後購物車被清空 |
| TC-ORDER-003 | `stock_is_deducted_after_order_creation` | 建立訂單後商品庫存正確扣減 |
| TC-ORDER-004 | `cannot_create_order_with_empty_cart` | 購物車為空時返回 400 |
| TC-ORDER-005 | `cannot_create_order_with_insufficient_stock` | 庫存不足時返回 422 |
| TC-ORDER-006 | `unauthenticated_user_cannot_create_order` | 未登入返回 401 |
| TC-ORDER-007 | `validates_required_shipping_fields` | 缺少必填收件欄位返回 422 |
| TC-ORDER-008 | `validates_phone_number_format` | 手機號碼格式錯誤返回 422 |
| TC-ORDER-009 | `order_number_is_auto_generated` | 訂單編號自動產生且格式正確 |
| TC-ORDER-010 | `shipping_fee_is_calculated_correctly` | 不同運送方式的運費正確 |

### 4.3 OrderQueryTest.php（10 個測試）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-ORDER-011 | `can_get_order_list` | 取得訂單列表含分頁資訊 |
| TC-ORDER-012 | `order_list_supports_pagination` | 分頁功能正常 |
| TC-ORDER-013 | `can_filter_orders_by_status` | 依狀態篩選訂單 |
| TC-ORDER-014 | `can_filter_orders_by_date_range` | 依日期範圍篩選 |
| TC-ORDER-015 | `can_only_see_own_orders` | 只能看到自己的訂單 |
| TC-ORDER-016 | `unauthenticated_user_cannot_query_orders` | 未登入無法查詢 |
| TC-ORDER-017 | `can_get_order_detail_with_timeline` | 訂單詳情含時間軸資料 |
| TC-ORDER-018 | `returns_404_for_nonexistent_order` | 查看不存在的訂單返回 404 |
| TC-ORDER-019 | `cannot_view_other_users_order` | 無法查看他人訂單 |
| TC-ORDER-020 | `can_get_order_stats` | 訂單統計各狀態數量正確 |

### 4.4 OrderCancelTest.php（5 個測試）

| 編號 | 方法名 | 說明 |
|------|--------|------|
| TC-ORDER-021 | `can_cancel_pending_order` | 成功取消待付款訂單 |
| TC-ORDER-022 | `stock_is_restored_after_order_cancellation` | 取消訂單後庫存回補 |
| TC-ORDER-023 | `cannot_cancel_shipped_order` | 已出貨訂單無法取消 |
| TC-ORDER-024 | `cannot_cancel_completed_order` | 已完成訂單無法取消 |
| TC-ORDER-025 | `cannot_cancel_other_users_order` | 無法取消他人訂單 |

## 待辦清單

- [x] 建立 OrderCreateTest.php（10 個測試）
- [x] 建立 OrderQueryTest.php（10 個測試）
- [x] 建立 OrderCancelTest.php（5 個測試）
- [x] 全部 25 個測試通過

## 成功標準

- 25 個測試全部通過
- 庫存扣減/回補斷言正確（直接查詢 DB 驗證 stock 值）
- 涵蓋認證、權限、驗證、正常流程、錯誤處理

## 風險評估

- 中風險：建立訂單測試需先準備購物車資料，測試設置較複雜
- 庫存測試需在建立訂單前後比對 stock 值，注意 `fresh()` 重新載入
- `OrderCreateTest` 的 TC-ORDER-005（庫存不足）需確認 OrderService 的錯誤回傳格式

## 安全考量

- 權限測試確保用戶無法存取/操作他人訂單
- 驗證測試確保收件資訊格式正確

## 下一步

完成後進入階段 5（驗證與收尾）
