# システムDB (sys_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、システムDB（`sys_*`）の設計ルールを定義します。

---

## 概要

システムDBは、**ゲームの設定・システム管理に関するデータ**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `sys_player` | プレイヤー情報（基本情報、レベル、経験値等） |
| `sys_deploy` | デプロイ管理（マスターデータ配信） |
| `sys_deploy_master` | マスターデータのバージョン管理 |
| `sys_deploy_asset` | アセットデータのバージョン管理 |
| `sys_sharding_node` | シャーディング設定 |
| `sys_maintenance` | メンテナンス情報 |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `sys_`
- 単数形を使用（例: `sys_player`, not `sys_players`）
- スネークケース

### 2. PRIMARY KEY

- **単一カラムの自動インクリメントID**を推奨
- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`

### 3. データベース接続

```php
// Modelで明示的に指定
protected $connection = 'sys';
```

---

## テーブル例

### sys_player

```sql
CREATE TABLE sys_player (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    my_id VARCHAR(8) UNIQUE NOT NULL COMMENT 'ユーザー識別用ID',
    name VARCHAR(50) NOT NULL COMMENT 'プレイヤー名',
    level INT UNSIGNED DEFAULT 1 COMMENT 'レベル',
    exp BIGINT UNSIGNED DEFAULT 0 COMMENT '経験値',
    last_login_at DATETIME COMMENT '最終ログイン日時',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (my_id),
    INDEX (last_login_at)
);
```

### sys_deploy

```sql
CREATE TABLE sys_deploy (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deploy_key INT UNSIGNED UNIQUE NOT NULL COMMENT '人間が管理しやすいキー',
    start_at DATETIME NOT NULL COMMENT 'ダウンロード可能日時',
    sys_deploy_master_id BIGINT UNSIGNED COMMENT 'マスターデータのデプロイID',
    sys_deploy_asset_id BIGINT UNSIGNED COMMENT 'アセットデータのデプロイID',
    is_active BOOLEAN DEFAULT FALSE COMMENT 'アクティブフラグ',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## チェックリスト

- [ ] テーブル名は`sys_`プレフィックス
- [ ] 単数形を使用
- [ ] PRIMARY KEYは自動インクリメント
- [ ] 必要なインデックスを設定
- [ ] Modelで`$connection = 'sys'`を指定

---

## 関連ドキュメント

- [データベース設計](../database.md) - 全体の設計方針
- [マスターデータ配信システム](../api.md#マスターデータ配信システム) - deploy関連テーブル

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
