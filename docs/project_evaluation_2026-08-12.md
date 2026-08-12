# Nexus プロジェクト評価レポート

| 項目 | 内容 |
| --- | --- |
| 評価日 | 2026-08-12 |
| 対象コミット | `8b60627` (main) |
| 評価方法 | テストスイート・静的解析・コードフォーマッタの実行、および起動中コンテナへの実HTTPリクエストによる動作確認 |

---

## 総評

設計思想とドキュメントは本格的だが、**「テストは緑だがアプリは動かない」状態**にある。

テスト323件が全通過する一方、起動中のAPIサーバはゲーム系エンドポイントがほぼ全て500を返す。テストが緑なのは `TestCase` がストレージ実装を差し替えているためで、テストスイートは現状「アプリが動作すること」を保証していない。

```
Tests:    1 skipped, 323 passed (1233 assertions)
Duration: 33.33s
```

```
500  GET /api/maintenance/status
500  GET /api/guild/list
299  GET /api/auth/version   ← maintenance ミドルウェア群の外なので生存
500  GET http://localhost:8091/   ← Tool
```

---

## 🔴 最優先：動作していない

### 1. APIのゲーム系エンドポイントが全滅

**症状**：`maintenance` ミドルウェア配下（＝ほぼ全ゲームAPI）が起動時点で `RuntimeException` を投げる。

```
AWS SDK is required for DynamoDB storage.
Install it with: composer require aws/aws-sdk-php
at packages/nexus-maintenance/src/MaintenanceServiceProvider.php:66
```

**原因**：

- `api/config/maintenance.php:17` — 既定値が `dynamodb`
- ルート `.env` に `MAINTENANCE_DRIVER` の指定なし
- `aws/aws-sdk-php` 未インストール
- ドライバ実装は `dynamodb` / `tablestore` のみで、**ローカル・DB用の実装が存在しない**（`SysMaintenance` モデルとテーブルは存在するが未接続）

**テストが緑な理由**：`api/tests/TestCase.php:24` が `MaintenanceStorageInterface` に `InMemoryMaintenanceStorage` をバインドしている。実行時のバインド経路はテストでは一度も通らない。

**対応**：ローカル/DBドライバの実装。`SysMaintenance` モデルが既にあるため、DB実装を追加して `MAINTENANCE_DRIVER=database` を `.env` に設定するのが最短。

---

### 2. Toolプロジェクトが完全に不動

**症状**：

```
SQLSTATE[HY000] [2002] Connection refused
(Connection: admin, Host: 127.0.0.1, Port: 3306, Database: nexus-local-adm)
```

**原因**：

- `tool/config/database.php:37` — `admin` 接続が `env('DB_HOST', '127.0.0.1')` を読む
- ルート `.env` が定義しているのは `DB_ADMIN_HOST=db-adm`
- 結果、フォールバックの `127.0.0.1` に接続して失敗

**併発する問題**：`tool/bootstrap/app.php:23` が `useEnvironmentPath('/var/www')` を指定しているため、**`tool/.env` は一切読まれない死にファイル**。設定が2箇所に散在して見え、原因追跡を困難にしている。

**対応**：`tool/config/database.php` の各接続を `DB_ADMIN_HOST` / `DB_TOOL_HOST` を読むよう修正し、`tool/.env` を削除する。

**補足**：Tool は実装が8ファイルのみで実質未着手。

---

## 🟠 セキュリティ

### 3. `POST /guild/create` の認証バイパス

**内容**：このルートだけ `auth.token` ミドルウェアの外にある。

```php
// api/routes/api.php:56
// Guild creation endpoint (temporary public access for testing)
Route::post('/guild/create', [GuildController::class, 'create']);
```

そして `api/app/Http/Requests/Guild/CreateRequest.php` の認証情報取得は**クライアント入力をそのまま読んでいる**：

```php
public function getAuthenticatedPlayerId(): ?int
{
    $playerId = $this->input('authenticated_player_id');
    return $playerId ? (int) $playerId : null;
}
```

**影響**：任意のプレイヤーIDを名乗ってギルド作成が可能。現在は問題1（maintenance 500）で偶然到達不能になっているだけで、**問題1を修正した瞬間に露出する**。

**設計上の懸念**：`api/app/Http/Requests/_BaseRequest.php:73` も同様に `input()` ベース。保護ルートでは `packages/nexus-core-security/src/Middleware/VerifyAccessToken.php:64` の `$request->merge()` が入力を上書きするため実害はないが、**「認証情報をリクエスト入力とみなす」設計自体が脆い**。ミドルウェアの適用漏れが即座に認証バイパスになる。

なお `api/app/Http/Requests/Unit/LevelUpRequest.php:81` は `$this->attributes->get('sys_player_id')` を使っており、実装が割れている。

**対応**：

1. `/guild/create` を `auth.token` 配下に戻す（`idempotency` も併せて適用）
2. 認証情報の取得を `$request->attributes` に統一し、`input()` 経由を廃止する

---

### 4. `sign_in` / `refresh_token` にレート制限なし

スロットリングは `sign_up` のみ（`throttle.signup`）。グローバルthrottleも未設定のため、ログインとトークン更新が無制限に叩ける。クレデンシャルスタッフィングの経路になる。

---

## 🟡 パッケージ構成の負債

### 5. `nexus-core` 統合（コミット `32aa8a9`）が未完了

| パッケージ | namespace | コード内参照数 | 状態 |
| --- | --- | ---: | --- |
| nexus-core | `Nexus\Core\` | 138 | 現役 |
| nexus-core-persistence | `NexusPersistence\` | **0** | 重複コードのまま残存・**ServiceProviderは起動中** |
| nexus-core-utilities | `NexusUtilities\` | **0** | 完全な死骸（composer repositories 未登録） |

**重複しているファイル**（namespace 違いで実体は同一）：

- `Utilities/RedisUtility.php` — 407行
- `Repositories/Trx/_BaseTrxRepository.php` — 384行
- `Support/CustomCollection.php` — 329行
- その他 Models / Repositories の基底クラス群

**地雷**：`packages/nexus-core-utilities/composer.json` の `name` が **`nexus/core`** で `nexus-core` と衝突している。現在は `api/composer.json` の repositories に未登録のため無害だが、追加した瞬間に `nexus/core` の解決先が壊れる。

**persistence が生き残っている理由**：sharding migration が1本だけ存在するため。

```
packages/nexus-core-persistence/database/migrations/sys/2026_01_01_000005_create_sharding_system_tables.php
```

`PersistenceServiceProvider::boot()` はこのマイグレーション登録以外何もしていない。

**対応**：上記マイグレーション1本を `nexus-core` に移設し、`nexus-core-persistence` と `nexus-core-utilities` を削除する。`api/composer.json` の repositories と require からも `nexus/core-persistence` を除く。

---

### 6. 実体のないシンボリックリンク

```
api/vendor/nexus/game-common -> ../../../packages/nexus-game-common   (リンク先が存在しない)
```

`packages/nexus-game-common` は存在せず、`vendor/composer/installed.json` にも記載がない。過去のパッケージ削除時の残骸。

---

### 7. namespace 規約の逸脱

`nexus-wallet` のみ namespace が `LaravelWallet\`。他は全て `Nexus*` 系（`NexusVip\`、`NexusGuild\` 等）。サードパーティスケルトンからの流用時の残骸と見られる。

---

## 🟡 品質ゲートが機能していない

### 8. PHPStan level 6 で626エラー

エラー種別の内訳（上位）：

| 件数 | identifier |
| ---: | --- |
| 134 | `missingType.iterableValue` |
| 129 | `missingType.generics` |
| 100 | `property.notFound` |
| 50 | `method.notFound` |
| 32 | `return.type` |
| 31 | `class.notFound` |
| 28 | `property.phpDocType` |

エラーの多いファイル（上位）：

| 件数 | ファイル |
| ---: | --- |
| 18 | `Models/Sys/SysShardingNode.php` |
| 17 | `Models/Sys/SysMaintenance.php` |
| 14 | `Domain/InAppPurchase/Services/InAppPurchasePackService.php` |
| 14 | `Repositories/Sys/SysFriendApplyRepository.php` |
| 13 | `Models/Sys/SysDeploy.php` |

**問題点**：

- baseline が存在しないため `make check` は**恒久的に赤**＝誰も回していない
- 解析対象が `api/app` のみ。**ロジックの大半がある `packages/` は静的解析ゼロ**

---

### 9. パッケージテストが1件も実行されない

- 19パッケージに `phpunit.xml` が存在（テストファイル約66件）
- `php artisan test` の testsuite は `api/tests` のみ（`api/phpunit.xml`）
- パッケージ単体でも `vendor/autoload.php` が無く起動不可
- `packages/*/composer.lock` と `packages/*/vendor/` は `.gitignore` 済み

パッケージ側に書かれたテストは、書かれた時点以降一度も実行されていない可能性が高い。

---

### 10. コードフォーマット未適用

`./vendor/bin/pint --test` で **56ファイル**が違反。主に `ordered_imports`、`php_unit_method_casing`、`no_trailing_whitespace`。

---

### 11. CIが存在しない

`.github/` ディレクトリなし。上記8〜10の品質ゲートを強制する仕組みが一切ない。

---

## 🟢 ドキュメント・リポジトリ衛生

### 12. `.claude/` が一度もコミットされていない

```
$ git log --oneline --all -- .claude
(出力なし)
```

- ドキュメント量：**39ファイル / 16,783行**
- READMEが全面的に `.claude/database.md`、`.claude/api.md`、`.claude/development.md`、`.claude/tool.md` を参照している
- **clone した人には存在しない**ため、READMEのリンクが全て切れる
- そのまま `git add` できない理由：`.claude/node_modules` が **6.5MB** 混入している

### 13. READMEのリンク切れ

`DEVELOPMENT_RULES.md` が存在しない（README冒頭のドキュメント一覧で参照）。他のリンクは全て解決する。

### 14. Makefileが実態と乖離

- `make migrate` — `--database=trx` / `--database=log` は現在のシャーディング構成に存在しない接続名。READMEは `trx:migrate` / `pitr:migrate` を案内しており矛盾
- `make test-unit` — ハードコードされたテスト名フィルタ（`ErrorResponse|BaseModelDateCast|...`）
- `make check` — 問題8のため常に失敗

### 15. 空ディレクトリが21個

`api/app` 配下：`Contracts/`、`DTOs/`、`Infrastructure/`、`Services/`、`Models/Tol/`、`Http/Controllers/Guild/`、および `Domain/` 配下15個（`Party/`、`Trade/`、`Mission/`、`PlayerVsPlayer/` 等の UseCases / Services）。

### 16. `.opencode/` が `.claude/` のほぼ複製

両方とも untracked。同一内容のドキュメントが2箇所に存在している。

---

## 良い点

- **テスト323件通過・1233アサーション**。実装は着実に前進している
- **レイヤリングと命名規約が一貫**しており、規約を機械的に守る仕組みがある
  - アーキテクチャテスト（`api/tests/Architecture/ServiceLayerTest.php`）— Service層での直接 `save()`/`update()`/`delete()` 呼び出しを検出
  - カスタムPHPStanルール（`App\PHPStan\Rules\NoDirectEloquentSaveInServiceRule`）
  - この発想自体は良く、対象範囲を広げる価値がある
- **trx/log シャーディング＋PITR設計が本格的**で、ドキュメントも詳細（`docs/sharding_migration_system.md`、`docs/trx_point_in_time_recovery.md` 等）
- **進行中の DTO→ValueObject リファクタは方向性が正しい**
  - `CurrencyBalance` が `totalAmount` を内訳から算出するよう変更され、不整合な値を持てなくなった
  - `zero()` ファクトリの導入
  - 同様の整理をする価値のあるDTOがパッケージ内に18個残っている

---

## パッケージ規模（参考）

| パッケージ | src ファイル数 | src 行数 | テストファイル数 |
| --- | ---: | ---: | ---: |
| nexus-core | 26 | 3,179 | 0 |
| nexus-core-persistence | 22 | 2,286 | 3 |
| nexus-core-billing | 25 | 2,002 | 9 |
| nexus-resource-delivery | 19 | 1,711 | 3 |
| nexus-vip | 19 | 1,483 | 5 |
| nexus-gacha | 18 | 1,132 | 5 |
| nexus-resource | 7 | 1,110 | 3 |
| nexus-pitr | 12 | 1,028 | 3 |
| nexus-guild | 10 | 1,012 | 4 |
| nexus-core-unit-of-work | 8 | 896 | 2 |
| nexus-core-utilities | 4 | 891 | 2 |
| nexus-wallet | 11 | 818 | 5 |
| nexus-maintenance | 6 | 705 | 2 |
| nexus-core-auth | 10 | 694 | 1 |
| nexus-mailbox | 9 | 677 | 2 |
| nexus-core-security | 8 | 666 | 4 |
| nexus-login | 6 | 564 | 1 |
| nexus-friend | 5 | 484 | 2 |
| nexus-player | 8 | 479 | 2 |
| nexus-stamina | 6 | 440 | 3 |
| nexus-level | 1 | 187 | 0 |
| nexus-version | 4 | 118 | 1 |

`api/app`：319ファイル / 29,315行。`tool/app`：8ファイル。

---

## 推奨する着手順

| 順 | 対応 | 該当 | 目安 |
| ---: | --- | --- | --- |
| 1 | maintenance のローカル/DBドライバを実装 | 問題1 | 0.5日 |
| 2 | `/guild/create` を `auth.token` 配下に戻す＋認証情報取得を `attributes` に統一 | 問題3 | 0.5日 |
| 3 | tool の DB設定修正（`DB_ADMIN_HOST` を読む）と `tool/.env` 削除 | 問題2 | 0.25日 |
| 4 | PHPStan baseline を生成して `make check` を緑にし、`packages/` を解析対象に追加 | 問題8 | 1日 |
| 5 | CI（GitHub Actions）を1本通す（test + phpstan + pint） | 問題11 | 1日 |
| 6 | `nexus-core-persistence` / `nexus-core-utilities` を削除（migration 1本を nexus-core へ移設） | 問題5 | 0.5日 |
| 7 | `.claude/` を node_modules 除外してコミット | 問題12 | 0.25日 |
| 8 | `sign_in` / `refresh_token` にレート制限を追加 | 問題4 | 0.25日 |

**1〜3は合計1.25日程度で終わる割にインパクトが大きい**（開発環境でアプリが触れるようになる＋認証バイパスの解消）ため、先に片付けることを推奨する。

4〜5を入れておかないと、以降の修正で同じ種類の負債が再発する。
