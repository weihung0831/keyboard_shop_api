# README 更新報告

**更新時間**: 2026-03-29
**檔案**: `/README.md`

## 摘要

更新 README.md 以反映項目當前狀態，添加新功能說明、系統設定、客服系統、產品規格、部署資訊及測試覆蓋。

## 變更內容

### 1. 公開路由新增（第 45-59 行）
- `GET /settings` — 系統設定端點（site_name、運費等）
- `POST /contact` — 公開客服留言端點

### 2. 管理員路由擴展（第 123-125 行）
- `GET /admin/contact-messages` — 客服留言列表
- `PATCH /admin/contact-messages/{id}/read` — 標記已讀
- `DELETE /admin/contact-messages/{id}` — 刪除留言

### 3. 新增「關鍵功能」區段（第 133-160 行）
清晰說明五大核心功能：
- **會員與權限管理**: RBAC 角色系統、購物車雙模式
- **商品與規格管理**: ProductSpecification 模型
- **系統設定與動態配置**: SystemSetting 集中儲存、ecpay_enabled 開關
- **訂單與支付**: 狀態流程、ECPay 金流、自動運費計算
- **客服系統**: 公開留言 + Admin 後台管理
- **部署**: Zeabur 持久化儲存

### 4. 專案結構更新（第 162-188 行）
- 新增 `Contact/` Controller
- Models 說明添加 ProductSpecification、SystemSetting、ContactMessage
- Services 說明添加 AdminDashboardService
- 功能測試註記為 289+ 測試

### 5. 測試區段完善（第 190-203 行）
- 添加 289+ 測試數量說明
- 強調涵蓋範圍

## 驗證

- 所有 API 路由已對照 CLAUDE.md 及專案程式碼
- 內容以繁體中文撰寫
- 結構清晰，易於導航
- 檔案大小合理（208 行）

## 狀態

✅ 已完成更新，無遺漏或不一致之處。
