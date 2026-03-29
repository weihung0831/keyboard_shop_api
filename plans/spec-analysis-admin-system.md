# Spec Analysis: 後台管理系統 API

> **Source**: `plans/260329-1333-admin-system/` (plan.md + 6 phase files)
> **Date**: 2026-03-29
> **Module**: Admin Management System
> **Analyzed at**: 2026-03-29 13:52

---

## DO

### Data Layer
- [ ] Migration: `add_role_to_users_table` — enum('user','admin','super_admin') DEFAULT 'user' AFTER password
- [ ] Migration: `create_product_specifications_table` — id, product_id(FK cascade), spec_name(100), spec_value(255), sort_order, timestamps, index(product_id, sort_order)
- [ ] Migration: `migrate_specifications_json_to_table` — 將 products.specifications JSON 資料遷移到新表
- [ ] Migration: `remove_specifications_from_products_table` — 移除 products.specifications 欄位
- [ ] Migration: `create_system_settings_table` — id, key(100 unique), value(JSON nullable), group(50 default 'general'), description(255 nullable), timestamps, index(group)
- [ ] Model: `ProductSpecification` — fillable, BelongsTo Product
- [ ] Model: `SystemSetting` — fillable, JSON cast on value, static getValue()/setValue()
- [ ] Model: `Product` 加 `specifications()` HasMany 關聯
- [ ] Model: `User` 加 ROLE_USER/ROLE_ADMIN/ROLE_SUPER_ADMIN 常數, isAdmin()/isSuperAdmin(), canBeCancelledByAdmin() on Order — **[!] role 不加入 $fillable（防 updateProfile 提權）**
- [ ] Seeder: `SystemSettingSeeder` — 5 個預設設定 (site_name, site_description, ecpay_enabled, shipping_fee, free_shipping_threshold)
- [ ] Factory: `ProductSpecificationFactory` (for tests)
- [ ] Factory: `SystemSettingFactory` (for tests)
- [ ] Factory: `UserFactory` 擴展 — 新增 admin()/superAdmin() states
- [ ] Factory: `OrderFactory` 擴展 — 新增各狀態 states (pending/processing/shipped/completed/cancelled)

### Backend Logic
- [ ] Middleware: `EnsureAdmin` — 檢查 isAdmin(), 403 if not
- [ ] Controller: `Admin/AdminDashboardController` — stats()
- [ ] Controller: `Admin/AdminProductController` — index/store/show/update/destroy/batchToggle/lowStock
- [ ] Controller: `Admin/AdminProductSpecController` — index/store/update/destroy
- [ ] Controller: `Admin/AdminCategoryController` — index/store/update/destroy
- [ ] Controller: `Admin/AdminOrderController` — index/show/updateStatus
- [ ] Controller: `Admin/AdminUserController` — index/show/updateRole
- [ ] Controller: `Admin/AdminSettingController` — index/update/batchUpdate
- [ ] Service: `AdminDashboardService` — getStats(period), getRevenueStats, getOrderStats, getTopProducts, getMemberGrowth
- [ ] Service: `ProductSpecService` — CRUD for product specifications
- [ ] Service: `SystemSettingService` — CRUD + batch update
- [ ] Repository: `ProductSpecRepository`
- [ ] Repository: `SystemSettingRepository`
- [ ] FormRequest: `StoreProductRequest` — name, category_id, price, stock, sku(unique), specifications[]
- [ ] FormRequest: `UpdateProductRequest` — same as store, sku unique ignore self
- [ ] FormRequest: `BatchToggleProductRequest` — product_ids[] (required|array|min:1), is_active
- [ ] FormRequest: `StoreProductImageRequest` — images[] (required, mimes:jpg,jpeg,png,webp, max:5120), 儲存用 hashName()
- [ ] FormRequest: `StoreProductSpecRequest` — spec_name, spec_value
- [ ] FormRequest: `StoreCategoryRequest` — name, slug, is_active
- [ ] FormRequest: `UpdateCategoryRequest`
- [ ] FormRequest: `UpdateOrderStatusRequest` — status in:processing,shipped,completed,cancelled
- [ ] FormRequest: `UpdateUserRoleRequest` — role in:user,admin,super_admin
- [ ] FormRequest: `UpdateSettingRequest` — key exists, value 依 key 動態驗證型別（boolean/integer/string+max+strip_tags）
- [ ] FormRequest: `BatchUpdateSettingsRequest` — settings[] array, 同上 value 驗證
- [ ] Resource: `Admin/AdminProductResource` — 含 is_active, stock, specifications, timestamps
- [ ] Resource: `Admin/ProductSpecificationResource`
- [ ] Resource: `Admin/AdminOrderResource` — 含 user, payment
- [ ] Resource: `Admin/AdminOrderDetailResource` — 含出貨單資料
- [ ] Resource: `Admin/AdminUserResource` — 含 orders_count
- [ ] Resource: `Admin/AdminUserDetailResource` — 含購買記錄、消費統計
- [ ] Resource: `Admin/SystemSettingResource`

### API Routes
- [ ] `GET    /api/v1/admin/dashboard/stats` — Dashboard 統計
- [ ] `GET    /api/v1/admin/products` — 商品列表（含 inactive）
- [ ] `POST   /api/v1/admin/products` — 新增商品
- [ ] `GET    /api/v1/admin/products/{id}` — 商品詳情
- [ ] `PUT    /api/v1/admin/products/{id}` — 更新商品
- [ ] `DELETE /api/v1/admin/products/{id}` — 刪除商品
- [ ] `POST   /api/v1/admin/products/batch-toggle` — 批次上下架
- [ ] `GET    /api/v1/admin/products/low-stock` — 庫存警示
- [ ] `GET    /api/v1/admin/products/{id}/specs` — 商品規格列表
- [ ] `POST   /api/v1/admin/products/{id}/specs` — 新增規格
- [ ] `PUT    /api/v1/admin/products/{id}/specs/{specId}` — 更新規格
- [ ] `DELETE /api/v1/admin/products/{id}/specs/{specId}` — 刪除規格
- [ ] `POST   /api/v1/admin/products/{id}/images` — 上傳圖片
- [ ] `DELETE /api/v1/admin/products/{id}/images/{imageId}` — 刪除圖片
- [ ] `GET    /api/v1/admin/categories` — 分類列表
- [ ] `POST   /api/v1/admin/categories` — 新增分類
- [ ] `PUT    /api/v1/admin/categories/{id}` — 更新分類
- [ ] `DELETE /api/v1/admin/categories/{id}` — 刪除分類
- [ ] `GET    /api/v1/admin/orders` — 訂單列表
- [ ] `GET    /api/v1/admin/orders/{id}` — 訂單詳情
- [ ] `PATCH  /api/v1/admin/orders/{id}/status` — 更新訂單狀態
- [ ] `GET    /api/v1/admin/users` — 會員列表
- [ ] `GET    /api/v1/admin/users/{id}` — 會員詳情
- [ ] `PATCH  /api/v1/admin/users/{id}/role` — 更新會員角色
- [ ] `GET    /api/v1/admin/settings` — 系統設定列表
- [ ] `PUT    /api/v1/admin/settings` — 更新設定
- [ ] `PUT    /api/v1/admin/settings/batch` — 批次更新設定

### Integration & Modifications
- [ ] `bootstrap/app.php` — 註冊 'admin' middleware alias
- [ ] `routes/api.php` — 新增 admin prefix group (auth:sanctum + admin + throttle:60,1)
- [ ] `routes/api.php` — **[!] 靜態路由 (batch-toggle, low-stock) 必須定義在 {id} wildcard 之前**
- [ ] `app/Models/User.php` — 新增常數, isAdmin(), isSuperAdmin() — **role 不加 $fillable**
- [ ] `app/Models/Product.php` — 新增 specifications() HasMany, 移除 specifications JSON cast
- [ ] `app/Models/Order.php` — 新增 canBeCancelledByAdmin() (允許 shipped 狀態取消)

---

## DON'T

### Explicit Exclusions
- 不建立獨立 admin 登入端點，共用 `POST /auth/login` — Source: Brainstorm 關鍵設計
- 不產生 PDF 出貨單，只回傳結構化 JSON — Source: Brainstorm 關鍵設計
- 不做庫存警示推播通知，僅提供查詢端點 — Source: Brainstorm 關鍵設計
- 不使用 spatie/laravel-permission，用簡單 role 欄位 — Source: Brainstorm 需求確認

### Constraints & Limitations
- 訂單狀態流轉嚴格限制：completed/cancelled 不可再變更 — Source: Phase 3 §4
- 只有 super_admin 可修改角色 — Source: Phase 4 §4
- 不可修改自己的角色（防自我降權） — Source: Phase 4 §4
- admin 不可將他人設為 super_admin — Source: Phase 4 §4
- Dashboard revenue 排除取消訂單，只計已付款 — Source: Phase 5 §3
- period=all 時 trend_percentage 為 null — Source: Phase 5 §3
- top_products 只計有效訂單（非取消） — Source: Phase 5 §3
- system_settings seeder 需冪等（updateOrCreate） — Source: Phase 6 §3

### Security Constraints (審核新增)
- `role` 絕不放入 User $fillable — 防 updateProfile 提權攻擊 — Source: Security Audit C1
- 圖片上傳必須有 FormRequest 驗證 (mimes/max/hashName) — Source: Security Audit C2
- 系統設定 value 必須依 key 動態型別驗證，不可只驗 `present` — Source: Security Audit C3
- batchToggle 必須包 `DB::transaction()` — Source: Architecture Review W-2
- LIKE 搜尋必須 escape `%` `_` 萬用字元 — Source: Architecture Review W-6
- 系統設定修改限 super_admin — Source: 用戶決策
- Admin 路由群組加 throttle:60,1 — Source: Security Audit H4
- 批次操作空陣列回 422 (array|min:1) — Source: 用戶決策

### Anti-patterns
- 不在 Controller 內聯驗證，用 FormRequest — Source: CLAUDE.md Gotchas
- 不用 `DB::` 原生查詢，用 Eloquent ORM — Source: CLAUDE.md Gotchas
- 不用 ECPay SDK 的 CheckMacValueService — Source: CLAUDE.md Gotchas
- products.specifications JSON 欄位與 product_specifications 表不可並存 -> 遷移後移除 JSON — Source: Architecture Review I-2

---

## Verification Metrics (Test Cases)

### Authentication & Authorization

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | 未登入存取任何 admin 路由 | 401 Unauthorized | P0 |
| 2 | role=user 存取 admin 路由 | 403 Forbidden | P0 |
| 3 | role=admin 存取 admin 路由 | 200 OK | P0 |
| 4 | role=super_admin 存取 admin 路由 | 200 OK | P0 |
| 5 | admin 嘗試 PATCH /users/{id}/role | 403 只有 super_admin 可修改 | P0 |
| 6 | super_admin 修改自己的角色 | 422 不可修改自己 | P0 |
| 7 | super_admin 修改他人角色為 admin | 200 成功 | P0 |

### Product CRUD

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | admin GET /admin/products | 回傳含 inactive 商品，分頁 | P0 |
| 2 | admin POST /admin/products（完整資料） | 201 商品建立含規格 | P0 |
| 3 | admin POST /admin/products（缺必填欄位） | 422 驗證錯誤 | P0 |
| 4 | admin POST /admin/products（重複 SKU） | 422 SKU 已存在 | P1 |
| 5 | admin PUT /admin/products/{id} | 200 更新成功 | P0 |
| 6 | admin DELETE /admin/products/{id}（無訂單關聯） | 200 硬刪除成功 | P0 |
| 6b | admin DELETE /admin/products/{id}（有訂單關聯） | 422 禁止刪除 | P0 |
| 6c | 刪除商品時 specifications + images 級聯刪除 | 關聯資料一併清除 | P0 |
| 7 | admin GET /admin/products?search=keyword | 回傳符合搜尋結果 | P1 |
| 8 | admin GET /admin/products?is_active=false | 只回傳下架商品 | P1 |
| 9 | admin POST /admin/products/batch-toggle | 批次更新 is_active | P0 |
| 10 | admin GET /admin/products/low-stock?threshold=5 | 回傳 stock < 5 商品 | P1 |
| 11 | admin POST /admin/products/batch-toggle (空陣列) | 422 驗證錯誤 | P0 |
| 12 | 圖片上傳非圖片檔案 (e.g. .php) | 422 mimes 驗證失敗 | P0 |
| 13 | 圖片上傳超過 5MB | 422 max 驗證失敗 | P1 |

### Product Specifications

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | GET /admin/products/{id}/specs | 回傳該商品所有規格 | P0 |
| 2 | POST /admin/products/{id}/specs | 新增規格 | P0 |
| 3 | PUT /admin/products/{id}/specs/{specId} | 更新規格 | P0 |
| 4 | DELETE /admin/products/{id}/specs/{specId} | 刪除規格 | P0 |
| 5 | POST 規格到不存在的商品 | 404 | P1 |

### Category Management

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | POST /admin/categories（完整資料） | 201 建立成功 | P0 |
| 2 | PUT /admin/categories/{id} | 200 更新成功 | P0 |
| 3 | DELETE /admin/categories/{id}（無關聯商品） | 200 刪除成功 | P0 |
| 4 | DELETE /admin/categories/{id}（有關聯商品） | 422 或提示有關聯 | P1 |

### Order Management

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | GET /admin/orders（無篩選） | 回傳所有訂單，分頁 | P0 |
| 2 | GET /admin/orders?status=pending | 只回傳 pending 訂單 | P0 |
| 3 | GET /admin/orders?search=KB | 搜尋訂單編號 | P1 |
| 4 | GET /admin/orders?date_from=X&date_to=Y | 日期區間篩選 | P1 |
| 5 | GET /admin/orders/{id} | 回傳含 user/items/payment/時間線 | P0 |
| 6 | PATCH pending -> processing | 200 成功 | P0 |
| 7 | PATCH processing -> shipped | 200 + shipped_at 更新 | P0 |
| 8 | PATCH shipped -> completed | 200 + completed_at 更新 | P0 |
| 9 | PATCH pending -> cancelled | 200 + cancelled_at 更新 | P0 |
| 10 | PATCH completed -> processing | 422 非法狀態轉換 | P0 |
| 11 | PATCH cancelled -> processing | 422 非法狀態轉換 | P0 |
| 12 | PATCH shipped -> cancelled（管理員操作） | 200 + cancelled_at 更新 | P0 |
| 13 | PATCH pending -> shipped（跳過 processing） | 422 非法狀態轉換 | P1 |
| 14 | 狀態更新後對應時間戳正確記錄 | shipped_at/completed_at/cancelled_at 非 null | P0 |

### User Management

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | GET /admin/users | 回傳含 orders_count，分頁 | P0 |
| 2 | GET /admin/users?search=test | 搜尋姓名或 email | P1 |
| 3 | GET /admin/users?role=admin | 只回傳 admin | P1 |
| 4 | GET /admin/users/{id} | 含訂單統計 + 最近 10 筆購買記錄 | P0 |
| 5 | super_admin PATCH /users/{id}/role -> admin | 200 成功 | P0 |
| 6 | super_admin PATCH 自己 /role | 422 不可修改自己 | P0 |
| 7 | admin PATCH /users/{id}/role | 403 權限不足 | P0 |

### Dashboard Statistics

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | GET /admin/dashboard/stats?period=today | 回傳今日統計 | P0 |
| 2 | GET /admin/dashboard/stats?period=7d | 回傳 7 天統計 | P0 |
| 3 | GET /admin/dashboard/stats?period=30d | 回傳 30 天統計（預設） | P0 |
| 4 | GET /admin/dashboard/stats?period=all | trend_percentage 為 null | P1 |
| 5 | revenue 排除 cancelled 訂單 | total 不含取消訂單金額 | P0 |
| 6 | top_products 排序正確 | 依 total_quantity DESC | P1 |
| 7 | trend_percentage 計算正確 | (current-prev)/prev*100 | P1 |
| 8 | 無資料時不報錯 | 回傳 0 值或空陣列 | P1 |

### System Settings

| # | Test Scenario | Expected Result | Priority |
|---|--------------|-----------------|----------|
| 1 | GET /admin/settings | 回傳所有設定 | P0 |
| 2 | GET /admin/settings?group=payment | 只回傳 payment group | P1 |
| 3 | PUT /admin/settings（合法 key） | 200 更新成功 | P0 |
| 4 | PUT /admin/settings（不存在 key） | 422 驗證錯誤 | P1 |
| 5 | PUT /admin/settings/batch（多個設定） | 200 批次更新成功 | P0 |
| 6 | SystemSetting::getValue() 回傳正確值 | 匹配設定值 | P1 |
| 7 | SystemSetting::setValue() 新增/更新 | updateOrCreate 行為正確 | P1 |
| 8 | Seeder 重複執行不重複建立 | 設定數量不變 | P1 |
| 9 | admin 嘗試修改設定 | 403 只有 super_admin 可修改 | P0 |
| 10 | value 注入 XSS payload (e.g. `<script>`) | 422 驗證失敗或 strip_tags | P0 |

---

## Summary

| Category | Count |
|----------|-------|
| DO items | 72 |
| DON'T items | 23 |
| Test cases | 72 |
| P0 critical tests | 42 |

### Risk Areas
- **[!] role 提權漏洞** — role 放入 $fillable 可透過 updateProfile 自我提權（已標記修正）
- **[!] 圖片上傳安全** — 無驗證可上傳惡意檔案（已補 FormRequest）
- **訂單狀態流轉** — 最多 DON'T constraints，admin 可取消 shipped 但需新增 canBeCancelledByAdmin()
- **角色權限控制** — super_admin/admin 權限邊界需精確，防越權
- **Dashboard 統計** — revenue 計算需正確排除取消訂單與未付款訂單，trend 分母為 0 需處理
- **規格資料遷移** — products.specifications JSON -> product_specifications 表，需遷移 migration

### Suggested Implementation Order
1. Phase 1: 基礎建設（所有後續模組依賴）
2. Phase 2: 商品管理（最大模組，核心日常操作）
3. Phase 3: 訂單管理（狀態流轉邏輯複雜度高）
4. Phase 4: 會員管理（依賴 role 系統完整度）
5. Phase 5: Dashboard 統計（依賴訂單/會員資料完整度）
6. Phase 6: 系統設定（獨立低風險，最後收尾）

### Items Needing Clarification
- 商品刪除策略 -> **已決定：硬刪除 + 關聯檢查（有訂單->422）**
- 圖片儲存位置 -> **已決定：Local public disk**
- 出貨取消 -> **已決定：shipped 可取消（管理員操作）**
- 規格資料來源 -> **已決定：用新表 + 遷移舊 JSON + 移除舊欄位**
- 批次空陣列 -> **已決定：422 驗證錯誤**
- 設定修改權限 -> **已決定：只有 super_admin**
- role 放 $fillable -> **已決定：不放（安全考量）**

### Review Sources
- 架構審核：`260329-1333-admin-system/reports/code-reviewer-260329-1400-admin-system-plan-review.md`
- 安全審計：`260329-1333-admin-system/reports/debugger-260329-1401-security-audit.md`
- 測試審查：`260329-1333-admin-system/reports/tester-260329-1401-test-coverage-analysis.md`
