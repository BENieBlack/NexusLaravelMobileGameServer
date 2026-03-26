# ツールDB (tol_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、ツールDB（`tol_*`）の設計ルールを定義します。

---

## 概要

ツールDBは、**運営ツール用のデータ（配布予約、メンテナンス設定等）**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `tol_delivery_reservation` | アイテム配布予約 |
| `tol_maintenance_schedule` | メンテナンススケジュール |
| `tol_announcement` | お知らせ管理 |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `tol_`
- 単数形を使用
- スネークケース

### 2. PRIMARY KEY

- **自動インクリメントID**
- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`

---

## テーブル例

### tol_delivery_reservation

```sql
CREATE TABLE tol_delivery_reservation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_player_condition VARCHAR(255) COMMENT '対象プレイヤー条件',
    resource_type VARCHAR(50) NOT NULL COMMENT 'item, equipment等',
    resource_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL COMMENT '配布予定日時',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (scheduled_at),
    INDEX (status)
);
```

---

## チェックリスト

- [ ] テーブル名は`tol_`プレフィックス
- [ ] 単数形を使用
- [ ] 運営ツール用途を明確に

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
