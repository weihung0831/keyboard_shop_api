# Commit, Push

執行完整的提交流程：格式化程式碼、提交、推送。

## 步驟

1. 檢查 `git status` 確認變更
3. 將所有變更加入暫存區
4. 根據變更內容產生符合規範的 commit message（繁體中文），請用戶確認
5. 詢問用戶要推送到哪個分支或建立新分支，然後推送

## 注意事項

- Commit message 格式：`類型: 描述`（如 `feat: 新增配方匯出功能`）
- 類型：feat, fix, refactor, test, docs, chore
- PR 描述使用繁體中文
- 一句話就好, 不需要加上 ai 名稱
