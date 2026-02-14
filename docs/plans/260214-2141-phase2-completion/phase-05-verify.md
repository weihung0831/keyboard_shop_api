# 階段 5：驗證與收尾

> 父計畫：[plan.md](plan.md)
> 相依：階段 1-4 全部完成
> 被依賴：無

## 概述

- 日期：2026-02-14
- 說明：執行全部測試套件確認通過，Pint 格式化
- 優先級：P1
- 實作狀態：completed
- 審查狀態：completed

## 實作步驟

### 5.1 執行新增的測試

```bash
php artisan test tests/Feature/Cart/CartTest.php
php artisan test tests/Feature/Order/
```

### 5.2 執行全部測試套件

```bash
php artisan test
```

確認所有測試（含原有的 Auth、Product、Category、User 測試）全部通過。

### 5.3 Pint 格式化

```bash
vendor/bin/pint --dirty
```

### 5.4 確認 git 狀態

```bash
git status
git diff --stat
```

## 待辦清單

- [x] 新增測試全部通過
- [x] 全部測試套件通過（無回歸）
- [x] Pint 格式化完成
- [x] 確認所有變更檔案正確
- [x] 修復 ProductCategoryFactory.php 的 image_url 欄位

## 成功標準

- 全部測試通過（0 failures, 0 errors）
- Pint 無格式問題
- 新增檔案：4 個 Factory + 4 個測試檔
- 修改檔案：1 個（OrderService.php）

## 下一步

完成後可進行 commit & push，第二階段正式完成。
