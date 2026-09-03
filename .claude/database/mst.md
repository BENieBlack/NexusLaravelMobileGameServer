# マスターDB (mst_*)

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)

このドキュメントでは、マスターDB（`mst_*`）の設計ルールを定義します。

---

## 概要

マスターDBは、**ゲームの定義データ（アイテム、ユニット、ガチャ、課金商品等）**を格納します。
デプロイで投入するもので、実行時には書き換えません（`_BaseMst` が書き込みを例外で止め、
シーダー・デプロイ処理だけが `allowWrites()` で許可します）。

マイグレーションは**パッケージ側**の `packages/nexus-*/database/migrations/mst/` に置きます。
`api/database/migrations/mst/` にあるのは Laravel の `cache` / `jobs` 系テーブルだけで、
これらは `mst_` プレフィックスを持たず、マスターデータではありません。

---

## 対象テーブル

多言語テーブル（`__l10n`）を含めて32本あります。

### nexus-resource

| テーブル名 | 用途 |
|-----------|------|
| `mst_unit` / `mst_unit__l10n` | ユニット（キャラクター）マスター |
| `mst_item` / `mst_item__l10n` | アイテムマスター |
| `mst_equipment` / `mst_equipment__l10n` | 装備マスター |
| `mst_unit_level` | ユニットレベルマスター |
| `mst_equipment_level` | 装備レベルマスター |

### nexus-gacha

| テーブル名 | 用途 |
|-----------|------|
| `mst_gacha` / `mst_gacha__l10n` | ガチャマスター |
| `mst_gacha_cost` | ガチャコストマスター |
| `mst_gacha_rarity_rate` | ガチャレアリティ排出率マスター |
| `mst_gacha_prize` | ガチャ景品マスター |
| `mst_gacha_step` | ガチャステップマスター |
| `mst_gacha_step_bonus` | ガチャステップボーナスマスター |
| `mst_gacha_step_bonus_content` | ガチャステップボーナスコンテンツマスター |

### nexus-core-billing

| テーブル名 | 用途 |
|-----------|------|
| `mst_billing_platform_product` | プラットフォーム課金商品マスター |
| `mst_in_app_purchase` / `mst_in_app_purchase__l10n` | アプリ内課金商品マスター |
| `mst_in_app_purchase_content` | アプリ内課金商品コンテンツマスター |
| `mst_in_app_purchase_effect` | アプリ内課金商品効果マスター |

### nexus-mailbox

| テーブル名 | 用途 |
|-----------|------|
| `mst_message` / `mst_message__l10n` | メッセージマスター |
| `mst_mailbox` | メールボックスマスター |
| `mst_mailbox_content` | メールボックスコンテンツマスター |

### nexus-vip

| テーブル名 | 用途 |
|-----------|------|
| `mst_vip_level` | VIPレベルマスター |
| `mst_vip_level_reward` | VIPレベルアップ報酬マスター |
| `mst_vip_login_bonus` | VIPログインボーナス設定マスター |
| `mst_vip_login_bonus_content` | VIPログインボーナス報酬内容マスター |

### nexus-login

| テーブル名 | 用途 |
|-----------|------|
| `mst_login_bonus` | ログインボーナス設定マスター |
| `mst_login_bonus_content` | ログインボーナス報酬内容マスター |

### nexus-level

| テーブル名 | 用途 |
|-----------|------|
| `mst_player_level` | プレイヤーレベルマスター |

なお `nexus-album` はテーブルを作らず、`mst_unit` / `mst_equipment` / `mst_item` に
`is_album_target`（アルバムに載せる対象か）を追加します。

---

## 設計原則

### 1. 命名規約

- プレフィックス: `mst_`
- 単数形を使用
- スネークケース
- 多言語テーブルは親テーブル名 + `__l10n`（アンダースコア2つ）
  - `i18n` は使わない。各言語の文言を持つテーブルが指しているのは
    localization であり、語が2種類あると寄せ先の判断が毎回発生する

### 2. PRIMARY KEY

- **意味のある文字列ID**を主キーにする
  - `$table->string('id')->primary()`
  - マスターデータはデプロイで投入するため、`item_heal_001` や `vip_login_lv5` のように
    IDそのものが読めるほうが、シーダー・企画データ・ログの突合が楽になる
- 自動インクリメント（`$table->id()`）は例外
  - 現状は `mst_billing_platform_product` / `mst_in_app_purchase` /
    `mst_vip_login_bonus_content` の3本のみ
- 一意性を複合キーで保証できるなら `id` カラムは持たない
  - `__l10n` テーブル: `[親テーブルID, language]`
  - コンテンツテーブル: `[親テーブルID, content_type, content_mst_id]`
  - レベルテーブル: `mst_player_level` は `level`、
    `mst_unit_level` / `mst_equipment_level` は `[rarity, level]`
- 複合主キーには**名前を付ける**（`$table->primary([...], 'pk_item_language')`）
  - 既存の複合主キーは全て名前付きなので揃える

### 3. ENUM型の使用

- **マスターテーブルではENUM型を使用可能**
- デプロイ時に計画的に更新できるため、trx/sys と違って選択肢の追加が事故になりにくい

```php
$table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
$table->enum('element', ['Fire', 'Water', 'Wind', 'Earth', 'Light', 'Dark'])->comment('属性');
```

### 4. 多言語対応

言語に依存しない情報は親テーブル、各言語の文言は `__l10n` テーブルに分けます。
命名・複合主キー・モデル実装の詳細は
[多言語対応テーブル（L10nテーブル）](../database.md#多言語対応テーブルl10nテーブル)を参照してください。

- 親テーブル: ID、フラグ、数値、日時など言語に依存しないもの
- `__l10n` テーブル: `name`、`description` などの文言
  - 主キーは `[親テーブルID, language]` の複合。`id` カラムは持たない
  - `language` は `$supportedLanguages`（`config('language.supported')` と同じ並び）のENUM

### 5. deploy_key管理

全テーブルの先頭に `deploy_key` を置き、インデックスを張ります。
`_BaseMst` が `fillable` に持っているため、モデル側で追加する必要はありません。

```php
$table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
// ...
$table->index('deploy_key');
```

---

## テーブル例

`packages/nexus-resource/database/migrations/mst/` の実物です。

### mst_item

```php
Schema::connection('mst')->create('mst_item', function (Blueprint $table) {
    $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
    $table->string('id')->primary()->comment('アイテムID');
    $table->string('type')->comment('アイテムタイプ');
    $table->string('effect')->comment('効果');
    $table->float('value')->comment('効果値');

    // 残高として持つアイテム（gold, coin, 各種ポイントなど）。
    // trueなら trx_item ではなく trx_wallet 系で管理する
    $table->boolean('is_wallet')->default(false)->comment('Wallet管理フラグ');

    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

    $table->index('deploy_key');
    $table->index('is_wallet');
});
```

### mst_item__l10n

```php
Schema::connection('mst')->create('mst_item__l10n', function (Blueprint $table) {
    $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
    $table->string('mst_item_id')->comment('アイテムID');
    $table->enum('language', $this->supportedLanguages)->comment('言語コード');
    $table->string('name')->comment('アイテム名');
    $table->text('description')->nullable()->comment('説明');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

    $table->primary(['mst_item_id', 'language'], 'pk_item_language');
    $table->index('deploy_key');
});
```

---

## チェックリスト

- [ ] テーブル名は`mst_`プレフィックス
- [ ] 単数形を使用
- [ ] マイグレーションはパッケージ側の `database/migrations/mst/` に置く
- [ ] `Schema::connection('mst')` を明示する
- [ ] PRIMARY KEYは意味のある文字列ID（複合キーで足りるなら`id`を持たない）
- [ ] 複合主キーには名前を付ける
- [ ] deploy_keyカラムとインデックスを追加
- [ ] 多言語は`__l10n`テーブルに分割（`i18n`は使わない）
- [ ] ENUM型を適切に使用
- [ ] Modelは`_BaseMst`を継承する（`$connection = 'mst'`は基底クラスが持つ）

---

## 関連ドキュメント

- [データベース設計](../database.md) - 全体の設計方針
- [多言語対応](../database.md#多言語対応テーブルl10nテーブル) - 詳細な設計
- [ENUM型の使用ルール](../database.md#enum型の使用ルール) - ENUM使用の注意点

---

[← データベース設計に戻る](../database.md) | [← ホームに戻る](../README.md)
