# Nexus プロジェクト評価レポート

| 項目 | 内容 |
| --- | --- |
| 評価日 | 2026-11-03 |
| 対象コミット | `90118170` (main) |
| 前回評価 | [2026-09-01](./project_evaluation_2026-09-01.md)（当時の `646eaf4`。履歴の書き換えで、現在の main では `82233a0d` が同じ時点にあたる） |
| 評価方法 | テストスイート・カバレッジ・静的解析・フォーマッタ・依存パッケージ監査の実行、起動中コンテナへの実HTTPリクエスト（署名付きサインアップから主要APIまで）、前回指摘11件（A〜K）の追跡 |

---

## 総評

前回の指摘11件のうち **8件が解消**、2件が一部解消、1件は前回の誤指摘の訂正で対応不要。
品質ゲートは全て緑で、前回「守備範囲の穴」とした `packages/` のフォーマット・`make check` の範囲・カバレッジ下限はいずれも埋まった。

```
Tests:  2183 (6596 assertions), Skipped: 1     OK
PHPStan level 6 (api/app + 27パッケージ)       [OK] No errors
Pint (api / packages / tool)                    OK
Lines:   90.23% (12605/13970)
Methods: 83.52% (2834/3393)
Classes: 65.41% (450/688)   ← 前回 57.48%
```

この2か月で、データ引き継ぎ・交換所・ミッション・利用規約・WebSocket通知など機能が大きく増えた。
新しい機能のうちPRで入ったもの（交換所・データ引き継ぎ・ミッション）は、パッケージの単体テストとエンドポイントのテストが揃っている。

一方で、課題の性質が再び変わった。**「検査の穴」から「検査をすり抜けて入った未完成の機能と、利用者に見える異常系」へ**移っている。

- ガチャはダイヤが足りないと **HTTP 500** を返す（業務エラーの299にならない）
- WebSocket通知は、**通知を作る処理がどこからも呼ばれておらず**、サーバー（`api-reverb`）も**起動直後に落ちる**。main に直接コミットされた機能で、レビューを経ていない
- 依存パッケージに既知の脆弱性がある（api 6件、tool 40件超。tool には critical を含む）。CIでも監査していない

---

## 前回指摘の追跡

| # | 指摘 | 状態 | 備考 |
| ---: | --- | --- | --- |
| A | `packages/` がフォーマッタの対象外 | ✅ 解消 | CI・`make pint`・`make pint-test` が `../packages` を明示的に検査。違反0件 |
| B | ギルドの読み取り3本が無認証・レート制限なし | ✅ 解消 | `throttle.public:guild_read`（IPごとに毎分60回）。`X-RateLimit-*` ヘッダーも返る。公開は意図どおり |
| C | `DB_CONNECTION=sqlite` で web ルートが500 | ✅ 解消 | `SESSION_DRIVER=file`。`GET /` は200 |
| D | Tool が動かない | ✅ 解消 | 8 → 23ファイル。ログイン・ダッシュボード・プレイヤー・アカウント/ロール/ページ管理・マスター取り込み。CIにtoolジョブ、テスト16件。ただし依存が古い（新規の N3） |
| E | CIのカバレッジ下限55% | ✅ 解消 | `--min=85` |
| F | `make check` の守備範囲が狭い | ✅ 解消 | pint（api + packages）→ PHPStan → 全テスト → tool の検査。CIと同じ |
| G | クラスカバレッジ57% | 🔺 改善中 | 65.4%。未カバーが多いのは `SlackErrorNotifier`（4%）、`TrxRewardTrack*Repository`（30%前後） |
| H | 前回指摘7（namespace）の訂正 | — | 対応不要（前回で訂正済み） |
| I | テストDBが既存マイグレーションの編集に追随しない | ✅ 解消 | 指紋が変わると `migrate:fresh`。ただし開発・共有環境では同じ問題が残る（新規の N7） |
| J | nginx のDNSキャッシュでAPIが壊れる | 🔺 一部 | READMEにトラブルシュートは追加。根本原因は残り、本評価中にも再現（後述） |
| K | `NAMING_CONVENTIONS.md` のリンク切れ | ✅ 解消 | |

### J の再現

本評価の冒頭で、APIが全エンドポイントで **502** を返した。`make up` が `api-php` を作り直した（11分前）一方、`api-web` は11日前から動き続けており、古いIPへ送っていた。`docker compose restart api-web` で復旧した。

- `make check-quiet` / `make test-quiet` は先頭で `make up` を呼ぶため、Dockerfile やイメージが変わると**検査を回しただけでAPIが止まる**
- READMEの見出しは「APIが全て404を返す」だが、今回の症状は502。同じ原因で症状が変わる
- nginx に `resolver 127.0.0.11` を設定し `fastcgi_pass` を変数経由にするか、`make up` で `api-web` も再起動するのが根本対応

---

## 現在の規模

| 対象 | ファイル数 | 行数 | 前回 |
| --- | ---: | ---: | --- |
| `api/app` | 518 | 40,781 | 331 / 30,869 |
| `packages/*/src`（28パッケージ） | 310 | 27,660 | 231 / 22,285（20パッケージ） |
| `tool/app` | 23 | 2,887 | 8 / — |
| テスト（`api/tests` + `packages/*/tests` + `tool/tests`） | 274 | — | 229 |

| 指標 | 値 | 前回 |
| --- | ---: | ---: |
| コミット数 | 573 | 426 |
| 前回評価以降のコミット | 117 | — |
| PR番号の付いたマージ（前回評価以降） | 40（#73〜#108、#112〜#115） | — |
| 追跡ファイル総数 | 1,483 | 1,147 |
| アーキテクチャテスト | 12種 | 10種 |
| TODO / FIXME / HACK | 0 | 0 |

---

## 🔴 優先して直すべき問題

### N1. ガチャのコスト不足が HTTP 500 になる

`GachaCostService` はダイヤ・アイテムの残高を確かめずに消費し、不足は下位の `\Exception` に任せている。

```php
// packages/nexus-resource/src/Services/DiamondService.php
throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: 0");
```

`GameException` ではないため、`_BaseController` はシステムエラーとして扱い、**HTTP 500 + `error_code: 999`** を返し、Slackにも通知する。
ダイヤ不足はプレイヤーが日常的に起こす操作で、クライアントは「不足」と「障害」を区別できない。

- 専用のコードは既にある（`GameErrorCode::DIAMOND_NOT_ENOUGH` / `ITEM_NOT_ENOUGH`、`BusinessLogicException::itemNotEnough()`）。ユニット・装備の強化とアイテム使用は事前に確かめて使っているが、ガチャだけが使っていない
- 交換所（`TradeCostPayerAdapter`）は消費の前に所持数を確かめて299を返しており、同じ形に揃えられる
- 業務エラーなのに `throw new \Exception(` しているのは api 4か所・packages 7か所

### N2. WebSocket通知が実際には動かない

2026-10-28 に main へ直接コミットされた通知機能（Reverb、ギルド全員向け通知）は、テストは緑だが、通しで動かすと次の3点で止まる。

1. **通知を作る処理がどこからも呼ばれていない**。`NotificationService::notify()` / `notifyGuild()` の呼び出し元は本番コードに無い。実際にフレンド申請を送っても、受け取った側の `/api/notification/list` は空のまま
2. **`api-reverb` コンテナが起動直後に落ちる**。PHPイメージに `pcntl` 拡張が無い

   ```
   Undefined constant "Laravel\Reverb\Servers\Reverb\Console\Commands\SIGINT"
   ```

3. **ブロードキャストが sqlite のキューに積まれる**。`QUEUE_CONNECTION=database` で `DB_QUEUE_CONNECTION` が未設定のため、キューは既定接続の sqlite（ファイルが無い）を使う。キューのワーカーも起動していない。1を直して通知を作り始めると、ここで失敗する見込み

テストは `BroadcastFactory` を差し替えて「積まれたこと」だけを確かめており、どの段階の欠落も検出できない。

### N3. 依存パッケージの既知の脆弱性

`composer audit` の結果。

| 対象 | 件数 | 主なもの |
| --- | ---: | --- |
| api | 6件（high 3） | `symfony/http-foundation` の `Cache-Control` リクエストヘッダー経由の ReDoS（CVE-2026-108082。**任意のリクエストから届く**）、`symfony/yaml`、`league/commonmark` |
| tool | 40件超（critical 1） | `laravel/framework` の TokenGuard 認証回避（CVE-2026-114509）、`guzzlehttp/*`、`symfony/*` |

- tool は `laravel/framework` v12.46.0（api は v12.69.1）。`tool/composer.lock` は 2026-09-04 から更新されていない
- tool は TokenGuard を使っていないため critical の直接の影響は無いと見られるが、管理画面であり放置する理由も無い
- CIに `composer audit` が無く、Dependabot / Renovate も設定されていない

---

## 🟠 残っている問題

### N4. `nexus-agreement` が PHPStan とカバレッジの対象外

28パッケージのうち `nexus-agreement` だけが `api/phpstan.neon` と `api/phpunit.xml` の対象に入っていない。単体で解析すると実エラーは0件で、登録漏れだけが問題。
パッケージを足すたびに3ファイル（`composer.json` / `phpstan.neon` / `phpunit.xml`）を手で直す運用のため、また漏れる。
**`packages/*` が全て両方に載っていることを確かめるアーキテクチャテスト**を足すのが確実。

### N5. テストが実行順に依存する

- 約10ファイルが `sys_player` を **id=1 決め打ち**で作る（`LoginBonusServiceTest`、`VipLoginBonusServiceTest`、`TrxUnitRepositoryTest` など）
- 一方、`signUpPlayer()` を使う27ファイルは作ったプレイヤーを消さない
- 全体実行では Unit が先に走るので表に出ないが、スキーマ変更直後に Feature を単体で回すと id=1 が残り、決め打ちのテストが**20件まとめて落ちる**ことを本評価の期間中に確認した
- `AssignPlayersCommandTest` も実行順によって落ちる（「割り当て: 1 人」が出ない）

決め打ちをやめて `nonExistentId()` のように空いているIDを使うか、`signUpPlayer()` 側で後始末する。

### N6. main への直接コミット

`CLAUDE.md` は「main へ直接コミットしない」と定めているが、前回評価以降に first-parent で PR を経ずに入ったコミットが11件ある（9月に4件、10/28 に利用規約・Reverb・ギルド通知の7件）。
N2 の通知機能はまさにこの経路で入っており、レビューがあれば「呼び出し元が無い」「コンテナが起動しない」は気づけた可能性が高い。
GitHub の Branch protection で main への push を禁止するのが確実。

なお、`#109` / `#110` で入った2件は、メールアドレスの書き換え（force push）の際にコミットメッセージから PR 番号が消えている。

### N7. 既存マイグレーションの書き換えが、共有環境で反映されない

リリース前のため既存のマイグレーションを書き換える運用で、テストDBは I の対応で追随するようになった。
しかし開発・検証環境では通常の `make migrate` に反映されない。本評価では、データ引き継ぎ（#114）が既存のファイルに足した `sys_player_transfer_code` / `sys_player_external_account` が開発DBに作られず、`/api/account/*` が **500**（テーブルが無い）になった。

リリース（または共有の検証環境を作る時点）を境に、**既存のマイグレーションは凍結し、変更は新しいファイルで足す**運用へ切り替える日付を決めておく必要がある。

### N8. 開発環境の初期状態でサインアップが500になる

開発DBが空の状態（`make up` だけ）だと、サインアップが次の **500 / `error_code: 999`** で失敗する。

```
Active sharding definition not found: trx_sharding
```

シャーディング定義は `SysShardingSeeder` で入るが、`make up` はシードしない。
初期データの欠落は運用上も起こりうるので、インフラ系のエラーコードを持つ例外にし、READMEの起動手順で `make seed` を必須として明記したい。

---

## 🟡 改善の余地

### N9. APIの命名の揺れ

`.claude/api.md` の「配列のキーには `_list` を付ける」に従っていないレスポンスが14ファイルある。

```
friends, guilds, members, rooms, messages, applies, prizes, notifications,
results, lines, milestones, mailbox_array, content_array, unread_counts …
```

- 本文を `success` で包まない規約に対し、`'success' =>` を返すレスポンスが5つ
- パスも揺れている: `/reward-track/*`（ケバブケース）、`/chat/messages`・`/chat/rooms`（動作でなく名詞）
- 非推奨の `POST /signup` が残り、コメントは存在しない `/auth/signup` を案内している
- `ResponseKeyNamingTest` は `*_id` しか見ていない。`_list` も同じテストで検査できる

クライアントが既に使っていれば互換性の判断が要るため、リリース前に決めるのがよい。

### N10. 規約からの逸脱が少し残る

- `now()` / `Carbon::now()` 等の直接呼び出しが26か所（`TrxMailboxRepository` 5、`SysPlayerToken*` 6 など）
- `Gacha\UseCases\DrawUseCase` が `request()` ヘルパーでヘッダーを読む（Domain から HTTP を触っている。`ModuleBoundaryTest` はヘルパー呼び出しを検出しない）。しかも読むのは `X-Request-ID` で、APIが定める `X-Unique-Request-Identifier` ではない

### N11. `docs/` の設計資料が古い

`docs/` の12ファイルが、既に無いログ専用DB・`connectionName` を受け取るAPIなどを前提に書かれている（`log_db_sharding_design.md` は該当70か所）。
`.claude/` は `CLAUDE.md` に合わせて整理されたが、`docs/` は取り残されている。現行の仕組みと食い違う資料は、削除するか「過去の設計」と明示する。

### N12. コンテナ定義

- ローカルは `nginx:latest` / `mysql:latest`、CIは `mysql:8.0`。ローカルとCIでMySQLのバージョンがずれうる
- `docker/api-nginx/nginx.conf` はどこからも使われておらず、存在しない `admin-php` を参照している

---

## 🟢 良い点

### 品質ゲートが全て緑で、範囲も揃った

- PHPStan level 6・pint・全テスト・tool の検査を、ローカル（`make check`）とCIで同じ範囲で回している
- カバレッジ下限を実測の直下（85%）に置き、低下を検知できる
- アーキテクチャテストが12種に増え、`ModuleBoundaryTest`（コンテキスト間は公開APIだけ）や `NoDirectEloquentWriteTest` で設計を機械的に守っている

### 新機能の作り方が揃ってきた

交換所・データ引き継ぎ・ミッションは同じ形で作られている。

- パッケージが契約（Repository・コスト支払い・IDトークン検証）を宣言し、api が `*Adapter` で実装して差し込む
- パッケージは Fake だけで単体テストでき、api 側はエンドポイントで通しのテストを持つ
- 業務エラーはパッケージ例外 → `*ExceptionTranslator` → `GameErrorCode` の順に変換する

交換所の「足りなければ消費前に299」はこの流れで作られており、N1 のガチャはこれに揃えれば直る。

### 安全側の設計判断

- データ引き継ぎは、パスワードの誤りを数えてロックし、その回数は業務エラーで巻き戻さずに残す。新端末で別のプレイヤーが遊んでいれば端末を付け替え、元のトークンを無効にする
- IDトークンは署名・期限・iss・aud を全て確かめ、発行先が未設定の種類は使えなくしている
- ログインボーナスは、VIP・カムバックが実際に配られていなかった不具合と、カムバック期間の日数の数え方の不具合が見つかって直された

### エージェントと人が同じ規約を読む

`CLAUDE.md` を「毎回守ること」だけに絞り、詳細を `.claude/*.md` に分けた。結果だけを出す `make check-quiet` / `make test-quiet`、重いディレクトリの読み取り禁止、編集時の自動整形により、エージェントの作業でも規約から外れにくくなっている。

---

## 推奨する着手順

| 順 | 対応 | 該当 | 目安 |
| ---: | --- | --- | --- |
| 1 | ガチャのコスト不足を消費前に判定し、`DIAMOND_NOT_ENOUGH` / `ITEM_NOT_ENOUGH` を返す。業務エラーの `\Exception` を置き換える | N1 | 0.5日 |
| 2 | api / tool の依存を更新し、CIに `composer audit` を足す（Dependabot も検討） | N3 | 0.5日 |
| 3 | main の Branch protection を有効にする | N6 | 0.1日 |
| 4 | `nexus-agreement` を解析・カバレッジに登録し、全パッケージの登録を確かめるアーキテクチャテストを足す | N4 | 0.25日 |
| 5 | 通知を作る箇所（フレンド申請・ギルドなど）をつなぎ、`pcntl` 追加・キュー接続の設定・ワーカー起動まで通しで動かす | N2 | 1〜2日 |
| 6 | テストの id=1 決め打ちをやめ、`signUpPlayer()` の後始末を共通化する | N5 | 0.5日 |
| 7 | nginx の `resolver` 設定（または `make up` で `api-web` を再起動） | J | 0.1日 |
| 8 | 既存マイグレーションを凍結する日付を決める。サインアップの初期データ欠落を分かるエラーにする | N7, N8 | 0.25日 |
| 9 | レスポンスキーとパスの命名を決め、`ResponseKeyNamingTest` で `_list` も検査する | N9 | 1日（互換性の判断を含む） |
| 10 | `docs/` の古い設計資料を整理する | N11 | 0.5日 |

**1〜4は合計1.5日弱**で、利用者に見える500と、外から届く脆弱性と、今後の取りこぼしを防ぐ仕組みに効く。
5は機能としての完成が要るため、通知をリリースに含めるかどうかの判断が先にある。

---

## 付記：評価中に開発環境へ加えた変更

実HTTPでの確認のため、ローカルの開発環境に次の変更を加えた（いずれも既存の行は消していない）。

- `make migrate`（足りないテーブルの作成のみ）と `SysShardingSeeder` の実行
- 確認用のプレイヤー5人の作成（サインアップ・フレンド申請）
- `docker compose restart api-web`
