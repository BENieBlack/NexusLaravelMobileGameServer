# 運営ツール（tool/）

運営ツールの画面・DB・マスターデータのデプロイに手を入れるときに読む。
コンテナ・テストの回し方は [development.md](./development.md)、マスターデータがクライアントに届くまでの流れは [api.md](./api.md)。

## 構成

- `tool/` は api とは別の Laravel アプリで、composer の依存も別。api のクラスや packages の Repository は使わず、`DB::connection()` で各DBを直接読み書きする
- URL は http://localhost:8091（tool-web / tool-php）。ルートは `tool/routes/web.php` だけ
- 画面は Inertia + Vue（`resources/js/Pages/`）と Blade（`resources/views/`。管理者管理とプレイヤー画面）が混在している
- ログインはセッション認証で、ユーザーモデルは `App\Models\Adm\AdmAccount`（`config/auth.php`）。`AdminAccountSeeder` が `admin@example.com` / `admin` を作る

## DB 接続

`tool/config/database.php`。

| 接続名 | ホスト | 用途 |
|---|---|---|
| `admin` | db-admin（`-admin`） | `adm_*`。既定の接続 |
| `tool` | db-tool（`-tool`） | `tol_*` |
| `sys` | db-system | `sys_player` の参照、`sys_deploy*` への登録 |
| `mst` | db-master | マスターデータのインポート先・エクスポート元 |
| `trx` / `trx2` / `trx3` | db-shard1〜3 | プレイヤーのログ参照、ダッシュボードの集計 |

- 既定接続は `TOOL_DB_CONNECTION`（既定 `admin`）。共有の `.env` にある `DB_CONNECTION=sqlite` は読まない
- シャード1の接続名は api 側の `trx1` と違い `trx`
- Eloquent モデルは `app/Models/Adm/` の4つ（AdmAccount, AdmRole, AdmPage, AdmPermission）だけ。tol と他DBは `DB::connection()` で扱う

## マイグレーション

- `tool/database/migrations/adm/` と `tol/`。`Schema::connection('admin')` / `Schema::connection('tool')` で接続を固定する
- `make migrate` には含まれない。次で流す

```bash
docker compose exec -T tool-php php artisan migrate --path=database/migrations/adm --database=admin --force
docker compose exec -T tool-php php artisan migrate --path=database/migrations/tol --database=tool --force
```

| DB | テーブル |
|---|---|
| admin | `adm_account`, `adm_role`, `adm_page`, `adm_permission`, `adm_account_role`, `sessions`, Laravel 標準（cache, cache_locks, jobs, job_batches, failed_jobs） |
| tool | `tol_master_status`, `tol_asset_status`, `tol_banner`, `tol_cache_control`, `tol_maintenance`, `tol_notice`, `tol_dashboard_retention`, `tol_dashboard_access_cache`, Laravel 標準 |

コードから使っている `tol_*` は `tol_dashboard_retention` と `tol_dashboard_access_cache` だけ。他の `tol_*` はテーブルだけあり、画面も処理も無い。

## 主な機能

### マスターデータのインポートとデプロイ（`/master-import`）

`MasterImportController`。

1. Google スプレッドシートの一覧・シート・プレビューを取る（`GoogleSpreadsheetService`。`TOL_GOOGLE_SPREADSHEET_DIR` と `TOL_GOOGLE_SERVICE_ACCOUNT_JSON` か `TOL_GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT` が必要）。`.言語コード` の付いた列は `<親>__l10n` の行に展開する
2. インポート（`POST /master-import/execute`）: `MasterImportService` が対象の mst テーブルを TRUNCATE し、500行ずつ INSERT する
3. エクスポート（`POST /master-import/export`）:
   - `MasterDataExporter` が mst の `mst_*` テーブルをグループ（親テーブルと `__l10n`）ごとの SQLite に書き出し、`tool/public/masterdata/{マニフェストハッシュ}/{グループ}_{ハッシュ}.sqlite` に置く
   - `MasterDeployService::register()` が `sys_deploy_master` と `sys_deploy_master_table` に登録し、ダミーの `sys_deploy_asset` を作る。既存の `sys_deploy` を全て `is_active = false` にし、新しい `sys_deploy` を `start_at` = 現在時刻・`is_active = true` で入れる（即時公開）
   - 同じハッシュが登録済みなら新しく作らず、既存の deploy_key を返す
   - deploy_key は `YYYYMMDD × 100 + 当日の連番`

### ダッシュボード（`/dashboard`）

- 売上は `trx` 接続（シャード1）から集計する
- アクセス数と継続率は `tol_dashboard_access_cache` / `tol_dashboard_retention` にキャッシュし、無ければジョブ（`CalculateAccessCacheJob`, `CalculateRetentionJob`）を database キューに積む。キューワーカーのコンテナは無いので、ローカルで集計させるには tool-php で `php artisan queue:work` を動かす

### プレイヤー（`/player/list`, `/player/index`, `/player/log`）

- `list`: `sys_player` を `my_id` / `uuid` の部分一致で検索（最大50件）
- `index`: `sys_player` の基本情報だけを表示する
- `log`: 全シャードの `log_*` テーブルからプレイヤーの履歴を最新100件ずつ表示する。表示するテーブルは `PlayerHistoryController::LOG_TABLES`

### 管理者・ロール・ページ（`/adm/*`）

`AdmManagementController`。アカウント、ロール、ページの一覧・作成・編集。

### ツールに無いもの

メンテナンスの開始・終了は tool ではなく API の `POST /api/admin/maintenance/start|end`（`ADMIN_TOKEN` で認証）。お知らせ・バナーの画面は無い。

## 権限

- `adm_role`（ID は文字列）と `adm_page`（ID は文字列。例 `master.items`）を `adm_permission` で結ぶ。`adm_permission` は**拒否リスト**で、ロールに対して見せないページを登録する（`AdmRole::deniedPages()`）
- アカウントとロールは `adm_account_role` で多対多
- 判定は `AdmAccount::canAccessPage($pageId)`。スーパー管理者（name が `admin` かつ email が `admin@example.com`）は常に可、それ以外は所属ロールのどれかが拒否していれば不可
- 現状 `canAccessPage()` はルートやミドルウェアから呼ばれておらず、ログイン済みなら全画面に入れる
