# ログDB (log_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、ログDB（`log_*`）の設計ルールを定義します。

---

## 概要

ログDBは、**ゲーム内のアクションログ（アイテム使用、ガチャ実行等）**を格納します。

---

## 対象テーブル

| テーブル名 | 用途 |
|-----------|------|
| `log_access` | APIアクセスログ |
| `log_player` | プレイヤーレベル変更ログ |
| `log_item` | アイテム増減ログ |
| `log_gacha` | ガチャ実行ログ |
| `log_unit` | ユニット取得・強化ログ |
| `log_equipment` | 装備取得・強化ログ |
| `log_in_app_purchase` | 課金ログ |

---

## 設計原則

### 1. 命名規約

- プレフィックス: `log_`
- 単数形を使用
- スネークケース

### 2. PRIMARY KEY

- **自動インクリメントID**
- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`

### 3. タイムスタンプ

**重要: ログテーブルは特別なルールを適用**

- **`updated_at`を使用しない** （ログは更新されないため）
- **`system_at`を追加** （ビジネスロジック用の日時、デバッグ時刻に連動）
- **`created_at`はMySQLが自動設定** （実際の記録時刻）

```sql
CREATE TABLE log_equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unique_request_id VARCHAR(64) NOT NULL,
    sys_player_id BIGINT UNSIGNED NOT NULL,
    -- ...
    system_at DATETIME NOT NULL COMMENT 'ビジネスロジック用の日時（デバッグ時刻に連動）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '実際の記録時刻（MySQL自動設定）',
    INDEX (unique_request_id),
    INDEX (sys_player_id),
    INDEX (system_at)
);
```

### 4. _BaseLog を継承

```php
namespace App\Models\Log;

use App\Models\Log\_BaseLog;

class LogEquipment extends _BaseLog
{
    protected $connection = 'log';
    protected $table = 'log_equipment';
    
    // const UPDATED_AT = null; // _BaseLogで定義済み
    
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_equipment_id',
        'mst_equipment_id',
        'system_at',  // ビジネスロジック用
        // created_atは$fillableに含めない（MySQL自動設定）
    ];
    
    protected $casts = [
        'system_at' => 'immutable_datetime',
        // created_at, updated_atは$castsに含めない
    ];
}
```

### 5. ENUM型は使用しない

- **ログテーブルではENUM型を使用禁止**
- STRING型を使用

---

## テーブル例

### log_equipment（装備強化ログ）

```sql
CREATE TABLE log_equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unique_request_id VARCHAR(64) NOT NULL COMMENT 'リクエスト識別ID',
    sys_player_id BIGINT UNSIGNED NOT NULL,
    trx_equipment_id BIGINT UNSIGNED NOT NULL,
    mst_equipment_id BIGINT UNSIGNED NOT NULL,
    before_grade INT UNSIGNED,
    after_grade INT UNSIGNED,
    before_level INT UNSIGNED,
    before_level_exp BIGINT UNSIGNED,
    after_level INT UNSIGNED,
    after_level_exp BIGINT UNSIGNED,
    system_at DATETIME NOT NULL COMMENT 'ビジネスロジック用の日時',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '実際の記録時刻',
    INDEX (unique_request_id),
    INDEX (sys_player_id),
    INDEX (system_at),
    INDEX (created_at)
);
```

### log_item（アイテム増減ログ）

```sql
CREATE TABLE log_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unique_request_id VARCHAR(64) NOT NULL,
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_item_id BIGINT UNSIGNED NOT NULL,
    change_type VARCHAR(50) NOT NULL COMMENT 'use, obtain, sell等',
    before_quantity INT UNSIGNED,
    change_quantity INT,
    after_quantity INT UNSIGNED,
    reason VARCHAR(100) COMMENT '増減理由',
    system_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (unique_request_id),
    INDEX (sys_player_id),
    INDEX (mst_item_id),
    INDEX (system_at)
);
```

---

## デバッグ機能との連動

ゲーム開発では、テスト目的で日時を変更する機能が必要です。

```php
// デバッグ設定: システム日時を1週間後に
$debugTime = now()->addWeek();

// ログ記録時
LogEquipment::create([
    'sys_player_id' => $playerId,
    'system_at' => $debugTime,  // 1週間後の日時（デバッグ設定）
    // ...
]);

// データベースに記録される:
// system_at: 2026-03-02 10:00:00（デバッグで設定した1週間後）
// created_at: 2026-02-24 10:00:00（実際の現在時刻、MySQL自動設定）
```

---

## チェックリスト

- [ ] テーブル名は`log_`プレフィックス
- [ ] 単数形を使用
- [ ] PRIMARY KEYは自動インクリメント
- [ ] `system_at`カラムを追加（NOT NULL）
- [ ] `updated_at`は定義しない
- [ ] `created_at`はMySQLのDEFAULT CURRENT_TIMESTAMP
- [ ] Modelは`_BaseLog`を継承
- [ ] `$fillable`に`system_at`を含める（`created_at`は含めない）
- [ ] `$casts`に`system_at`のみ含める（`created_at`, `updated_at`は含めない）
- [ ] ENUM型を使用していない

---

## 関連ドキュメント

- [データベース設計 - Log Modelの特別なルール](../database.md#log-modelの特別なルール) - 詳細なルール
- [コーディング規約](../coding-standards.md#6-modelの実装ルール) - Model実装

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
