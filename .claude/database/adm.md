# 管理DB (adm_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、管理DB（`adm_*`）の設計ルールを定義します。

---

## 概要

管理DBは、**運営管理用のデータ（管理者アカウント、運営ツール設定等）**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `adm_admin_user` | 管理者ユーザー |
| `adm_role` | 権限ロール |
| `adm_permission` | 権限設定 |
| `adm_audit_log` | 管理操作監査ログ |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `adm_`
- 単数形を使用
- スネークケース

### 2. PRIMARY KEY

- **自動インクリメントID**
- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`

### 3. セキュリティ

- パスワードは必ずハッシュ化
- 重要な操作は監査ログに記録

---

## テーブル例

### adm_admin_user

```sql
CREATE TABLE adm_admin_user (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    adm_role_id BIGINT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (username),
    INDEX (email)
);
```

---

## チェックリスト

- [ ] テーブル名は`adm_`プレフィックス
- [ ] セキュリティを考慮した設計
- [ ] 監査ログを記録

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
