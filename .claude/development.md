# 開発環境・テスト・静的解析

ローカル環境を立ち上げるとき、テストや静的解析を回すとき、環境まわりで詰まったときに読む。
DB 接続・マイグレーションの置き場所は [database.md](./database.md)、運営ツール固有の話は [tool.md](./tool.md)。

## コンテナとポート

定義は `docker-compose.yml`。

| コンテナ | ホスト側ポート | 役割 |
|---|---|---|
| api-php | - | API（PHP 8.4）。`./api` を `/var/www/html`、`./packages` を `/var/www/packages` にマウント |
| api-web | 8090 | API の nginx |
| tool-php | - | 運営ツール（PHP） |
| tool-web | 8091 | 運営ツールの nginx |
| db-admin | 63060 | `{APP_NAME}-{APP_ENV}-admin` |
| db-tool | 63061 | `{APP_NAME}-{APP_ENV}-tool` |
| db-system | 63062 | `-system` と `nexus-testing-system` |
| db-master | 63063 | `-master` と `nexus-testing-master` |
| db-shard1〜3 | 63161〜63163 | `-shard` と `nexus-testing-shard`（DB名は全シャード共通） |
| redis | 63790 | キャッシュ（`CACHE_STORE=redis`）。冪等キーやシャード割当のキャッシュもここに入る |

- MySQL の root パスワードは `MYSQL_ROOT_PASSWORD`（既定 `root`）。DB はコンテナ初回起動時に `docker/mysql/init/` のスクリプトが `DB_NAMES` から作る
- 環境変数はリポジトリ直下の `.env` 1つを api と tool が共有する（`.env.example` から作る）。コンテナには読み取り専用でマウントされる
- シャード数は `DB_SHARD_COUNT`（`.env.example` は 3）。テストは `phpunit.xml` で 2 に固定している

## 初回セットアップ

`./command/setup.sh`（`make install`）は現状使えない（「よくある詰まりどころ」参照）。手で次を実行する。

```bash
cp .env.example .env          # APP_KEY を設定する（例: base64:$(openssl rand -base64 32)）
make up
docker compose exec -T api-php composer install
docker compose exec -T tool-php composer install
make migrate
docker compose exec -T tool-php php artisan migrate --path=database/migrations/adm --database=admin --force
docker compose exec -T tool-php php artisan migrate --path=database/migrations/tol --database=tool --force
make seed
docker compose exec -T tool-php php artisan db:seed --class=AdminAccountSeeder --force
```

`.env` はコンテナを起動する前に書き終えておく（後述）。

## make コマンド

`Makefile` を参照。主なもの:

| コマンド | 内容 |
|---|---|
| `make up` / `make down` | 起動（`--build` 付き）/ 停止 |
| `make check` | CI と同じ検査（`pint-test` → `phpstan` → `test` → `tool-check`）。PR 前に回す |
| `make test` | api の全テスト（Unit / Feature / Architecture / Packages） |
| `make check-quiet` / `make test-quiet` | `check` / `test` と同じ検査。全文は `.make-logs/*.log` に書き、画面には成功の1行か、失敗の詳細と集計だけを出す。エージェントはこちらを使う |
| `make test-unit` / `make test-feature` | そのスイートだけ |
| `make test-fresh` | `migrate-fresh` と `seed` の後に全テスト |
| `make tool-check` | tool の pint --test・phpstan・phpunit |
| `make pint` / `make pint-test` / `make pint-dirty` | 整形 / 検証のみ / 変更ファイルだけ整形（api と packages） |
| `make phpstan` | api + packages の静的解析 |
| `make migrate` / `make migrate-fresh` | sys・mst・trx・log のマイグレーション / 作り直し（tool のDBは含まない） |
| `make seed` | api のシーダー |
| `make coverage` / `make coverage-html` | カバレッジ（テキスト / `api/storage/coverage/index.html`） |
| `make shell` / `make tinker` / `make logs` | api-php のシェル / tinker / ログ |
| `make clean` | ボリュームごと削除し、`api/vendor` も消す |

`./command/sail` は Laravel Sail のラッパー。対象は既定で api-php、`--tool` / `--api` / `--service=名前` か `SAIL_SERVICE` で切り替える。`./command/sail migrate` はエラーで `make migrate` を案内する。

## テストの実行

- api 側の `api/phpunit.xml` に4スイートがある: `Unit`, `Feature`, `Architecture`（規約違反を検出するテスト）, `Packages`（`../packages/*/tests`）。パッケージのテストも api-php から回す
- 部分実行:

```bash
docker compose exec -T api-php php artisan test --filter=FriendFlowTest
docker compose exec -T api-php php artisan test --testsuite=Architecture
./command/sail api test --filter=FriendFlowTest     # 引数はそのまま phpunit に渡る
./command/sail tool test                             # tool の phpunit
```

- テストは `nexus-testing-*` の DB を使う。マイグレーションはテスト自身が流す（`api/tests/RefreshMultipleDatabases.php`）。マイグレーションファイルの指紋が変わったときだけ `migrate:fresh` し、指紋は `api/storage/framework/testing/.migrated` に保存される
- テストの時刻は `ClockUtility::setNow()` で固定し、`Tests\TestCase::setUp()` が毎回リセットする。日跨ぎの境界は `phpunit.xml` で `DAY_START_TIME=00:00:00` に固定している

## カバレッジ

- ドライバは pcov。`docker/api-php/Dockerfile` で `pcov.directory=/var/www` を設定済みで、オプションは要らない。Xdebug は入れていない
- 計測対象は `api/phpunit.xml` の `<source><include>`。パッケージを追加したらここにも足す。足さないとそのパッケージは計測されない
- `--coverage-text` の一覧には一度も読み込まれなかったファイルが出てこない。一覧に無い＝0%と読む。取りこぼしはソースのファイル一覧とレポートを突き合わせて探す

```bash
docker compose exec -T api-php ./vendor/bin/phpunit --testsuite=Packages --coverage-text
docker compose exec -T api-php ./vendor/bin/phpunit --coverage-text=/tmp/cov.txt > /dev/null
docker compose exec -T api-php grep 'App.Adapters' /tmp/cov.txt
```

- CI は `php artisan test --coverage --min=85`。下限は実測のすぐ下に置く

## 静的解析とフォーマット

- PHPStan は level 6（`api/phpstan.neon`）。対象は `app` と各パッケージの `src`。パッケージを追加したら `paths` にも足す。独自ルール `App\PHPStan\Rules\NoDirectEloquentSaveInServiceRule`（`api/phpstan/Rules/`）が有効
- Pint の設定は `api/pint.json`（preset laravel）。packages にも同じ設定を当てる
- tool は別アプリで、`tool/phpstan.neon`（level 6）と tool の Pint を `make tool-check` で回す
- CI（`.github/workflows/ci.yml`）は3ジョブ: 静的解析・フォーマット / tool / テスト（api + packages）

## よくある詰まりどころ

- **引数なしの `php artisan migrate` を打った**: 既定接続は sqlite（`.env` の `DB_CONNECTION=sqlite`）で、実DBには何も入らない。`make migrate` を使う
- **マイグレーションを書き換えたのに反映されない**: リリース前は既存のマイグレーションファイルを直接編集する運用で、`make migrate` は適用済みとして飛ばす。`make migrate-fresh` → `make seed` で作り直す（データは消える）。テストDBは指紋の変化で自動的に作り直される
- **`No code coverage driver available`**: イメージが Dockerfile より古い。`make up`（`--build` 付き）で作り直す。コンテナ内で `pecl install` してもイメージには残らない
- **`.env` を書き換えたのに反映されない**: `.env` は単一ファイルの bind マウントで、起動時のファイル実体に固定される。ファイルを置き換える保存方法だとコンテナは古い内容を読み続けるので、`make down` → `make up`。`config:cache` 済みなら `config:clear` も要る
- **tool のテストでローカルの管理者アカウントが消えた**: `tool/phpunit.xml` が `DB_ADMIN_DATABASE=nexus-local-admin` を指定し、テストが `admin` 接続に `migrate:fresh` を流す。`make tool-check` の後は `AdminAccountSeeder` を流し直す（初回セットアップの最後の行）
- **`./command/setup.sh` が途中で止まる**: 旧コンテナ名（db-sys, db-trx1 など）を前提にしており、コンテナを `down --volumes` で消した後の存在確認で失敗する。使わずに「初回セットアップ」の手順で進める。`api/tests/setup-test-db.sh` も同じく旧構成のままで使えない
