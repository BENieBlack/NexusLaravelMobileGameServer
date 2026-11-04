# NexusLaravelMobileGameServer

モバイルゲームのバックエンド。`api/`（ゲームAPI, Laravel）、`tool/`（運営ツール, 別のLaravelアプリ）、`packages/nexus-*`（機能ごとのパッケージ）で構成する。

このファイルは Claude Code と opencode の両方が毎回読む。ここには毎回守ることだけを書き、詳細は下の「参照資料」に置く。

## 進め方

- 応答・コメント・コミットメッセージ・ドキュメントは日本語。識別子は英語。
- コミットメッセージと PR に AI の共同作者表記（`Co-Authored-By: Claude ...` などの trailer、`Generated with Claude Code` などの署名、セッションのリンク）を付けない。GitHub に Claude のアイコンが表示されるため。Claude Code は `.claude/settings.json` の `attribution` で無効にしてある。
- main へ直接コミットしない。作業ブランチ（`feature/` `fix/` `refactor/` `chore/` + 英語ケバブケース）を切って PR でマージする。PR は `.github/pull_request_template.md` の各欄に変更内容を記載する（書き方は `.claude/development.md` の「ブランチと PR」）。
- 仕上げに `make check`（CIと同じ: 依存の脆弱性 → pint → PHPStan → 全テスト → tool の検査）を通す。エージェントは結果だけを表示する `make check-quiet` / `make test-quiet` を使い、全文は `.make-logs/` を必要な部分だけ読む。

## コマンド（Docker上で動かす。ホストで php / artisan を直接叩かない）

| 用途 | コマンド |
|---|---|
| 起動 / 停止 | `make up` / `make down`（API :8090、Tool :8091） |
| CIと同じ検査 | `make check`（結果だけ: `make check-quiet`） |
| テスト | `make test`（api + packages。結果だけ: `make test-quiet`）、`make test-unit`、`make test-feature`、`make tool-check`（tool） |
| 一部だけ | `docker compose exec -T api-php php artisan test --filter=Xxx` |
| 整形 / 静的解析 | `make pint`（api と packages）、`make phpstan`（level 6） |
| マイグレーション | `make migrate`、`make migrate-fresh && make seed` |

引数なしの `php artisan migrate` は使わない（既定接続が sqlite で、どのDBにも流れない）。詳細は `.claude/development.md`。

## DB構成

| 接続 | 中身 |
|---|---|
| `mst` | マスターデータ（読み取り専用）。`mst_*` |
| `sys` | 全プレイヤー共通。プレイヤーID採番、シャード割当、デプロイ、フレンド、ギルド、チャット。`sys_*` |
| `trx1`〜`trxN` | プレイヤーごとのデータ（シャード。N は `DB_SHARD_COUNT`）。`trx_*` と `log_*` が同じDBに入る |
| `admin` / `tool` | 運営ツール用（tool/ から使う）。`adm_*` / `tol_*` |

- ログ専用のDB・接続は無い。`log_*` はプレイヤーと同じシャードに書く。
- trx / log のモデルに `$connection` を書かない。`ApiSession` が解決したシャードに自動で振り分けられる。
- マイグレーションは主に `packages/*/database/migrations/{sys,mst,trx,log}/` に置き、`Schema::create`（接続名なし）で書く。共有の検証環境を作るまでは既存ファイルを書き換えてよい（作る時点で凍結する）。詳細は `.claude/database.md`。

## 毎回守る規約

**レイヤー**
- Controller は `_BaseController` を継承し、`$this->execute(fn () => XxxResponse::fromResult($useCase->exec(...)))` の形にする。Request からは値を取り出して渡し、Request オブジェクトを UseCase に渡さない。
- UseCase は `App\Domain\{Context}\UseCases\*UseCase`。`_BaseUseCase` を継承し、入口は `exec()`。Response は返さず、DTO・モデル・`DataTransferObjects/*Result` を返す。
- 他のコンテキストは `App\Domain\{Other}\Contracts\` のインターフェースだけを使う。`app/Domain` から `App\Http` を参照しない。
- 起きたことを他のコンテキストに伝えるときはドメインイベントを使う。`Dispatcher` をコンストラクタで受け取って発行し、受け手は `app/Domain/{Context}/Listeners/` に置く。
- `packages/` から `App\` を参照しない。パッケージは Contract を定義し、api 側が `*RepositoryAdapter` で実装する。

**データ**
- 書き込みは `executeWithTransaction()` の中で、Repository の `setModel()` / `softDeleteModel()` / `hardDeleteModel()` を使う。Model の `save()` / `update()` / `delete()` / `create()` は例外になる。
- Repository の読み取りメソッドは `select*`。複数件は `CustomCollection` で返す。
- モデルは実在するカラムだけを `@property` / `$fillable` / `$casts` に書く。リレーションを返す getter や、存在しない列の定義を作らない。
- 多言語テーブルは `<親テーブル>__l10n`（主キーは `<親>_id` と `language`）。i18n は使わない。
- テーブルは接頭辞付き・単数形・スネークケース。DBに入る文字列値もスネークケース（`app_store` など）。外部キー制約は張らない。

**時刻・エラー**
- 時刻の取得とパースは `Nexus\Core\Utilities\ClockUtility`（リクエスト時刻は `ApiSession::getNow()`）。`Carbon::now()` / `parse()` / `now()` を直接呼ばない。比較は `isPast` / `isFuture` / `isWithin` など（`isAfter` / `isBefore` は無い）。
- 日時は `'Y-m-d H:i:s'` の文字列で扱う。
- 業務エラーは `GameException` 系（`MasterDataException` など）を `GameErrorCode` 付きで投げる。レスポンスは HTTP 299 の `{error_code, message}`。

**その他**
- 呼び出し元の無い関数も削除しない。テストで動作を固定する。
- API のレスポンスキーはスネークケース。配列は `_list` サフィックス。

規約の多くは `api/tests/Architecture/` と PHPStan のルールが検査している。テストが落ちたら、テストを緩めずにコードを規約に合わせる。

## 参照資料（必要になったときに Read で読む。全部を先に読まない）

| ファイル | 読むとき |
|---|---|
| `.claude/architecture.md` | レイヤーやモジュールをまたぐ変更、新しいコンテキストやパッケージを作るとき、トランザクション・例外の仕組みを知りたいとき |
| `.claude/coding-standards.md` | Controller / Request / UseCase / Service / Repository / Model / Response / DTO / テストを書くとき |
| `.claude/naming-conventions.md` | クラス・変数・メソッドの名前を決めるとき |
| `.claude/database.md` | テーブルやマイグレーションを追加・変更するとき、シャーディングやログテーブルを扱うとき |
| `.claude/api.md` | エンドポイントを追加・変更するとき、レスポンス形式やマスターデータ配信を扱うとき |
| `.claude/development.md` | 環境構築、テスト・カバレッジ・静的解析の詳細、PR を書くとき、環境が動かないとき |
| `.claude/tool.md` | 運営ツール（`tool/`）を触るとき |
| `.claude/skills/` | CS問い合わせの調査（cs-support）、スキルの作成（create-skill） |
