# Keyboard Shop API

鍵盤電商後端 RESTful API，基於 Laravel 12 建構。

## 技術棧

- **PHP** 8.4 / **Laravel** 12
- **認證**: Laravel Sanctum（Bearer Token）
- **資料庫**: SQLite（預設）
- **測試**: PHPUnit 11
- **程式碼風格**: Laravel Pint

## 快速開始

### 環境需求

- PHP >= 8.4
- Composer
- Node.js（用於 concurrently 開發伺服器）

### 安裝與啟動

```bash
# 一鍵設定（安裝依賴、產生 key、執行 migration）
composer run setup

# 啟動開發環境（server + queue + logs）
composer run dev
```

### 常用指令

```bash
php artisan test              # 執行測試
vendor/bin/pint --dirty       # 格式化已修改的檔案
php artisan migrate           # 執行資料庫遷移
php artisan db:seed           # 填充測試資料
```

## API 概覽

所有 API 路由前綴為 `/api/v1/`。

### 公開路由（無需認證）

| 方法 | 路徑 | 說明 |
|------|------|------|
| POST | `/auth/register` | 會員註冊 |
| POST | `/auth/login` | 會員登入（限流：5次/分鐘） |
| POST | `/auth/forgot-password` | 忘記密碼 |
| POST | `/auth/reset-password` | 重設密碼 |
| GET | `/categories` | 分類列表 |
| GET | `/categories/{idOrSlug}` | 分類詳情 |
| GET | `/products` | 產品列表（支援分頁、搜尋、篩選） |
| GET | `/products/search/suggestions` | 搜尋建議 |
| GET | `/products/{idOrSlug}` | 產品詳情 |

### 購物車（支援會員與訪客）

會員使用 Bearer Token，訪客使用 `X-Session-Id` Header。

| 方法 | 路徑 | 說明 |
|------|------|------|
| GET | `/cart` | 取得購物車 |
| POST | `/cart/items` | 加入購物車 |
| PUT | `/cart/items/{id}` | 更新數量 |
| DELETE | `/cart/items/{id}` | 移除項目 |
| DELETE | `/cart` | 清空購物車 |
| POST | `/cart/merge` | 合併訪客購物車（需認證） |

### 需認證路由

| 方法 | 路徑 | 說明 |
|------|------|------|
| POST | `/auth/logout` | 登出 |
| GET | `/user/profile` | 取得個人資料 |
| PUT | `/user/profile` | 更新個人資料 |
| PUT | `/user/change-password` | 修改密碼 |
| GET | `/orders` | 訂單列表 |
| GET | `/orders/stats` | 訂單統計 |
| POST | `/orders` | 建立訂單 |
| GET | `/orders/{id}` | 訂單詳情 |
| PUT | `/orders/{id}/cancel` | 取消訂單 |
| POST | `/orders/{id}/pay` | 發起付款（跳轉綠界） |
| GET | `/orders/{id}/payment` | 查詢付款狀態 |
| POST | `/orders/{id}/refund` | 申請退款 |
| GET | `/payments` | 付款紀錄列表 |

### 管理員路由（需認證 + Admin 權限）

| 方法 | 路徑 | 說明 |
|------|------|------|
| GET | `/admin/dashboard/stats` | 儀表板統計 |
| GET | `/admin/orders` | 訂單列表 |
| GET | `/admin/orders/{id}` | 訂單詳情 |
| PATCH | `/admin/orders/{id}/status` | 更新訂單狀態 |
| GET | `/admin/users` | 會員列表 |
| GET | `/admin/users/{id}` | 會員詳情 |
| PATCH | `/admin/users/{id}/role` | 更新會員角色 |
| GET | `/admin/categories` | 分類列表 |
| POST | `/admin/categories` | 新增分類 |
| PUT | `/admin/categories/{id}` | 更新分類 |
| DELETE | `/admin/categories/{id}` | 刪除分類 |
| GET | `/admin/products` | 商品列表 |
| POST | `/admin/products` | 新增商品 |
| GET | `/admin/products/{id}` | 商品詳情 |
| PUT | `/admin/products/{id}` | 更新商品 |
| DELETE | `/admin/products/{id}` | 刪除商品 |
| POST | `/admin/products/batch-toggle` | 批次上下架 |
| GET | `/admin/products/low-stock` | 低庫存商品 |
| POST | `/admin/products/{id}/images` | 上傳商品圖片 |
| DELETE | `/admin/products/{id}/images/{imageId}` | 刪除商品圖片 |
| GET | `/admin/products/{id}/specs` | 商品規格列表 |
| POST | `/admin/products/{id}/specs` | 新增規格 |
| PUT | `/admin/products/{id}/specs/{specId}` | 更新規格 |
| DELETE | `/admin/products/{id}/specs/{specId}` | 刪除規格 |
| GET | `/admin/settings` | 系統設定列表 |
| PUT | `/admin/settings` | 更新系統設定（僅 super_admin） |
| PUT | `/admin/settings/batch` | 批次更新設定（僅 super_admin） |

### 金流回調（無需認證）

| 方法 | 路徑 | 說明 |
|------|------|------|
| POST | `/payments/callback` | 綠界 ECPay 回調（以 CheckMacValue 驗證） |

## 專案結構

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/         # 管理後台（商品、訂單、會員、分類、儀表板、設定）
│   │   ├── Auth/          # 認證（註冊、登入、密碼重設）
│   │   ├── Cart/          # 購物車
│   │   ├── Category/      # 產品分類
│   │   ├── Order/         # 訂單
│   │   ├── Payment/       # 金流（ECPay 綠界）
│   │   ├── Product/       # 產品
│   │   └── User/          # 會員資料
│   ├── Middleware/         # EnsureAdmin（RBAC 權限）
│   ├── Requests/          # Form Request 驗證
│   └── Resources/         # API Resource 回應格式
├── Models/                # Eloquent Models
├── Repositories/          # 資料存取層
├── Services/              # 商業邏輯（含 EcpayService）
database/
├── factories/             # 測試工廠
├── migrations/            # 資料庫遷移
└── seeders/               # 資料填充
tests/Feature/             # 功能測試（依模組分類）
```

## 測試

```bash
# 執行全部測試
php artisan test

# 執行特定模組測試
php artisan test tests/Feature/Cart/CartTest.php

# 執行特定測試方法
php artisan test --filter=testName
```

## 授權條款

MIT License
