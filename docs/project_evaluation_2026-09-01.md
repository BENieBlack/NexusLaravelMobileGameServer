# Nexus プロジェクト評価レポート

| 項目 | 内容 |
| --- | --- |
| 評価日 | 2026-09-01 |
| 対象コミット | `646eaf4` (main) |
| 前回評価 | [2026-08-12](./project_evaluation_2026-08-12.md) (`8b60627`) |
| 評価方法 | テストスイート・カバレッジ・静的解析・フォーマッタの実行、起動中コンテナへの実HTTPリクエストによる動作確認、前回指摘16件の追跡 |

---

## 総評

前回の指摘16件のうち **14件が解消**。前回の総評だった
「テストは緑だがアプリは動かない」状態からは完全に抜けた。

品質ゲートの整備が特に大きい。PHPStan level 6 が **baseline 無しで0エラー**、
テストは **1826件・行カバレッジ90.0%**、CIが `pull_request` で
静的解析・フォーマット・全テストを回している。前回「誰も回していない」と
書いた `make check` は現在通る。

```
Tests:  1826 (5353 assertions), Skipped: 1     OK
PHPStan level 6 (api/app + 20パッケージ)       [OK] No errors
Lines:   89.99% (9885/10984)
Methods: 81.32% (2068/2543)
Classes: 57.48% (265/461)
```

実HTTPリクエストでもゲームAPIが応答する。

```
200  GET /api/auth/version
200  GET /api/maintenance/status
200  GET /api/guild/list
```

残る課題は性質が変わった。**「動かない」から「守備範囲の穴」へ**移っている。
最大のものは、コードの約4割を占める `packages/` がフォーマッタの対象外で、
361ファイル中178ファイルが規約違反という点。

なお前回指摘の7（namespace逸脱）は、前回評価そのものが誤っている。
本レポートも当初は検証せずに引き継いだため、Hで訂正する。

---

## 前回指摘の追跡

| # | 指摘 | 状態 | 備考 |
| ---: | --- | --- | --- |
| 1 | maintenance のゲームAPI全滅 | ✅ 解消 | `DatabaseMaintenanceStorage` 実装済み、`.env` に `MAINTENANCE_DRIVER=database` |
| 2 | Tool が不動 | 🔺 一部 | `tool/.env` は削除済み。ただし Tool は依然500（後述） |
| 3 | `/guild/create` の認証バイパス | ✅ 解消 | `auth.token` + `idempotency` 配下に移動 |
| 4 | `sign_in`/`refresh_token` にレート制限なし | ✅ 解消 | `throttle.auth:sign_in` / `throttle.auth:refresh_token` |
| 5 | `nexus-core-persistence` / `-utilities` の重複 | ✅ 解消 | 両パッケージとも削除済み |
| 6 | 実体のないシンボリックリンク | ✅ 解消 | 壊れたリンクなし |
| 7 | `nexus-wallet` の namespace 逸脱 | ⚠️ 誤指摘 | 20パッケージ全てが `NexusGuild\` 等の同じ形。逸脱していない（後述） |
| 8 | PHPStan 626エラー・解析対象が `api/app` のみ | ✅ 解消 | baseline無しで0エラー。20パッケージを解析対象に追加 |
| 9 | パッケージテストが実行されない | ✅ 解消 | `Packages` testsuite を追加。パッケージ側テスト82ファイルが実行対象 |
| 10 | pint 違反56ファイル | 🔺 一部 | `api/` は0件。**`packages/` は対象外のまま178件**（後述） |
| 11 | CIが存在しない | ✅ 解消 | `.github/workflows/ci.yml`。静的解析ジョブとテストジョブ |
| 12 | `.claude/` が未コミット | ✅ 解消 | 46ファイルが追跡下 |
| 13 | READMEのリンク切れ | ✅ 解消 | README内のリンクは全て解決する |
| 14 | Makefileが実態と乖離 | ✅ 解消 | `trx:migrate`/`pitr:migrate` を使う形に修正済み |
| 15 | 空ディレクトリ21個 | ✅ 解消 | 0個 |
| 16 | `.opencode/` が `.claude/` の複製 | ✅ 解消 | シンボリックリンク1本に集約 |

---

## 現在の規模

| 対象 | ファイル数 | 行数 |
| --- | ---: | ---: |
| `api/app` | 331 | 30,869 |
| `packages/*/src`（20パッケージ） | 231 | 22,285 |
| `tool/app` | 8 | — |
| テスト（`api/tests` + `packages/*/tests`） | 229 | — |

| 指標 | 値 |
| --- | ---: |
| コミット数 | 426 |
| マージ済みPR | 56 |
| 開発期間 | 2026-03-26 〜 2026-09-01 |
| 追跡ファイル総数 | 1,147 |
| TODO / FIXME / HACK | 0 |

---

## 🟠 残っている問題

### A. `packages/` がフォーマッタの対象外（最優先）

CIのフォーマット検証は `api` ディレクトリから引数なしで実行しているため、
**カレントディレクトリの `api/` しか見ていない**。

```yaml
# .github/workflows/ci.yml
- name: コードフォーマットの検証
  working-directory: api
  run: ./vendor/bin/pint --test
```

`packages/` にかけると **361ファイル中178ファイルが違反**する。
コード行数で見ればプロジェクトの約4割が未検証のまま放置されている。

```
⨯ packages/nexus-wallet/src/Services/WalletService.php       braces_position, single_quote
⨯ packages/nexus-wallet/src/ValueObjects/CurrencyBalance.php unary_operator_spaces
（178ファイル）
```

**注意点**：単純に `pint packages` を通すと壊れる箇所がある。
`Nexus\Core\Support\CustomCollection` は `no_superfluous_phpdoc_tags` で
`@return static` が剥がされ、PHPStanの型推論が崩れて8エラーになる。
一括適用ではなく、除外設定を用意してから段階的に入れる必要がある。

### B. ギルドの読み取り3本が無認証・レート制限なし

```php
// api/routes/api.php:58
// Guild list/detail endpoints (public access for browsing)
Route::get('/guild/list', [GuildController::class, 'list']);
Route::get('/guild/detail', [GuildController::class, 'detail']);
Route::get('/guild/member/list', [GuildController::class, 'memberList']);
```

実際に認証なしで応答する。

```
200  GET /api/guild/list         → {"guilds":[]}
200  GET /api/guild/member/list  → {"members":[]}
```

コメントのとおり意図的な公開だが、`member/list` は任意のギルドの
メンバー一覧を返す。スロットリングも掛かっていないため、
プレイヤー名の総当たり収集経路になりうる。

**判断が必要**：公開仕様として正しいなら、少なくとも
`throttle` を付けて返却項目を絞る。ゲーム内ブラウズ用途なら
`auth.token` 配下へ移す。

### C. `DB_CONNECTION=sqlite` で web ルートが500になる

```
500  GET /
```

```
Database file at path [/var/www/html/database/database.sqlite] does not exist.
(Connection: sqlite, SQL: select * from "sessions" where "id" = ?)
```

ルート `.env` が `DB_CONNECTION=sqlite` かつ `SESSION_DRIVER=database` で、
sqliteファイルが存在しない。ゲームAPI（`api` ミドルウェアグループ）は
セッションを使わないため影響しないが、web側は起動しない。
Tool の500も同じ原因。

`DB_CONNECTION=sqlite` 自体は意図的な既定値で、引数なしの
`php artisan migrate` が実DBを書き換えないようにするためのもの
（READMEに明記されている）。直すべきはセッションドライバのほう。

### D. Tool は依然として動かない

8ファイルのみで実質未着手。8091番ポートは500を返す（原因はC）。
`tool/config/database.php` は3箇所で `env('DB_HOST')` を読んでおり、
ルート `.env` が定義しているのは `DB_ADMIN_HOST` / `DB_TOOL_HOST` のため、
接続先が解決できない状態が残っている。

### E. CIのカバレッジ下限が実態と乖離

```yaml
run: php artisan test --coverage --min=55
```

実測90.0%に対して下限55%。**35ポイントぶん下げてもCIは通る**ため、
カバレッジ低下の検知機構として機能していない。85%程度まで上げるのが妥当。

### F. `make check` の守備範囲が狭い

```makefile
check: phpstan test-unit
```

pint を含まず、テストも `Unit` のみ。Feature・Architecture・Packages が
入らないため、ローカルで `make check` が通ってもCIで落ちる。

### G. クラスカバレッジ57%（行90%との乖離）

行90.0%・メソッド81.3%に対しクラス57.5%。
「全メソッドが覆われたクラス」が半分強しかない。
広く薄く当たっており、クラス単位で完結していない箇所が多い。

### H. 前回指摘7（namespace逸脱）は誤り

前回評価は `nexus-wallet` だけが `NexusWallet\` で逸脱していると
書いているが、実際は21パッケージ中20が同じ形をしている。

```
nexus-album      NexusAlbum\        nexus-guild    NexusGuild\
nexus-friend     NexusFriend\       nexus-vip      NexusVip\
nexus-wallet     NexusWallet\       …
nexus-core       Nexus\Core\        ← 唯一の別形
```

`.claude/architecture.md` も `namespace NexusWallet\Exceptions;` を
例として載せており、これが規約どおりの形。
別形なのは `nexus-core` の `Nexus\Core\` だけで、
これは共通基盤という位置づけを反映した意図的なもの。

**対応不要**。前回の指摘を検証せずに引き継いだ本レポートの誤りで、
初出時に訂正する。

### I. マイグレーションの現地編集とテストDBの追随

リリース前のため既存マイグレーションを直接編集する運用だが、
テスト用DBは `migrate`（`migrate:fresh` ではない）で追随するため、
**既存ファイルへの列追加が反映されない**。マイグレーションの指紋は
更新されるので、古いスキーマのまま「最新」と判定される。

実際に今回 `mst_item.is_wallet` を追加した際に踏んだ。
CIは毎回まっさらなDBなので顕在化せず、ローカルでのみ発生する。
`RefreshMultipleDatabases` が指紋の変化を検出したら
`migrate:fresh` に切り替えるのが素直な解。

### J. nginx のDNSキャッシュでAPIが404になる（環境）

`api-php` コンテナを再起動するとIPが変わるが、`api-web` の nginx は
`fastcgi_pass api-php:9000` を起動時に一度解決したまま保持するため、
別コンテナへリクエストを送り続ける。
本評価の途中で全APIが404を返す状態になり、`docker compose restart api-web`
で復旧した。

コードの問題ではないが、**「APIが全部404」という誤診に直結する**ため、
`resolver` の設定か、READMEのトラブルシュートに一行あると事故が減る。

### K. 軽微

- `docs/NAMING_CONVENTIONS.md` が存在しない `../CHANGELOG.md` を参照

---

## 🟢 良い点

### 品質ゲートが実際に効いている

- **PHPStan level 6 を baseline 無しで0エラー**。前回626エラーから、
  抑制ではなく型を通して解消している。解析対象も `api/app` のみから
  20パッケージへ拡大
- **CIが `pull_request` で回る**。静的解析ジョブとテストジョブが分離され、
  DB不要な検査が先に落ちる構成
- **アーキテクチャテスト10種**が規約を機械的に守っている。
  レイヤ違反・命名・PSR-4整合に加え、
  `NoFullTableSelectOnSysTest`（Sysテーブルの全件SELECT禁止）や
  `RepositoryKeyDeclarationTest`（キー宣言の一元化）のように、
  **この設計固有の制約**をテストにしている点が特に良い
- `AgentDocsInSyncTest` でドキュメントとコードの乖離まで検査している

### 開発フローが一貫している

426コミット・56PR が全てPR経由でマージされている。
コミットメッセージは日本語で、「何を直したか」ではなく
**「なぜ壊れていたか」**が書かれているものが多い。

### 設計上の判断が言語化されている

Sysリポジトリの自己スコープモデル（`queryOrMemory()` は自分に関係する行、
それ以外は `selectWithoutCache()`、全件取得は `selectAll()` で例外）のように、
安全側の制約をコードとテストの両方で表明している。
`$uniqueKeys` を廃してモデルの主キーへ一本化した整理も、
二重管理の芽を摘む方向で正しい。

### テストが挙動の説明になっている

テストメソッド名が日本語で、
`既にwalletに残高があれば足し込む`、`論理削除済みの行は移さない` のように
**仕様として読める**。コメントも「なぜこの検証が要るか」を書いている。

---

## 推奨する着手順

| 順 | 対応 | 該当 | 目安 |
| ---: | --- | --- | --- |
| 1 | ギルド読み取り3本の公開可否を決める（throttle付与 or 認証配下へ） | B | 0.25日 |
| 2 | CIのカバレッジ下限を85%へ引き上げ | E | 0.1日 |
| 3 | `make check` に pint と全テストスイートを追加 | F | 0.1日 |
| 4 | `packages/` を pint 対象に追加（除外設定を先に用意し、段階適用） | A | 1〜2日 |
| 5 | `SESSION_DRIVER` をファイルへ変更（`DB_CONNECTION` は意図的な既定値なので触らない） | C | 0.25日 |
| 6 | `RefreshMultipleDatabases` を指紋変化時に `migrate:fresh` へ | I | 0.5日 |
| 7 | READMEに nginx DNSキャッシュのトラブルシュートを追記 | J | 0.1日 |
| 8 | `tool/config/database.php` の接続設定を修正 | D | 0.25日 |

**1〜3は合計0.5日弱**で、いずれも「今後の劣化を検知する仕組み」に効く。
4はコード量が大きいぶん時間がかかるが、
放置するほど差分が増えて入れにくくなる種類の負債。

Tool（D）はプロダクトとしての優先度次第。
現状8ファイルで実質未着手のため、着手時期を決める判断が先にある。
