# 動画撮影用シード

クライアントの動作確認（タイトル〜ホーム。`NexusGodotMobileGameClient/docs/design/title-to-home/client_design.md` の 7 節）を動画に撮るために、サーバーの状態を整えるシーダー。

**このフォルダに動画撮影用のものをすべて置く。** 撮影が増えるたびに、このフォルダの中にシナリオを足していく。

## フォルダの構成

| パス | 役割 |
| --- | --- |
| `README.md` | このドキュメント |
| `RecordingSeeder.php` | 入口。既定で流すシナリオの一覧（`defaultScenarios()`）を持つ |
| `RecordingScenarioSeeder.php` | 基底クラス。環境ガード、マーカーの定数、共通の出力 |
| `Scenarios/` | シナリオ（1 クラス 1 場面）。下の一覧を参照 |
| `api/tests/Feature/Recording/` | テスト（フォルダの外。テストの置き場の規約による） |

フォルダの外にあるのは次の 3 つだけ（置き場所を変えられない）: `Makefile` のターゲット、サーバーの `CLAUDE.md` のコマンド表の 1 行、上のテスト。シーダーは composer の PSR-4（`Database\Seeders\`）の都合で `database/seeders/` の下に置く必要があり、その配下の `Recording/` を専用フォルダにしている。

## 前提

- `make up` でサーバーが動いていること
- **`make seed`（基本のシード）が済んでいること**（`make migrate-fresh` の後は `make seed` を流す）
- `make migrate-fresh && make seed` で DB を作り直した後は、**もう一度 `make seed-recording` が必要**（動画用の行も消えるため。ケース 10 の注記）
- 実行環境が `local`（開発）または `testing`。それ以外の環境（production など）では、どのシナリオも例外で止まる

## 使い方

| 目的 | コマンド |
| --- | --- |
| 既定のシナリオをまとめて流す | `make seed-recording` |
| シナリオを 1 つだけ流す | `make seed-recording-one SEEDER=<クラス名>`（例: `SEEDER=RecordingMaintenanceOnSeeder`） |
| 動画用のものを消して元に戻す | `make seed-recording-reset` |
| キャッシュを全消去する（開発専用） | `make seed-recording-clear-cache` |

Docker を使わない直接の形は `php artisan db:seed --class='Database\Seeders\Recording\Scenarios\<クラス名>' --force`（実際は `docker compose exec -T api-php` 経由で流す）。

## シナリオ一覧

ケース番号は client_design.md 7-2 の表の `#`。

| クラス（`Scenarios/`） | 内容 | 使うケース | 既定に入るか |
| --- | --- | --- | --- |
| `RecordingDeploySeeder` | `sys_deploy` が空でも version API が成功する最小のデプロイ（master・asset・deploy を各 1 行）。有効なデプロイが既にあれば何もしない | 全ケースの前提 | 入る |
| `RecordingAgreementSeeder` | 規約 3 件: 公開済みの必須（`rec-required`）・公開済みの任意（`rec-optional`）・公開日が未来の必須（`rec-future`） | 3, 4, 12, 13, 15 | 入る |
| `RecordingLoginBonusSeeder` | 通常ログインボーナス `daily_login`（7 日サイクル）を、有効・期間なしで保証する。他の行は消さない | 1, 2, 3, 5, 13 | 入る |
| `RecordingLoginBonusDisabledSeeder` | `daily_login` を無効にする（ログインボーナスなしの画面用） | ログインボーナスなし | 入らない |
| `RecordingMaintenanceOnSeeder` | メンテナンスを有効にする（API が 503 になる） | 8 | 入らない |
| `RecordingMaintenanceOffSeeder` | 動画用のメンテナンスを解除する | 8（解除して再試行） | 入らない |
| `RecordingResetSeeder` | マーカー付きの行を削除し、メンテナンスを解除し、`daily_login` を有効に戻す | 規約なしに戻す・撮影の終わり | 入らない |

### 既定に入れない理由

API を止める（メンテナンス）、画面を変える（ログインボーナス無効）、データを消す（リセット）ものは、撮影の途中で意図せず流れると他の撮影を壊す。必要なときだけ `seed-recording-one` で流す。

## client_design.md 7 節のケースとシナリオ

サーバーの状態に関するケースだけを載せる（クライアントの保存・停止・429 などはサーバーのシードでは作らない）。

| ケース | サーバーの状態 | 流すシナリオ |
| --- | --- | --- |
| 1 | 規約なし、ログインボーナスあり | `seed-recording-reset` の後に `RecordingLoginBonusSeeder`（規約は入らない）。デプロイが空なら `RecordingDeploySeeder` も |
| 2 | 同上（同じ端末の 2 回目） | 1 と同じ |
| 3, 4 | 必須規約が 1 件 | `make seed-recording`（`RecordingAgreementSeeder` を含む） |
| 5 | ログインボーナス 2 日目 | `RecordingLoginBonusSeeder`。`sys_player.last_login_at` の調整は別途（シード対象外） |
| 8 | メンテナンス 503 | `RecordingMaintenanceOnSeeder` → 撮影 → `RecordingMaintenanceOffSeeder` |
| 12, 13 | 規約が 1 件ある | `make seed-recording` |
| 15 | 規約が 1 件ある | `make seed-recording` |
| ログインボーナスなし | `daily_login` が無効 | `RecordingLoginBonusDisabledSeeder` |
| 6, 7, 9, 10, 11, 14 | サーバーのシードでは作らない（停止・応答の差し替え・保存データの操作・配信ホストの不通）。14 は未取得の版が要るが、動画用のデプロイは配信ファイルを持たないので代わりにならない | なし |

## 動画撮影の標準手順の例

### 新規ユーザーで規約あり → ログインボーナス → ホーム（ケース 3）

1. `make seed-recording`（デプロイ・規約・ログインボーナスを入れる）
2. クライアントを `--e2e-reset` で起動して撮影する
3. 撮り終えたら `make seed-recording-reset`

### メンテナンス 503 → 解除（ケース 8）

1. `make seed-recording-one SEEDER=RecordingMaintenanceOnSeeder`
2. クライアントを起動して、メンテナンス表示と「再試行」を撮る
3. `make seed-recording-one SEEDER=RecordingMaintenanceOffSeeder`
4. 再試行してホームまで進むところを撮る

## シナリオの足し方

1. `Scenarios/` にクラスを作る。`RecordingScenarioSeeder` を継承し、`apply()` だけを書く（`run()` は環境ガード付きで固定）。クラスの冒頭に、目的・使うケース・冪等性・副作用を日本語で書く
2. 既定で流したいものだけ `RecordingSeeder::defaultScenarios()` に足す（入れないものは `seed-recording-one` で流す）
3. 動画用の行にはマーカー（下）を付け、`RecordingResetSeeder` がその行を消せるようにする。新しい種類のテーブルを使うなら、Reset の削除にも足す
4. このドキュメントの「シナリオ一覧」と、必要なら「ケースとシナリオ」の表に足す
5. `api/tests/Feature/Recording/` にテストを足す（2 回流して行数が変わらない、マーカー行だけを作る・消す、既定の一覧に入るか）

## マーカーの規則

動画用の行は目印で見分け、**目印のある行だけを作る・消す**。定数は `RecordingScenarioSeeder` にある。

| テーブル | 目印 |
| --- | --- |
| `sys_agreement` | `version` が `rec-` で始まる |
| `sys_maintenance` | `title` が `[REC]` で始まる |
| `sys_deploy_master` / `sys_deploy_asset` | `description` が `[REC]` で始まる（`sys_deploy` は `deploy_key = 190001010`） |
| `mst_login_bonus` | 目印なし。`daily_login` の有効フラグだけを切り替える（行は消さない） |

## 冪等性と安全性

- **追加専用・冪等**: 何度流しても同じ結果。既存のシーダー（`truncate` で作り直す）と違い、既存データを消さない。キーで `updateOrInsert` する
- **環境ガード**: `local` / `testing` 以外では例外で止まる。本番に誤って流さないため。シナリオの入口（`run()`）で毎回検査する
- 例外: `RecordingResetSeeder` は、マーカーの無い `daily_login` の `is_active` を true に更新する
- マスター（`mst_*`）への書き込みは `_BaseMst::allowWrites()` の中だけで行う
- キャッシュ: 503 のミドルウェアは `MaintenanceService` のキャッシュ（既定 60 秒）、version API は Redis のキャッシュ（メンテナンス・最新デプロイとも 3600 秒）を先に見る。`RecordingMaintenanceOnSeeder` / `RecordingMaintenanceOffSeeder` / `RecordingResetSeeder` / `RecordingDeploySeeder` は末尾でこれらを消すので、流した直後に反映される（ケース 8 は On → 撮影 → Off → 再試行で撮れる）。それ以外の手段（SQL など）で DB を直接変えたときは、TTL を待つか `make seed-recording-clear-cache` で消す
- 回復手段（レート制限）: sign_up は IP あたり 10 回/時間・device あたり 3 回/時間で、成否にかかわらず数える。撮影で使い切ったら `make seed-recording-clear-cache`（`cache:clear`）でカウンターごと消せる。**キャッシュを全部消すので local 限定**
- 注意（規約）: 動画用ではない必須規約が既にあると、それも pending に出る。ケース 1・2 の撮影前に `seed-recording-reset` を流し、他の規約が無いことを確かめる
- 注意（任意規約）: pending は必須規約だけを返すので、`rec-optional` は規約画面に出ない（任意の扱いの確認用の行）
