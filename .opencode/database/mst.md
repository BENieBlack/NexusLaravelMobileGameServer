# マスターDB (mst_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、マスターDB（`mst_*`）の設計ルールを定義します。

---

## 概要

マスターDBは、**ゲームの定義データ（アイテム、キャラクター、クエスト等）**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `mst_item` | アイテムマスター |
| `mst_equipment` | 装備マスター |
| `mst_unit` | ユニット（キャラクター）マスター |
| `mst_quest` | クエストマスター |
| `mst_gacha` | ガチャマスター |
| `mst_skill` | スキルマスター |
| `mst_billing_platform_product` | 課金商品マスター |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `mst_`
- 単数形を使用
- スネークケース

### 2. PRIMARY KEY

- **自動インクリメントID**
- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`

### 3. ENUM型の使用

- **マスターテーブルではENUM型を使用可能**
- デプロイ時に計画的に更新可能

```sql
-- ✅ Good: mstテーブルでENUM使用
CREATE TABLE mst_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('consumable', 'material', 'key_item') NOT NULL,
    rarity ENUM('common', 'rare', 'epic', 'legendary') NOT NULL
);
```

### 4. 多言語対応

**Option 1: 別テーブル方式（推奨）**

```sql
CREATE TABLE mst_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_key VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE mst_item_text (
    mst_item_id BIGINT UNSIGNED NOT NULL,
    language VARCHAR(5) NOT NULL COMMENT 'ja, en, zh等',
    name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (mst_item_id, language)
);
```

### 5. deploy_key管理

```sql
CREATE TABLE mst_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deploy_key INT UNSIGNED NOT NULL COMMENT 'どのデプロイで追加されたか',
    -- ...
    INDEX (deploy_key)
);
```

---

## テーブル例

### mst_item

```sql
CREATE TABLE mst_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deploy_key INT UNSIGNED NOT NULL,
    item_key VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('consumable', 'material', 'key_item') NOT NULL,
    rarity INT UNSIGNED NOT NULL COMMENT '1-5',
    max_stack INT UNSIGNED DEFAULT 9999,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (deploy_key),
    INDEX (type),
    INDEX (rarity)
);
```

### mst_item_text

```sql
CREATE TABLE mst_item_text (
    mst_item_id BIGINT UNSIGNED NOT NULL,
    language VARCHAR(5) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (mst_item_id, language),
    INDEX (language)
);
```

---

## チェックリスト

- [ ] テーブル名は`mst_`プレフィックス
- [ ] 単数形を使用
- [ ] PRIMARY KEYは自動インクリメント
- [ ] deploy_keyカラムを追加
- [ ] 多言語対応（別テーブル方式推奨）
- [ ] ENUM型を適切に使用
- [ ] Modelで`$connection = 'mst'`を指定

---

## 関連ドキュメント

- [データベース設計](../database.md) - 全体の設計方針
- [多言語対応](../database.md#多言語対応) - 詳細な設計
- [ENUM型の使用ルール](../database.md#enum型の使用ルール) - ENUM使用の注意点

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
