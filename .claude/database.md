# データベース

このファイルを読むべきとき: テーブルやカラムを追加・変更するとき、マイグレーションを書く・流すとき、どのDB/接続にデータを置くか決めるとき。
トランザクションの流れ（UnitOfWork・QueryManager）は [architecture.md](architecture.md)、Model クラスの書き方は [coding-standards.md](coding-standards.md) を参照。

## 1. DB構成と接続

API（api/）の接続は `api/config/database.php`、Tool（tool/）の接続は `tool/config/database.php` に定義されている。DB名は `{APP_NAME}-{APP_ENV}-xxx`（.env.example では `nexus-local-xxx`）。

| 接続名 | コンテナ | DB名の末尾 | 置くテーブル | 使う側 |
|---|---|---|---|---|
| `sys` | db-system | `-system` | `sys_*`（全プレイヤー共通） | API |
| `mst` | db-master | `-master` | `mst_*`（マスターデータ、読み取り専用） | API |
| `trx1`..`trxN` | db-shard1..N | `-shard`（全シャード共通） | `trx_*` と `log_*` | API |
| `trx` | db-shard1 | `-shard` | `trx1` の別名（テスト互換用） | API |
| `admin` | db-admin | `-admin` | `adm_*`（管理者アカウント・権限） | Tool |
| `tool` | db-tool | `-tool` | `tol_*`（運営ツールの機能データ） | Tool |

- シャード数 N は `DB_SHARD_COUNT`（.env.example は 3、`api/phpunit.xml` は 2、未設定時は 2）。docker-compose.yml のシャードコンテナは3台なので3を超えない。
- ホスト名は `DB_SHARD_HOST`（既定 `db-shard`）にシャード番号を付けたもの。シャード個別に変えるときだけ `DB_SHARD{N}_HOST` などで上書きする。
- **log 専用のDB・接続は無い**。`log_*` は対応する `trxN` と同じDBに入る（`packages/nexus-pitr/src/Logger/ShardMapper.php::resolveLogConnection()` が `trxN` → `trxN` を返す）。
- 既定接続（`DB_CONNECTION`）は sqlite のまま。どのDBへのクエリも接続名を明示する。
- TiDB モード（`DB_IS_TIDB=true`）ではシャード割り当てをせず常に `trx1` を使い、trx/log の単一主キー `id` を UUID に変換する（`packages/nexus-tidb`）。主キーの型と書き込みの偏りは11節。

### sys と trx の使い分け

- **trx**: プレイヤー本人だけが読み書きするデータ（所持品・通貨・進行状況）。プレイヤー単位でシャードに分かれる。
- **sys**: 他プレイヤーからも参照される・シャードを跨いで検索するデータ（`sys_player` の名前・レベル等、ギルド・フレンド・チャット）と、シャード管理・デプロイ管理・メンテナンス。全プレイヤーが1テーブルに入るので全件取得は禁止（`api/tests/Architecture/NoFullTableSelectOnSysTest.php`）。
- プレイヤーIDは `sys_player.id` で一元採番する。trx 側はそれを `sys_player_id` として持つ（`trx_player` は `sys_player_id` が主キー）。

### 書き込みとモデル基底クラス

- 基底モデルは `packages/nexus-core/src/Models/{Sys,Mst,Trx,Log}/_Base*.php`、API側の拡張は `api/app/Models/{Sys,Mst,Trx,Log}/_Base*.php`。
- `_BaseSys` は `sys`、`_BaseMst` は `mst` に固定。`_BaseTrx` / `_BaseLog` は `$connection = null` で、ログイン中プレイヤーのシャードを自動で解決する（未ログイン時は `trx1`）。
- 書き込みは Repository 経由で `executeWithTransaction()` の中で行う（Model の直接 save は例外。詳細は architecture.md）。`mst` は読み取り専用で、シーダーだけ `_BaseMst::allowWrites()` で書く。

## 2. シャーディングの仕組み

テーブル定義: `packages/nexus-core/database/migrations/sys/2026_01_01_000001_create_core_tables.php`

1. `sys_sharding`（name = `trx_sharding`, strategy）→ `sys_sharding_node`（`node_no` が接続名 `trx{node_no}` の番号）→ `sys_sharding_node_player`（`sys_player_id` が主キー、どのノードか）。
2. 割り当てはプレイヤー作成時に `api/app/Domain/Sharding/Services/ShardAssignmentService.php::assign()` で一度だけ行う。対象は status=active かつ is_writable で、接続設定がある（`config('database.pitr.active_trx_connections')`）ノードだけ。strategy=hash なら `sys_player_id % ノード数`、それ以外は人数の少ないノード。
3. リクエスト中は `ApiSession::resolveConnectionName()`（`api/app/Persistence/ApiSession.php`）が `trx{node_no}` を返す。リクエスト内はインスタンスに、リクエスト間は Redis `shard:player:{sys_player_id}` に1時間キャッシュする。
4. 開発環境のシャード定義は `api/database/seeders/SysShardingSeeder.php` で投入する。

## 3. マイグレーション

### 置き場所

| グループ | 置き場所 | 流し先 |
|---|---|---|
| sys | `packages/*/database/migrations/sys/`、`api/database/migrations/sys/` | `sys` |
| mst | `packages/*/database/migrations/mst/`、`api/database/migrations/mst/` | `mst` |
| trx | `packages/*/database/migrations/trx/` | `trx1`..`trxN` 全部 |
| log | `packages/*/database/migrations/log/`、`api/database/migrations/log/` | `trx1`..`trxN` 全部 |
| adm | `tool/database/migrations/adm/` | `admin` |
| tol | `tool/database/migrations/tol/` | `tool` |

- テーブルは機能を持つパッケージのマイグレーションに書く。`api/database/migrations/` には Laravel 標準テーブルと、パッケージに属さない `log_action_*` だけがある。
- ファイル名は `2026_01_01_000001_create_{機能}_tables.php`、log は `create_{機能}_log_tables.php`。trx と log は同じDBの同じ `migrations` テーブルに記録されるので、両グループを合わせて同名ファイルがあってはいけない（`api/tests/Architecture/MigrationNameUniquenessTest.php`）。
- trx/log のパスは `packages/nexus-pitr/src/Support/ShardMigrationPaths.php` が `packages/` を走査して集める。パッケージを足しても一覧を直す必要はない。`nexus-tidb` の変換マイグレーションは必ず最後に流れる。

### 書き方

- API側（sys/mst/trx/log）は `Schema::create('テーブル名', ...)` と書き、**接続名を書かない**。流すときに `--database` で接続を指定するため（trx は同じファイルを全シャードに流す）。
- Tool側（adm/tol）は `Schema::connection('admin')` / `Schema::connection('tool')` を書く。
- 1ファイルに1パッケージ・1グループ分のテーブルをまとめる。見本: `packages/nexus-resource/database/migrations/{mst,trx,log}/`、`packages/nexus-core/database/migrations/sys/`。

### 既存ファイルを書き換えてよい期間

今は**共有の検証環境を作るまで**の期間で、既存のマイグレーションファイルを直接書き換えてよい（列の追加・型の変更・テーブルの追加も既存の `create_{機能}_tables.php` に書く）。

- 書き換えた変更は通常の `make migrate` では反映されない。手元では `make migrate-fresh` → `make seed` で作り直す。テストDBは指紋の変化で自動的に作り直される
- **共有の検証環境（ステージングなど、作り直せないデータを持つ環境）を作る時点で凍結する**。凍結後は既存のファイルを書き換えず、変更は新しいファイル（`2026_MM_DD_HHMMSS_add_xxx_to_yyy.php` など）で足す。消せないデータがある環境では `migrate-fresh` が使えないため
- 凍結したら、この節と CLAUDE.md を「既存のマイグレーションは書き換えない」に改め、凍結した日付をここに書く

### 実行

| コマンド | 内容 |
|---|---|
| `make migrate` | sys と mst を `--database` 指定で、trx を `php artisan trx:migrate`、log を `php artisan pitr:migrate`（どちらも全シャード） |
| `make migrate-fresh` | sys/mst を `migrate:fresh`、trx を `trx:migrate --fresh`、log は通常の `pitr:migrate` |
| `trx:rollback` / `pitr:rollback` | 全シャードでロールバック（`--step`） |

- **引数なしの `php artisan migrate` は使わない**。既定接続が sqlite なので、意図しないDBに流れる。
- **`pitr:migrate --fresh` は使わない**。trx と log は同じDBにあるので、log 側で fresh すると直前に作った trx のテーブルまで消える。全部作り直すときは `make migrate-fresh`。
- 既存のマイグレーションファイルを書き換えたときは通常の migrate では反映されない。`make migrate-fresh` → `make seed` で作り直す（上の「既存ファイルを書き換えてよい期間」）。
- Tool の adm/tol は `make migrate` に含まれない。tool-php コンテナで `php artisan migrate --path=database/migrations/adm --database=admin` と `--path=database/migrations/tol --database=tool` を実行する（[tool.md](./tool.md)）。

## 4. テーブル命名

- 接頭辞で置き場所を表す: `sys_` `mst_` `trx_` `log_` `adm_` `tol_`。接頭辞と置き場所のDBを必ず一致させる。
- 単数形・スネークケース（`trx_item`、`mst_unit_level`）。単複同形や不可算名詞で間違えやすいので複数形にしない。
- 多言語テーブルは `<親テーブル名>__l10n`（アンダースコア2つ）。6章。
- ログテーブルは種類で接頭辞を分ける（8章）: `log_action_*`、`log_change_trx_*`、`log_pitr_*`。
- Laravel 標準テーブル（cache, cache_locks, jobs, job_batches, failed_jobs）は sys/mst/adm/tol にだけある（`api/database/migrations/{sys,mst}/0001_01_01_000001_create_laravel_tables.php`、`tool/database/migrations/{adm,tol}/0001_01_01_000001_create_laravel_tables.php`）。adm には `sessions` もある。trx/log には作らない。

## 5. カラム規約

### ID と主キー

- sys / trx / log / adm / tol: `$table->id()`（BIGINT UNSIGNED AUTO_INCREMENT）。TiDB モードでは trx/log の `id` だけが UUID（VARCHAR）に変わり、sys / adm / tol と `sys_player_id` は連番のまま（11節）。
- mst: **文字列の `id`**（`$table->string('id')->primary()`、例 `unit_knight_001`）。参照側の `mst_xxx_id` も string にする。
- 複合主キーで一意になる trx/mst テーブルは `id` を持たなくてよい（`trx_item` = `sys_player_id, mst_item_id`、`mst_unit_level` = `rarity, level`）。Model 側は `$primaryKey` を配列にして `api/app/Traits/CompositePrimaryKeyTrait.php` を使う。
- 主キー・ユニークキーに名前を付けるときは `pk_xxx` / `uk_xxx`。

### 参照カラムの名前

- 他テーブルを指すカラムは `{参照先テーブル名}_id`（`sys_player_id`、`mst_unit_id`、`trx_equipment_id`）。`player_id` や `item_id` のように省略しない。
- 同じテーブルを複数指すときは役割を前に付ける（`request_sys_player_id`、`receive_sys_player_id`）。
- 外部サービス側の識別子は `platform_` 等を付けて区別する（`mst_billing_platform_product.platform_product_id`）。
- 種類が混ざる参照は `content_type` + `content_mst_id`（`mst_login_bonus_content` など報酬系）。
- TiDB の UUID 変換はこの命名規約を使って変換対象の参照カラムを見つけるので、規約から外れた名前にすると変換が漏れる（`packages/nexus-tidb/database/migrations/trx/2026_01_01_999999_convert_trx_ids_to_uuid.php`）。

### 外部キー制約を張らない

全DBで `foreign()` / `constrained()` を使わない。シャードやDBを跨ぐ参照が多く、制約では整合性を保証できないうえ、UnitOfWork のバッチINSERTの順序を縛るため。代わりに参照カラムに `index()` を張り、整合性はアプリ側で保証する。

### コメント必須

全カラムに `->comment()` を付ける。参照カラムは参照先を書く（`'sys_playerテーブルのID'`）。ENUM を使えない列（後述）は、取りうる値をコメントに列挙する（`'変更理由 (consume_quest, natural_recovery, ...)'`）。

### 日時

- 型は `dateTime()`。`timestamp()` / `timestamps()` は使わない（2038年問題とタイムゾーン変換を避ける）。
- `created_at` / `updated_at` は次の形で明示的に書く:
  `dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))`、
  `dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))`
- 時刻は UTC（`config/app.php` の timezone が UTC）。アプリから入れる日時は `ClockUtility`（`Nexus\Core\Utilities\ClockUtility`）で取得する。`now()` や Carbon を直接使わない。
- 列名は `*_at`（`last_login_at`、`expires_at`、`start_at` / `end_at`）。

### 論理削除・bool

- trx の論理削除は `is_delete`（boolean, default false）。`deleted_at` / `softDeletes()` は使わない。論理削除は Repository の `softDeleteModel()`、物理削除は `hardDeleteModel()`。
- bool は `is_` / `has_` で始める（`is_active`、`is_wallet`、`is_paid`）。

### 値のケースと ENUM

- DBに入る文字列の値はすべて**スネークケース**（`app_store`、`google_play`、`pay_pal`、`stripe`、`apple`、`google`、`sub_master`）。PHP の定数・Enum の値も同じ（例 `packages/nexus-core-billing/src/Constants/BillingConst.php`）。
- 例外（外部規格・慣習に合わせる）: `language`（`ja`、`zh-TW`）、`rarity`（`UR` `SSR` `SR` `R` `UC` `C`）、`log_change_trx.operation`（`INSERT` `UPDATE` `DELETE`）。
- **ENUM型を使ってよいのは mst / adm / tol だけ**。sys / trx / log は常時書き込まれるテーブルで、値を足す `ALTER TABLE ... MODIFY` がテーブルロックを起こしサービス停止が必要になるため、`string()` にして値をコメントに書く。値の検証はアプリ側（定数・バリデーション）で行う。

### インデックス

- 検索・ソートに使う列と参照カラムに `index()` を張る。
- `unique()` や `primary()` を付けた列に同じ `index()` を重ねない（重複インデックスになる）。

## 6. l10n（多言語テーブル）

- 多言語は `<親テーブル名>__l10n` テーブルで持つ。**i18n という名前は使わない**。
- 列は `deploy_key`、`<親テーブル名>_id`、`language`、翻訳する列（`name`、`description` など）、`created_at`、`updated_at`。**`id` 列は作らない**。
- 主キーは名前付き複合主キー `(<親テーブル名>_id, language)`（例 `$table->primary(['mst_unit_id', 'language'], 'pk_unit_language')`）。
- 親テーブル側には翻訳する列を持たない。
- 対応言語は `api/config/language.php` の `supported`（既定 `ja`）。リクエストの言語は `ResolveLanguage` ミドルウェアが解決し、`ApiSession::getLanguage()` で取れる。マイグレーションの `language` ENUM は同じ一覧を `$supportedLanguages` に持っているので、言語を足すときは両方直す。
- 実例: `packages/nexus-resource/database/migrations/mst/2026_01_01_000001_create_resource_tables.php` の `mst_unit__l10n` / `mst_item__l10n` / `mst_equipment__l10n`。

## 7. deploy_key とマスター

- mst の全テーブル（`__l10n` を含む）に `deploy_key` を持たせる: `$table->integer('deploy_key')->default(202601010)->comment('デプロイキー')` と `$table->index('deploy_key')`。
- deploy_key の値は `YYYYMMDD * 100 + その日の連番`（10桁、例 `2026090401`）。生成は `tool/app/Services/MasterDeployService.php::generateDeployKey()`。
- 配信管理テーブルは sys にある（`packages/nexus-version/database/migrations/sys/2026_01_01_000001_create_version_tables.php`）:
  - `sys_deploy_master` / `sys_deploy_master_table`: マスターデータのバージョン（ハッシュ）とテーブルごとのファイル
  - `sys_deploy_asset`: アセットのバージョン
  - `sys_deploy`: クライアントに配る組み合わせ（`deploy_key`、`start_at`、`is_active`）
- マスターの投入は Tool（スプレッドシート → `MasterImportService` → mst DB → `MasterDataExporter` で SQLite 出力 → `MasterDeployService` で sys_deploy 登録）。開発環境では `api/database/seeders/Mst*Seeder.php` で投入する。
- API 側のバージョンチェックとマスター配信の流れは [api.md](api.md)。

## 8. ログテーブル

`log_*` は対応するシャード（`trxN`）と同じDBに入り、`_BaseLog` / `_BaseLogRepository` が接続を解決する。書き込みはトランザクションの最後にまとめて行われる（architecture.md）。

| 種類 | 内容 | 定義 |
|---|---|---|
| `log_action_*` | ゲーム内の行動の記録（APIアクセス、ガチャ、課金、強化、ログインボーナス受取など） | `api/database/migrations/log/`、各パッケージの `log/` |
| `log_change_trx` | trx テーブルの変更履歴（PITR 用、全テーブル共通、`id` は UUID） | `packages/nexus-pitr/database/migrations/log/` |
| `log_change_trx_*` | テーブル別の変更履歴（before/after と差分） | 各パッケージの `log/` |
| `log_pitr_*` | PITR の復旧・検証の記録 | `packages/nexus-pitr/database/migrations/log/` |

- 追記専用。更新・削除しない。
- 日時は2種類持つ: `system_at`（アプリの時刻。`ClockUtility::now()` で入れるので、デバッグで時刻をずらすとそれに連動する）と `created_at`（DBが実際に記録した時刻）。範囲検索は `system_at` にインデックスを張って使う。
- `_BaseLog` は `$timestamps = true` なので、Model 経由で書くログテーブルには `updated_at` も作る。
- `sys_player_id` と `unique_request_id`（1リクエストのログをまとめて追える）を持たせ、`sys_player_id` にインデックスを張る。
- PITR（`log_change_trx` への記録）は `packages/nexus-pitr/src/Traits/LogsChanges.php` と `src/Logger/TrxChangeLogger.php`。

## 9. 通貨（ダイヤ）

### 呼び方

- 課金通貨は「ダイヤ」。有償ダイヤ（課金で購入）と無償ダイヤ（報酬などで入手）を区別する。
- テーブル・カラム・クラス・変数は `diamond` を使う（`trx_diamond`、`paid_amount` / `free_amount`、`DiamondService`）。gem / stone / crystal は使わない。
- ゴールドやイベントコインなどダイヤ以外の通貨は `mst_item.is_wallet = true` のアイテムとして `trx_wallet` 系で持つ。

### テーブル

定義: `packages/nexus-core-billing/database/migrations/trx/2026_01_01_000001_create_billing_tables.php`、`packages/nexus-wallet/database/migrations/trx/2026_01_01_000001_create_wallet_tables.php`

| テーブル | 主キー | 役割 |
|---|---|---|
| `trx_diamond` | `(sys_player_id, platform)` | 現在の有償・無償ダイヤ数。プラットフォーム（`apple` / `google`）ごとに分ける |
| `trx_diamond_balance` | `id` | 購入単位の残高。`billing_platform`、`purchase_amount`、`current_amount`、返金計算用の `unit_price` を持つ。`id` 昇順が購入順 |
| `trx_wallet` | `(sys_player_id, mst_item_id)` | ダイヤ以外の通貨の現在値（有償・無償） |
| `trx_wallet_balance` | `id` | 取得単位の残高と有効期限 `expire_at` |
| `trx_in_app_purchase` | `(sys_player_id, billing_platform, mst_in_app_purchase_id)` | 商品ごとの購入回数。`(billing_platform, transaction_id)` がユニーク |

- `platform` は端末側（`apple` / `google`）、`billing_platform` は決済先（`app_store` / `google_play` / `pay_pal` / `stripe`）。同じ端末でも決済先が違えば別の残高レコードになる。
- 有償ダイヤの加算と残高レコード作成は `api/app/Domain/InAppPurchase/Services/InAppPurchaseDiamondBalanceService.php::addPaidDiamondWithBalance()`。
- 消費は `packages/nexus-resource/src/Services/DiamondService.php::consumeDiamond()`（無償 → 有償の順。`$isPaidOnly = true` で有償のみ）。
- ダイヤ以外の通貨の加算・消費・期限切れ処理は `packages/nexus-wallet/src/Services/WalletService.php`（有償優先 → 期限の近い順 → ID順）。
- 残高レコードは削除せず、`current_amount` を減らして履歴を残す。

## 10. DBごとの主なテーブル

マイグレーションはすべて `<場所>/database/migrations/<グループ>/` の下にある。

### sys

| パッケージ | テーブル |
|---|---|
| nexus-core | `sys_player`, `sys_player_device`, `sys_sharding`, `sys_sharding_node`, `sys_sharding_node_player` |
| nexus-core-auth | `sys_player_token` |
| nexus-version | `sys_deploy_master`, `sys_deploy_master_table`, `sys_deploy_asset`, `sys_deploy` |
| nexus-maintenance | `sys_maintenance` |
| nexus-friend | `sys_friend_apply` |
| nexus-guild | `sys_guild`, `sys_guild_member`, `sys_guild_apply` |
| nexus-chat | `sys_chat_room`, `sys_chat_room_member`, `sys_chat_message` |

### mst

| パッケージ | テーブル |
|---|---|
| nexus-resource | `mst_unit`, `mst_item`, `mst_equipment`（各 `__l10n` あり）, `mst_unit_level`, `mst_equipment_level` |
| nexus-level | `mst_player_level` |
| nexus-core-billing | `mst_billing_platform_product`, `mst_in_app_purchase`（`__l10n` あり）, `mst_in_app_purchase_content`, `mst_in_app_purchase_effect` |
| nexus-gacha | `mst_gacha`（`__l10n` あり）, `mst_gacha_cost`, `mst_gacha_rarity_rate`, `mst_gacha_prize`, `mst_gacha_step`, `mst_gacha_step_bonus`, `mst_gacha_step_bonus_content` |
| nexus-login | `mst_login_bonus`, `mst_login_bonus_content` |
| nexus-mailbox | `mst_message`（`__l10n` あり）, `mst_mailbox`, `mst_mailbox_content` |
| nexus-reward-track | `mst_reward_track`, `mst_reward_track_line`, `mst_reward_track_milestone`, `mst_reward_track_content` |
| nexus-vip | `mst_vip_level`, `mst_vip_level_reward`, `mst_vip_login_bonus`, `mst_vip_login_bonus_content` |
| nexus-tag | `mst_tag`（`__l10n` あり）, `mst_tag_content`（タグとユニット・アイテム等の多対多） |
| nexus-mission | `mst_mission`（`__l10n` あり）, `mst_mission_content` |
| nexus-trade | `mst_trade`（交換所。`__l10n` あり）, `mst_trade_product`（商品。消費種別・消費個数・交換回数の上限。`__l10n` あり）, `mst_trade_product_content`（交換で手に入るもの） |

### trx（各シャード）

| パッケージ | テーブル |
|---|---|
| nexus-core | `trx_player`, `trx_player_sns` |
| nexus-resource | `trx_unit`, `trx_equipment`, `trx_item` |
| nexus-core-billing | `trx_diamond`, `trx_diamond_balance`, `trx_in_app_purchase`, `trx_in_app_purchase_effect` |
| nexus-wallet | `trx_wallet`, `trx_wallet_balance` |
| nexus-stamina | `trx_stamina` |
| nexus-gacha | `trx_gacha` |
| nexus-login | `trx_login_bonus` |
| nexus-mailbox | `trx_mailbox` |
| nexus-album | `trx_album` |
| nexus-notification | `trx_notification` |
| nexus-reward-track | `trx_reward_track`, `trx_reward_track_line`, `trx_reward_track_milestone` |
| nexus-vip | `trx_vip_login_bonus` |
| nexus-mission | `trx_progress`（出来事×タグの通算カウンタ）, `trx_mission`（受け取り記録） |
| nexus-trade | `trx_trade_product`（商品ごとの交換回数） |

### log（各シャード、trx と同じDB）

- api: `log_action_api_access`, `log_action_player_update`, `log_action_item_change`, `log_action_gacha_draw`, `log_action_unit_levelup`, `log_action_equipment_levelup`, `log_action_in_app_purchase`
- nexus-login: `log_action_login_bonus_receive`／nexus-vip: `log_action_vip_login_bonus_receive`, `log_action_vip_point_change`／nexus-trade: `log_action_trade`
- nexus-pitr: `log_change_trx`, `log_pitr_recovery`, `log_pitr_verification`
- `log_change_trx_<trxテーブル名>`: 元の trx テーブルを持つパッケージ（core: player, player_sns／resource: unit, equipment, item／core-billing: in_app_purchase, in_app_purchase_effect, diamond, diamond_balance／wallet: wallet, wallet_balance／stamina／gacha／mailbox）

### adm（Tool、`tool/database/migrations/adm/2026_01_01_000001_create_game_tables.php`）

`adm_account`, `adm_role`, `adm_page`, `adm_permission`, `adm_account_role`, `sessions`

### tol（Tool、`tool/database/migrations/tol/2026_01_01_000001_create_game_tables.php`）

`tol_master_status`, `tol_asset_status`, `tol_banner`, `tol_cache_control`, `tol_maintenance`, `tol_notice`, `tol_dashboard_retention`, `tol_dashboard_access_cache`

## 11. TiDB モードの主キーと書き込みの偏り

TiDB モード（`DB_IS_TIDB=true`）で本番を動かすときに、テーブル設計で気にすること。変換の実体は `packages/nexus-tidb`。

### 主キーの型

| テーブル | MySQL | TiDB モード |
|---|---|---|
| sys / adm / tol の `id` | BIGINT UNSIGNED AUTO_INCREMENT | 同じ（変換しない） |
| trx / log の `id`（`id` だけが主キー） | BIGINT UNSIGNED AUTO_INCREMENT | VARCHAR の UUID。AUTO_INCREMENT ではなく、値は `NexusTidb\Traits\UuidPrimaryKey` が作る |
| trx / log の `{trxテーブル名}_id`（例 `trx_unit_id`） | BIGINT UNSIGNED | 参照先に合わせて UUID |
| `sys_player_id`（どのテーブルでも） | BIGINT UNSIGNED | 同じ。参照先の `sys_player` が trx DB に無いので変換されない |
| 複合主キー（`trx_item` など）、`sys_player_id` が主キー（`trx_player` など） | そのまま | 同じ |
| mst の `id` | 文字列 | 同じ |

- 変換は `convert_{trx,log}_ids_to_uuid.php` が最後に流れて行う。参照カラムは列名（`{参照先テーブル名}_id`）から見つけるので、命名規約（5節）から外れると変換が漏れる
- `sys_player.id` は TiDB モードでも連番。プレイヤーの採番方法は MySQL と同じ

### 連番の主キーで何が起きるか

TiDB はデータを主キーの範囲（リージョン）ごとに分けて置く。連番のキーは新しい行が常に末尾の同じリージョンに入るため、そこに書き込みが集中する（ホットスポット）。また TiDB の AUTO_INCREMENT は一意だが単調増加を保証しないので、`id` の大小を作成順として使わない。

複合主キーで行を主キー順に並べて置く（クラスタ化インデックス）場合、このリポジトリのテーブルは次のようになる。クラスタ化するかは `tidb_enable_clustered_index` とテーブル定義で決まり、既定値は TiDB のバージョンで違うので、使うバージョンで確かめる。

- **読み取りには有利**: `trx_item`（`sys_player_id, mst_item_id`）は同じプレイヤーの行が隣り合うので、`queryOrMemory()` の全件読みは1回の範囲読みで済む
- **既存プレイヤーの更新は分散する**: プレイヤーIDが範囲全体に散っているため
- **新規プレイヤーの書き込みは末尾に集中する**: 先頭の `sys_player_id` が連番なので、新しいプレイヤーの行は常に末尾のリージョンに入る。新規プレイヤーは初期配布や序盤で書き込みが多く、リリース直後や広告で登録が急増すると詰まりやすい

`id` を UUID にしたテーブル（`trx_unit` など）も、これだけでは解決しない。行そのものは散らばるが、プレイヤーの行を引くための `sys_player_id` のインデックスが連番順に並ぶので同じ偏りが出る。さらにプレイヤーの行が複数のリージョンに散るため、全件読みはインデックスを引いてから行を1件ずつ取りに行くことになり、複合主キーのテーブルより遅い。

### 対処の候補

| 案 | 効果 | 代わりに起きること |
|---|---|---|
| `sys_player.id` を `AUTO_RANDOM` にする | `sys_player_id` が先頭のすべての主キー・インデックスで偏りが消える。最も根本的 | IDが大きく作成順でない値になる。IDの大小を作成順として使っている箇所の見直しが要る。プレイヤーに見せるIDは `my_id` なので画面には影響しない |
| `SPLIT TABLE` でリージョンを事前に分ける | 立ち上がりの集中を和らげる | 一時しのぎで、運用で面倒を見続ける |
| 主キーを `NONCLUSTERED` にして `SHARD_ROW_ID_BITS` を付ける | 行そのものは分散する | 主キーのインデックスは連番順のままで偏りが残り、読み取りも遅くなる |

- 現状はどれも入れていない。TiDB モードを本番で使うと決めたら、まず `sys_player.id` の採番を見直す
- 新しい trx テーブルは、プレイヤーの全行を読む用途なら `id` を足さず `sys_player_id` を先頭にした複合主キーにするほうが TiDB でも読み取りが速い（5節の「複合主キーで一意になる trx テーブルは `id` を持たなくてよい」）
- sys の新しいテーブルは書き込み頻度で判断する。プレイヤーごとにたまにしか書かないもの（引き継ぎコードや設定など）は連番の `id` のままでよい

