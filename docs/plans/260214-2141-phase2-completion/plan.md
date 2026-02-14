---
title: "第二階段補完：購物車與訂單模組"
description: "建立 Factory、庫存扣減邏輯、約 45 個購物車與訂單測試"
status: completed
priority: P1
effort: 3h
branch: main
tags: [testing, factory, stock, cart, order, phase-2]
created: 2026-02-14
---

# 第二階段補完：購物車與訂單模組

## 概述

補齊第二階段缺少的部分：
- 4 個 Model Factory（測試資料產生）
- 庫存扣減/回補邏輯（OrderService）
- 約 45 個功能測試（購物車 + 訂單）

## 階段總覽

| # | 階段 | 狀態 | 預估時間 | 檔案 |
|---|------|------|---------|------|
| 1 | 建立 Factory | completed | 30min | [phase-01-factories.md](phase-01-factories.md) |
| 2 | 庫存扣減邏輯 | completed | 30min | [phase-02-stock-logic.md](phase-02-stock-logic.md) |
| 3 | 購物車測試 | completed | 1h | [phase-03-cart-tests.md](phase-03-cart-tests.md) |
| 4 | 訂單測試 | completed | 1h | [phase-04-order-tests.md](phase-04-order-tests.md) |
| 5 | 驗證與收尾 | completed | 15min | [phase-05-verify.md](phase-05-verify.md) |

## 關鍵檔案

### 需建立
- `database/factories/CartFactory.php`
- `database/factories/CartItemFactory.php`
- `database/factories/OrderFactory.php`
- `database/factories/OrderItemFactory.php`
- `tests/Feature/Cart/CartTest.php`
- `tests/Feature/Order/OrderCreateTest.php`
- `tests/Feature/Order/OrderQueryTest.php`
- `tests/Feature/Order/OrderCancelTest.php`

### 需修改
- `app/Services/OrderService.php`（createOrder 扣庫存、cancelOrder 回補庫存）

### 參考（唯讀）
- `app/Http/Controllers/Cart/CartController.php`
- `app/Http/Controllers/Order/OrderController.php`
- `app/Services/CartService.php`
- `app/Repositories/CartRepository.php`
- `app/Repositories/OrderRepository.php`
- `database/factories/ProductFactory.php`（Factory 模式參考）
- `tests/Feature/Auth/LoginTest.php`（測試風格參考）

## 相依關係

- 階段 1（Factory）必須在階段 3-4（測試）之前完成
- 階段 2（庫存邏輯）必須在階段 4（訂單測試含庫存斷言）之前完成
- 階段 3-4 可平行執行（前提是階段 1-2 已完成）

## 慣例

- 測試風格：PHPUnit + RefreshDatabase、AAA 註解、TC-XXX-NNN 編號
- 方法命名：snake_case + `@test` annotation
- Factory 狀態：使用 `state()` 方法（參考 ProductFactory）
- 使用 `php artisan make:factory` 和 `php artisan make:test --phpunit` 建立檔案
