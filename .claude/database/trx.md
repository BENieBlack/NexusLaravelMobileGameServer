# トランザクションDB (trx_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、トランザクションDB（`trx_*`）の設計ルールを定義します。

---

## 概要

トランザクションDBは、**プレイヤーの所有データ（アイテム、装備、通貨等）**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `trx_item` | プレイヤーのアイテム所有 |
| `trx_equipment` | プレイヤーの装備所有 |
| `trx_unit` | プレイヤーのユニット所有 |
| `trx_diamond` | ダイヤモンド（課金通貨）現在値 |
| `trx_diamond_balance` | ダイヤモンド残高（FIFO消費用） |
| `trx_quest_progress` | クエスト進行状況 |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `trx_`
- 単数形を使用
- スネークケース

### 2. PRIMARY KEY

**パターン1: 自動インクリメントID（推奨）**

```sql
CREATE TABLE trx_equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_equipment_id BIGINT UNSIGNED NOT NULL,
    -- ...
    INDEX (sys_player_id)
);
```

**パターン2: 複合PRIMARY KEY**

```sql
CREATE TABLE trx_item (
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED DEFAULT 0,
    -- ...
    PRIMARY KEY (sys_player_id, mst_item_id)
);
```

### 3. ENUM型は使用しない

- **トランザクションテーブルではENUM型を使用禁止**
- STRING型を使用

```sql
-- ❌ Bad: trxテーブルでENUM使用
CREATE TABLE trx_diamond_balance (
    billing_platform ENUM('AppStore', 'GooglePlay') NOT NULL
);

-- ✅ Good: STRING型を使用
CREATE TABLE trx_diamond_balance (
    billing_platform VARCHAR(191) NOT NULL COMMENT 'AppStore, GooglePlay, PayPal, Stripe等'
);
```

### 4. シャーディング対応

- プレイヤーIDをキーとしたシャーディング
- すべてのテーブルに`sys_player_id`を含める

---

## テーブル例

### trx_item（複合PRIMARY KEY）

```sql
CREATE TABLE trx_item (
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sys_player_id, mst_item_id)
);
```

### trx_equipment（自動インクリメントID）

```sql
CREATE TABLE trx_equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_equipment_id BIGINT UNSIGNED NOT NULL,
    grade INT UNSIGNED DEFAULT 1,
    level INT UNSIGNED DEFAULT 1,
    level_exp BIGINT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (sys_player_id),
    INDEX (mst_equipment_id)
);
```

### trx_diamond（課金通貨現在値）

```sql
CREATE TABLE trx_diamond (
    sys_player_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(191) NOT NULL COMMENT 'Apple, Google',
    paid_amount INT UNSIGNED DEFAULT 0 COMMENT '有償ダイヤモンド数',
    free_amount INT UNSIGNED DEFAULT 0 COMMENT '無償ダイヤモンド数',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sys_player_id, platform)
);
```

### trx_diamond_balance（FIFO消費用）

```sql
CREATE TABLE trx_diamond_balance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sys_player_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(191) NOT NULL,
    billing_platform VARCHAR(191) NOT NULL COMMENT 'AppStore, GooglePlay, PayPal, Stripe等',
    current_amount INT UNSIGNED NOT NULL COMMENT '現在の残高',
    purchase_amount INT UNSIGNED NOT NULL COMMENT '購入時の数量',
    unit_price DECIMAL(10,2) NOT NULL COMMENT '単価（返金計算用）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (sys_player_id, platform, billing_platform)
);
```

---

## 課金通貨管理システム

詳細は[課金通貨管理システム（ダイヤモンド管理）](../database.md#課金通貨管理システムダイヤモンド管理)を参照してください。

**設計方針:**
- 現在値テーブル（`trx_diamond`）+ 残高テーブル（`trx_diamond_balance`）の2テーブル構成
- FIFO方式での消費
- プラットフォーム別管理

---

## チェックリスト

- [ ] テーブル名は`trx_`プレフィックス
- [ ] 単数形を使用
- [ ] すべてのテーブルに`sys_player_id`を含める
- [ ] ENUM型を使用していない（STRING型を使用）
- [ ] 適切なインデックスを設定
- [ ] Modelで`$connection = 'trx1'`または`'trx2'`を指定

---

## 関連ドキュメント

- [データベース設計](../database.md) - 全体の設計方針
- [ENUM型の使用ルール](../database.md#enum型の使用ルール) - ENUM禁止の理由
- [課金通貨管理システム](../database.md#課金通貨管理システムダイヤモンド管理) - ダイヤモンド管理の詳細

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
