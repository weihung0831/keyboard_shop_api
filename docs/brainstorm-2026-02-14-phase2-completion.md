# 第二階段補完：購物車與訂單模組

## 問題陳述

第二階段（購物車與訂單模組）核心功能已實作，但缺少：
1. 測試覆蓋（購物車 6 個端點 + 訂單 5 個端點，共 0 個測試）
2. 測試用 Factory（Cart, CartItem, Order, OrderItem 無 Factory）
3. 庫存扣減邏輯（下單不扣庫存、取消不回補）

## 決策摘要

| 決策項目 | 選擇 | 理由 |
|---------|------|------|
| 優先順序 | 全部一次補齊 | 功能與測試相依，一起做更有效率 |
| 庫存策略 | 下單即扣 | 簡單直觀，無需金流依賴 |
| 並發處理 | 暫不處理 | 目前流量不高，簡單處理即可 |
| 取消回補 | 是 | 避免庫存永久損失 |
| 測試範圍 | 完整覆蓋 | Happy path + 錯誤處理 + 邊界情況 |

---

## 實作項目

### A. Factory（4 個）

需建立以下 Factory，用於測試資料快速產生：

1. **CartFactory** - 購物車（支援會員/訪客兩種 state）
2. **CartItemFactory** - 購物車項目（含 product 關聯）
3. **OrderFactory** - 訂單（支援各狀態 state：pending, processing, shipped, completed, cancelled）
4. **OrderItemFactory** - 訂單項目（含商品快照欄位）

### B. 庫存扣減邏輯

修改位置：`OrderService::createOrder()`

```
建立訂單時：
1. 驗證庫存（已有）
2. 扣減庫存 → Product::where('id', $id)->decrement('stock', $qty)
3. 建立訂單項目
4. 清空購物車
```

修改位置：`OrderService::cancelOrder()`

```
取消訂單時：
1. 驗證可取消（已有）
2. 回補庫存 → 遍歷 order_items，逐一 increment
3. 更新訂單狀態為 cancelled
```

### C. 測試規劃

#### 購物車測試（約 20 個）

**CartTest.php**
- TC-CART-001: 訪客取得空購物車
- TC-CART-002: 會員取得空購物車
- TC-CART-003: 訪客加入商品到購物車
- TC-CART-004: 會員加入商品到購物車
- TC-CART-005: 加入相同商品累加數量
- TC-CART-006: 加入不存在的商品失敗
- TC-CART-007: 加入超過庫存數量失敗
- TC-CART-008: 更新購物車項目數量
- TC-CART-009: 更新數量超過庫存失敗
- TC-CART-010: 更新不存在的項目失敗
- TC-CART-011: 無法更新他人購物車項目
- TC-CART-012: 移除購物車項目
- TC-CART-013: 移除不存在的項目失敗
- TC-CART-014: 無法移除他人購物車項目
- TC-CART-015: 清空購物車
- TC-CART-016: 無識別資訊時返回空購物車
- TC-CART-017: 登入後合併訪客購物車
- TC-CART-018: 合併時相同商品累加數量
- TC-CART-019: 合併時不超過庫存上限
- TC-CART-020: 合併後訪客購物車被刪除

#### 訂單測試（約 25 個）

**OrderCreateTest.php**
- TC-ORDER-001: 成功建立訂單
- TC-ORDER-002: 建立訂單後購物車被清空
- TC-ORDER-003: 建立訂單時庫存被扣減
- TC-ORDER-004: 購物車為空時無法建立訂單
- TC-ORDER-005: 庫存不足時無法建立訂單
- TC-ORDER-006: 未登入無法建立訂單
- TC-ORDER-007: 收件資訊驗證（必填欄位）
- TC-ORDER-008: 手機號碼格式驗證
- TC-ORDER-009: 訂單編號自動產生
- TC-ORDER-010: 運費正確計算

**OrderQueryTest.php**
- TC-ORDER-011: 取得訂單列表
- TC-ORDER-012: 訂單列表分頁
- TC-ORDER-013: 依狀態篩選訂單
- TC-ORDER-014: 依日期範圍篩選
- TC-ORDER-015: 只能看到自己的訂單
- TC-ORDER-016: 未登入無法查詢訂單
- TC-ORDER-017: 取得訂單詳情含時間軸
- TC-ORDER-018: 查看不存在的訂單返回 404
- TC-ORDER-019: 無法查看他人訂單
- TC-ORDER-020: 取得訂單統計

**OrderCancelTest.php**
- TC-ORDER-021: 成功取消待付款訂單
- TC-ORDER-022: 取消訂單後庫存回補
- TC-ORDER-023: 已出貨訂單無法取消
- TC-ORDER-024: 已完成訂單無法取消
- TC-ORDER-025: 無法取消他人訂單

---

## 實作順序

```
1. 建立 Factory（4 個）          ← 測試基礎
2. 修改庫存扣減邏輯               ← OrderService 改動
3. 購物車測試（CartTest.php）     ← 20 個測試
4. 訂單測試（3 個測試檔案）        ← 25 個測試
5. 執行全部測試確認通過            ← 驗證
6. Pint 格式化                   ← 收尾
```

## 風險與注意事項

- 庫存扣減使用簡單 decrement，暫不處理並發（未來流量高時可改用 `where('stock', '>=', $qty)->decrement()`）
- 取消回補需確保在 DB Transaction 內，避免部分回補
- Factory 建立時需注意關聯順序（先 Category → Product → Cart/Order）
- 現有購物車 API 部分端點不需認證（支援訪客），測試需分別覆蓋兩種身份

## 預估測試數量

- 購物車：~20 個
- 訂單：~25 個
- 總計：~45 個測試
