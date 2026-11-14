# ホーム＋プレイヤー情報 API 設計（S2）

機能 `home-player-info` のサーバー API 設計。**新しいエンドポイントは作らない。** 既存の `POST /api/auth/login` の応答を、ホームが使う項目について確定し、本機能で変わる点（スタミナ回復間隔の MstConfig 化、サインアップ時の初期データ、`is_delete` の型）を書く。確かめられなかったことは「未確認」と書き、9 節に集める。

- 全体設計: `Nexus/docs/design/home-player-info/overview.md`（Nexus リポジトリ。マージ済み）
- DB: [db.md](db.md)、シーケンス: [sequence.md](sequence.md)
- login・認証・共通ヘッダー・冪等・排他・エラーの共通事項: [title-to-home の api.md](../title-to-home/api.md)（1 節・6 節。**ここには複製しない**）
- 確かめ方の凡例: 「実行」= 開発環境のコンテナで読み取りだけを実行して確認した。「読んだ」= コードを読んだだけ。「未確認」。開発 DB への書き込み（seed・migrate・login・sign_up など）は行っていない。

## 1. 結論

| 項目 | 結論 |
| --- | --- |
| 新しい API | なし |
| login の応答のキー | **`trx_diamond_list`（配列）が `trx_diamond`（オブジェクト。`X-Platform` の platform の 1 行。行が無いとき `null`）に変わる（D5。破壊的変更。11 節）**。それ以外（`sys_player`・`trx_unit_list`・`trx_item_list`・`trx_wallet_list`・`trx_equipment_list`・`trx_stamina_list`・`login_bonus_list`）は変更なし |
| login の応答の中身の変化 | (1) スタミナの回復計算が MstConfig の値を使う（応答の形は同じ）。(2) **新規プレイヤーの `trx_*_list` が空でなくなる**（4 節。Q9 の初期データ）。(3) `is_delete` を JSON の真偽値にする（3 節。Q8 の人の決定。現行実装は 0/1 の整数なので**実装の変更が要る**）。(4) **ダイヤは `trx_diamond`（1 件）になり、無償ダイヤの付与の既定 platform が `X-Platform` の値になる**（D5。11 節） |
| sign_up の応答 | 変更なし。初期データは応答に含めない（クライアントは sign_up の後に login で取る。title-to-home のとおり） |
| 配信 | `GET /auth/version` と配信 SQLite は変更不要。`mst_config` は `mst_*` として自動で配信対象になる（6 節）。`mst_player_level` も同じ。実際にデプロイ済みかは運営作業（6 節） |
| **全 API 共通の契約変更（Q16）** | **ゲーム API のすべてで HTTP ヘッダー `X-Platform`（`Apple` / `Google`）を必須にする**（10 節）。欠落・不正値は HTTP 299 + 新しいエラーコード。sign_up の初期 `trx_diamond` の行の platform に使う。S3 の範囲が広がる |
| サインアップ時の初期データ（Q9・Q15〜Q19。決定） | sign_up で `trx_stamina`・`trx_wallet`（coin 1000）・`trx_diamond`（0 行）・`trx_item`（`free_diamond` 50）を作る。値は API の定数。`mst_item` に `free_diamond` の行を足す。**初期の無償ダイヤは別機能の完了まで消費できない**（4 節・db.md 8.6 節） |
| エラー・冪等・排他 | login・sign_up の既存のエラーは変更なし。ただし**全ゲーム API に platform ヘッダーのエラー（299 / 99003）が増える**（7 節・10.3 節） |

## 2. POST /api/auth/login の応答（ホームが使う項目の確定）

ミドルウェア・入力・トランザクション・エラーは [title-to-home の api.md 6 節](../title-to-home/api.md) のとおり。応答全体の構造もそちらを指す。ここでは**ホームが読む項目**の型・null 可否・書式を確定する。「実行」は開発 DB の既存行を `toResponseArray()` に通した結果。

### 2.1 共通の約束

- 日時は文字列 **`Y-m-d H:i:s`（例 `2026-11-14 02:03:19`）で、タイムゾーンの表記が付かない。値は UTC**（3 節）。ISO 8601 ではない。
- 数量（`*_amount`・`current_stamina`・`level`）は JSON の整数。
- 一覧は空のとき `[]`（null にならない）。
- 各行から `sys_player_id`・`uuid`・`created_at`・`updated_at` は除かれる（`_BaseModel::toResponseArray`。読んだ）。

### 2.2 sys_player（オブジェクト。null 不可）

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| my_id | string | 不可 | 8 文字のプレイヤー ID。ホームに出す |
| name | string | 可（DB は null 可。sign_up はランダム 8 文字を入れるので、通常は非 null） | ホームに出す。null のときの表示はクライアント（Q7） |
| level | int | 不可 | ホームに出す。`mst_player_level` の行を引く鍵 |
| level_exp / vip_point / total_paid_amount / last_login_at | - | - | ホームでは使わない（P2）。`total_paid_amount` は文字列 `"0.00"`、`last_login_at` は 3 節 |

実行: `{"my_id":"LIHDE6NX","name":"TestPlayer1","level":1,"level_exp":0,"vip_point":0,"total_paid_amount":"0.00","last_login_at":null}`（sign_in 直後の未ログインの行。login 後は更新後の日時が入る）。

### 2.3 trx_stamina_list（配列。空は `[]`）

ホームは `type` が `normal` の行だけを使う（P1）。

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| type | string | 不可 | `normal` / `raid` / `pvp` / `event` |
| current_stamina | int | 不可 | **自然回復を login 時点まで加算した現在値**（DB には保存しない）。最大値を超えることがある（レベルアップ時の加算。Q11） |
| recovery_rate_multiplier | string | 不可 | 回復速度倍率。`"1.00"`（小数 2 桁の**文字列**。数値ではない）。クライアントは数値に直して使う |
| last_recovery_at | string | 不可 | 回復反映済みの基準時刻。`Y-m-d H:i:s`・UTC。サーバーが加算した回復点数ぶんだけ進めた値（`floor(回復点数 × 回復間隔 ÷ 倍率)` 秒）。**現在値が最大値以上のときは進めない**ので、古いままのことがある（先に最大値判定をすること。overview 8 節） |
| is_delete | boolean | 不可 | 3 節。論理削除の行は返らない（Repository が `is_delete = false` で絞る。読んだ） |

- 回復間隔は応答に含まれない。MstConfig の `stamina_recovery_interval_seconds`（秒）を、マスター配信で受け取る（クライアントの式は overview 8 節の「Q4・Q5 の影響」）。
- `recovery_rate_multiplier` は VIP 特典などで 1.00 以外になる。回復点数 = `floor((now − last_recovery_at) × 倍率 ÷ 回復間隔)`。

### 2.4 trx_wallet_list（配列）

ホームは `mst_item_id` が `coin` の行だけを使う（`gold` など他の行は使わない）。

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| mst_item_id | string | 不可 | `coin` など |
| free_amount | int | 不可 | 無償の残高。コインはこれを出す |
| paid_amount | int | 不可 | 有償の残高 |
| is_delete | boolean | 不可 | 3 節 |

実行: `{"mst_item_id":"gold","free_amount":6000,"paid_amount":0,"is_delete":0}`（`is_delete` が整数。3 節）。`coin` の行が無いとき（残高なし）は行が無い。クライアントが 0 として扱う（S4）。

### 2.5 trx_diamond（オブジェクト。`X-Platform` の platform の 1 行。D5）

**変更（D5。人の決定 2026-11-14）**: 現行・title-to-home の `trx_diamond_list`（配列。platform ごとの行）をやめ、**`X-Platform` の platform の 1 行だけを `trx_diamond`（オブジェクト）で返す**。端末は 1 つの platform しか使わないので複数にならない。経緯・組み立て・影響は 11 節。

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| trx_diamond | object / null | **可**（仮置き） | その platform の行が無いとき `null`。サインアップ時に作る（4 節）ので通常は常にある。既存プレイヤーで行が無い・別 platform の行しか無い場合は `null`。**クライアントは `null` を有償 0・無償 0 として扱う**（S4） |
| trx_diamond.platform | string | 不可 | `Apple` / `Google`。常に `X-Platform` の値と同じ |
| trx_diamond.paid_amount | int | 不可 | 有償ダイヤ |
| trx_diamond.free_amount | int | 不可 | 無償ダイヤ（**暫定**）。この platform の行の分だけ。**D4 により、別機能 free-diamond-item の完了後はこの項目が消える**。クライアントは無償ダイヤを `trx_item_list` の `free_diamond` から取る。サインアップ時の無償ダイヤ 50 は `trx_item` の行で、ここには入らない。ログインボーナスなどの無償ダイヤは、**D5 により `X-Platform` の platform の行に入る**（11.3 節） |
| trx_diamond.is_delete | boolean | 不可 | 3 節 |

実行（旧形式。1 行の中身は変わらない）: `{"platform":"Apple","paid_amount":0,"free_amount":10000,"is_delete":0}`。新形式では `"trx_diamond": {"platform":"Apple", ...}`。

### 2.6 trx_item_list（配列）

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| mst_item_id | string | 不可 | ホームは、無償ダイヤ（`free_diamond`）の行の `free_amount` を使う（サインアップ時に 50 が入る。4 節） |
| free_amount | int | 不可 | |
| paid_amount | int | 不可 | |
| is_delete | boolean | 不可 | 3 節 |

実行: `{"mst_item_id":"event_points","free_amount":685,"paid_amount":216,"is_delete":0}`。`free_diamond` の行は、ホームの S3 がサインアップ時に作り、`mst_item` の行も足す（Q17。別機能 free-diamond-item の既存データの移行・消費は決めない。影響は 4 節と db.md 8.3 の Q17）。

## 3. Q8 の確認結果: `is_delete`・日時の書式

### 3.1 `is_delete`（Q8 決定: BOOL。**現行実装と食い違うので、S3 で直す**）

人の決定（2026-11-14。Q8）は「`is_delete` は JSON の真偽値」。確かめた現行の挙動は次のとおりで、**食い違う**。

| 条件 | 応答の値 | 確認 |
| --- | --- | --- |
| DB から読み込んだ行（login の全行がこれ） | **整数 `0` / `1`** | 実行: 開発 DB の `trx_item`・`trx_wallet`・`trx_diamond` の行を `toResponseArray()` に通すと `"is_delete":0`。`is_delete` のキャストがモデルに無い（`TrxStamina` の `$casts` にも無い。読んだ）。カラムは `tinyint(1)`（実行）。**スタミナの行そのものは、開発 DB に行が無く実行できていない**（同じ基底クラス・同じ型なので同様の見込み） |
| PHP の `false` を入れて作った未保存のモデル | `false`（真偽値） | 実行（in-memory）。login には来ない経路 |
| login の写し（`HomeUseCase::recoveredTrxStaminas`） | 整数 | 読んだ。`newInstance($trxStamina->getAttributes(), true)` は DB の生の値を写すだけ |

title-to-home の api.md の JSON 例は `false` と書いているが、実装の出力ではなくコードから組み立てた例（同ファイル冒頭の注記）。

**決定（S3 の範囲）**: `Nexus\Core\Models\_BaseModel::toResponseArray()` の最後で `is_delete` が配列にあれば `(bool)` に変換する。応答にだけ効き、DB・永続化・モデルの属性は変えない。理由: 全 trx の応答（login と `updated_resources`）が 1 か所で揃う。モデルごとの `$casts` は子クラスの定義で上書きされるので、基底の `$casts` に足しても効かない（`TrxStamina` などが `$casts` を持つ）。

- 影響: `toResponseArray` の呼び出しは 28 か所（モデルの override 43 ファイルは parent に委譲しており、基底の変更は全モデルに効く）で、login 以外の API（`updated_resources`）の `is_delete` も真偽値になる。`is_delete` の応答の値を検証するテストは見つからなかった（`api/tests` の `is_delete` はほぼ DB 行の検証。読んだ）が、**他のクライアント機能が 0/1 を前提にしていないかは未確認で、S3 の作業として洗い出す（U-1）**。クライアント側は、移行の間は真偽値と 0/1 の両方を受ける実装が安全。
- 実装後は、title-to-home の api.md の JSON 例（`false`）と一致する。サーバーを変えずクライアントが 0/1 を解釈する案は採らない（人の決定が BOOL のため）。

### 3.2 日時の書式（`last_recovery_at`・`last_login_at`）— 確定

- **`Y-m-d H:i:s`、タイムゾーン表記なし、値は UTC。** 根拠:
  - `_BaseModel::getDates()` が空を返し、日時を Carbon に変換しない設計で、`toResponseArray()` の ISO 8601 変換（`getDates()` の項目だけが対象）に該当しない（読んだ）。
  - `last_recovery_at` は `datetime` 列に `ClockUtility::now()` の `Y-m-d H:i:s` で入る。`config('app.timezone')` は `UTC`（実行）。
  - 既存テスト（`HomeSnapshotTest`）が `last_recovery_at` を `Y-m-d H:i:s` の文字列として比較している（読んだ）。
  - `last_login_at`: モデルに更新後の日時を入れて `toResponseArray()` を通すと `"2026-11-14 02:03:19"`（実行 / in-memory。DB への書き込みなし）。title-to-home の api.md の例（`"2026-11-12T01:23:46.000000Z"`）は**実装と違う**。
- 値に「UTC」を示す印（`Z` や `+00:00`）が**付かない**ので、クライアントは「UTC として解釈する」と決めて読む。S3 でサーバーの応答を変える必要は**無い**（overview の Q8 の「S3 の範囲が広がる条件」には該当しない）。
- HTTP の login を実際に呼んで確認したわけではない（書き込みを伴うため）。確認の手順は 5 節。

## 4. 新規プレイヤーの login 応答の変化（サインアップ時の初期データ。Q9・Q15〜Q19 は決定）

人の依頼（2026-11-14）: サインアップ時に `trx_diamond` / `trx_wallet` / `trx_item` / `trx_stamina` へ 1 レコードずつ入れる。値の持ち方と確認事項は [db.md](db.md) 8 節、書き込みの流れは [sequence.md](sequence.md) 4 節。**Q15〜Q19 は 2026-11-14 に決定済み**（db.md 8.3 節）。無償ダイヤ（`trx_item` の `free_diamond`）もホームの S3 に含む（Q17）。

sign_up 直後・日跨ぎ前の最初の login の `trx_*_list` は、現行（すべて空）から次のようになる。ログインボーナスの付与分は別に足される。

| 一覧 | 現行 | サインアップ時の初期データ | 備考 |
| --- | --- | --- | --- |
| trx_stamina_list | `[]` | `normal` の 1 行: `current_stamina` = レベル 1 の `max_stamina`（`MstPlayerLevelRepository::findMaxStaminaForLevel(1)`。**level 1 のマスターが無いときは sign_up が失敗する**。現行のシードでは 50）、`recovery_rate_multiplier` `"1.00"`、`last_recovery_at` = 作成時刻 | 最大値なので login は回復を足さず、`last_recovery_at` は作成時刻のまま。クライアントは先に最大値判定をする |
| trx_wallet_list | `[]` | `coin` の 1 行: `free_amount` 1000、`paid_amount` 0 | ログインボーナスで `gold` などが足されうる |
| trx_diamond（旧 `trx_diamond_list`。D5） | `[]` | **`platform` が sign_up の `X-Platform` の値（`Apple` / `Google`）の 1 行を、オブジェクトで返す**: `paid_amount` 0、`free_amount` 0（Q16。10 節）。**`free_amount` に依存しない**（D4。db.md 8.8 節） | ログインボーナス（`free_diamond`）などの無償ダイヤは、D5 で**付与の既定 platform が `X-Platform` の値になる**ので、同じ行の `free_amount` が増える（11.3 節。従来は `Apple` 固定） |
| trx_item_list | `[]` | `free_diamond` の 1 行: `free_amount` 50、`paid_amount` 0（Q17。ホームの S3 に含む。`mst_item` に行を足す。db.md 8.5 節） | **別機能の完了まで消費できない**（`DiamondService::consumeDiamond` が `trx_item` を見ない。db.md 8.6 節）。ホームの合算表示は 50 でも、ダイヤを消費する API（ガチャなど）では残高 0 のまま |
| trx_unit_list / trx_equipment_list | `[]` | `[]` | 変更なし |

例（`X-Platform: Apple` で sign_up した場合。すべて作成直後。`is_delete` は 3 節の決定（S3 で実装）の後の真偽値。ログインボーナスの分は含めない）:

```json
{
  "trx_stamina_list": [ { "type": "normal", "current_stamina": 50, "recovery_rate_multiplier": "1.00", "last_recovery_at": "2026-11-14 02:03:19", "is_delete": false } ],
  "trx_wallet_list": [ { "mst_item_id": "coin", "free_amount": 1000, "paid_amount": 0, "is_delete": false } ],
  "trx_diamond": { "platform": "Apple", "paid_amount": 0, "free_amount": 0, "is_delete": false },
  "trx_item_list": [ { "mst_item_id": "free_diamond", "free_amount": 50, "paid_amount": 0, "is_delete": false } ]
}
```

- `sys_player`・`login_bonus_list` の形は変わらない。`login_bonus_list` は 1 日目の報酬が入る（title-to-home のとおり）。
- sign_up の応答（`sys_player`・`sys_player_device`・`sys_player_token`・`token`）は変わらない。`#[DoesNotReturnUpdatedResources]` も変えない。
- Coin の付与は nexus-wallet 経由のため、取得単位の `trx_wallet_balance` の行も 1 行できる（読んだ。`CurrencyDeliveryHandler` → `CurrencyGranterInterface`）。応答には出ない。
- 既存プレイヤーは変わらない（Q18 の決定。補填しない。login 時の補完もしない）。スタミナ行が無い状態は残るので、クライアントの「行が無い」の表示は引き続き必要。

### 影響の一覧（title-to-home・クライアント・動画）

| 対象 | 影響 |
| --- | --- |
| title-to-home の要件（`Nexus/docs/requirements/title-to-home.md`）の「新規作成時の初期付与は対象外」と Q2 | 本機能の人の決定（Q19）で上書き。更新は別 PR |
| title-to-home のサーバー設計（`docs/design/title-to-home/` の api.md・sequence.md・db.md） | api.md の「新規プレイヤーの初回 login は `trx_*_list` が空」（6 節末尾）、sequence.md の「sign_up は sys のみ書く」（1・3 節）、db.md の sign_up の書き込み先が変わる。**S3 の完了後に更新する**（このドキュメントは複製・修正しない） |
| サーバーのテスト | `AuthSignUpTest`・`AuthFlowTest`・`WalkthroughTest`・`SignUpUseCaseTest`・`HomeUseCaseTest`・`HomeSnapshotTest` など、新規プレイヤーの login 応答や sign_up の書き込みを前提にしたものを確認する（未確認。S3 で洗い出す）。`HomeSnapshotTest` の「新規は空」の記述があれば直す |
| 動画の撮影用シード（`api/database/seeders/Recording/`） | 新規プレイヤーが空の前提のシナリオがあれば影響（未確認） |
| クライアント | 新規ユーザーでホームにコイン 1000・ダイヤ 50（有償 0 ＋ 無償 50。無償は `trx_item` の `free_diamond`。暫定は P6 の `trx_diamond` の `paid_amount` + `free_amount` の合算ではなく、`trx_item_list` の行を足す必要がある。S4・S5 で調整）・スタミナ（最大値）が出る。動画の期待値は、スタミナ最大値・残り時間の表示なし（最大値）、コイン 1000、ダイヤ 50。**ただし、ダイヤを消費する操作は残高不足になる**（db.md 8.6 節）。全リクエストに `X-Platform` を付ける（10.8 節）。**login 応答の `trx_diamond_list`（配列）は `trx_diamond`（オブジェクト・null 可）に変わる**（D5。11.4 節） |
| 全 API の platform ヘッダー（Q16） | 全ゲーム API が対象。サーバーのテスト基盤・クライアントの ApiClient・動画・e2e のドライバーに影響（10.8・10.9 節）。旧版クライアントは全 API で失敗する（10.10 節。ただし未配布なので影響なし。D7）。title-to-home の api.md の共通ヘッダー表（1 節）にも `X-Platform` を足す必要がある（S3 の後に別 PR） |
| 別機能 free-diamond-item | **`trx_diamond.free_amount` の廃止（D4 決定。既存データを `trx_item` の `free_diamond` へ移して列を削除、platform 別の複数行は 1 行に合算）**、`DiamondService`・`DiamondBalance`・`DiamondDeliveryHandler`・`InAppPurchasePackService`・`trx_diamond_balance`・ログへの影響（db.md 8.8 節）。login の `trx_diamond` から `free_amount` が消え、ホームの暫定表示は `trx_item_list` への切り替えで終わる。既存の無償ダイヤの移行・付与経路・消費・課金パックも。**初期の 50 が消費できない暫定のため、先に進める必要がある**（Q26 で決定: 暫定を許容し、別機能の優先度を高くする）。platform の使い道の論点は 10.7 節 |
| 既存のスタミナの挙動 | スタミナ行ができるので、ログインボーナスのスタミナ報酬・回復アイテムが「行が無い」で黙って捨てられていた問題は、新規プレイヤーでは解消する（db.md 8.1）。最大値のときの `last_recovery_at` の扱いに既存の疑いがある（db.md 未解決点 U-G） |

## 5. Q9 の確認結果と、実行での確認手順

- **確認したこと（読んだ・実行）**: 現行の sign_up は trx を書かず、`trx_stamina` の行を作るのはレベルアップ時のリスナー `RecoverStaminaOnPlayerLevelUp::createStamina` だけ。sign_up 直後の `trx_stamina_list` は空（db.md 8.1）。開発 DB の `trx_stamina` は 0 行（実行。ただしシーダーのプレイヤー）。
- **未確認（書き込みを伴うため実行していない）**: 実際に sign_up → login を呼んだ応答。

確認の手順（S3 で、テスト DB に対して行う。開発 DB は使わない）:

1. 機能テスト（`api/tests/Feature/AuthSignUpTest.php` の流れ）で sign_up → login を呼び、`trx_stamina_list` が `[]` であることを確かめる（現行）。S3 の実装後は 4 節の表のとおりになることをアサートする。
2. 同じテストで `is_delete` の JSON 型（真偽値）、`last_recovery_at` の書式（`Y-m-d H:i:s`）と、作成時刻との一致を確かめる。
3. （各リクエストの間に `ApiSession::clearForTest()` を挟む。10.4 節。）**付与の失敗で sign_up 全体が失敗し、何も残らない**ことを確かめる。付与のサービス（`CurrencyGranterInterface`・`DiamondService`・`StaminaService`）のどれかが例外を投げるようにして（テストでモックに差し替える）、`sys_player`・`sys_player_device`・`sys_sharding_node_player` と trx の行が 1 つも残らないこと、失敗が応答に出る（200 でない）ことの両方をアサートする。**「人工的に例外を起こして 200 でない」だけの確認では、`ResourceDeliveryService` のような握りつぶしを検出できない**（握りつぶす実装は、例外をログに出して続行するため sign_up が 200 になり、行は 1 つ残る。「行が残らないこと」のアサートがその検出になる）。

## 6. マスター配信（U1・MstConfig）

### 6.1 配信の仕組み（読んだ）— 変更は不要

- `GET /auth/version`（`CheckResponse`）は、`sys_deploy` → `sys_deploy_master` → **`sys_deploy_master_table` の行ごと**に `tables[テーブル名] = {hash, url, file_size}` を返す。テーブルは行があるものがすべて載る（API 側の除外・フィルタは無い）。
- 行は運営ツール `POST /master-import/export` が作る。`MasterDataExporter::export()` は `SHOW TABLES LIKE 'mst_%'` で**全 mst テーブル**を取り、`__l10n` を親にまとめてグループごとの SQLite にし、`MasterDeployService::register()` が**全グループを `sys_deploy_master_table` に登録**する。**除外リスト・フィルタは無い**（`tool/app/Services/` を読んだ）。
- したがって **`mst_config` は、テーブルを作ってデプロイするだけで配信対象になる**（コード変更なし）。`mst_player_level` も同じ。
- SQLite の型は MySQL の型から機械的に写される（int 系→INTEGER、decimal・float 系→REAL、それ以外→TEXT）。`mst_config.value` は TEXT、`mst_player_level.level`・`max_stamina` は INTEGER。
- 配信 SQLite の公開先はクライアント設定のホスト（ADR 0001）で、サーバーは相対パス `/masterdata/{hash}/{file}` を返す（title-to-home D7）。

### 6.2 U1 の確認結果

| 確認 | 結果 | 手段 |
| --- | --- | --- |
| `mst_player_level` は配信対象になる仕組みがあるか | **ある**。全 `mst_*` が対象で、除外が無い | 読んだ（`MasterDataExporter`・`MasterDeployService`） |
| 開発 DB の `mst` に `mst_player_level` が入っているか | 入っている（100 行。`max_stamina` を含む） | 実行（読み取り） |
| 開発 DB の `sys_deploy_master_table` に `mst_player_level` の行があるか | **無い**。`sys_deploy_master_table` は **0 行**（`sys_deploy_master` は 3 行、`sys_deploy` は 3 行で `is_active` は 1 つ） | 実行（読み取り） |
| 配信ファイルがあるか | **無い**。`tool/public/masterdata/` は `.gitkeep` のみ、`api/public/masterdata/` は存在しない | 実行（`ls`） |
| 開発環境で実際にデプロイされたか | **されていない**。`sys_deploy_master` の 3 行は `SysDeploySeeder` が作った**ダミー**（`hash('sha256', 'master_v1.0.0')` など。テーブル行・ファイルを伴わない）。この状態で `GET /auth/version` を呼ぶと `master.tables` が空になる | 実行（DB）＋読んだ（`SysDeploySeeder`）。version の応答は実行していない |
| 運用環境（本番・検証環境）でデプロイ済みか | **未確認** | 人に確認を依頼（手順は 6.4） |

結論: **配信対象に入る仕組みはある。ただし開発環境では実際には配信されていない。** 「配信されていなかったら、配信されるようにする」（人の指示）に対する作業は、**サーバーのコード変更ではなく、運営ツールでのエクスポート（デプロイ）の実行**。

### 6.3 配信されない原因になりうるもの（と対処）

| 原因 | 見分け方 | 対処 |
| --- | --- | --- |
| マスターのデプロイ（エクスポート）を運営ツールで実行していない | `sys_deploy_master_table` に行が無い／配信ディレクトリにファイルが無い（**開発環境はこれ**） | 運営ツールの `POST /master-import/export` を実行する（6.4） |
| `mst_*` のテーブルが空（行が入っていない） | `mst` の件数が 0。エクスポートは空テーブルも `CREATE TABLE` だけを作って登録する（行は入らない）ので、**行が無いと配信はされるが中身が空** | 運営ツールのインポート（スプレッドシート → `mst`）またはシーダー（開発）で行を入れてからエクスポートする |
| 除外設定 | **存在しない**（読んだ） | 該当なし |
| 新しいテーブル（`mst_config`）のマイグレーション未実行 | `SHOW TABLES LIKE 'mst_config'` が空（開発 DB は現在**無い**。実行） | `make migrate`（S3 の実装後） |
| デプロイは済みだが `sys_deploy.is_active` が無い／`start_at` が未来 | `version` が `needs_update` を返さない | `register()` は最新を `is_active = true`・`start_at` = 現在で入れる。手で変えていなければ起きない |
| クライアントが読む対象に入れていない | クライアント側の `master_data` が `mst_player_level`・`mst_config` を読まない | S4・S5 の範囲（サーバーではない） |

### 6.4 運用の前提（サーバーのコード変更は不要）

1. **S3 の実装後**、`make migrate`（`mst_config` の作成）→ `make seed`（開発。`MstConfigSeeder`・`MstPlayerLevelSeeder`）で行を入れる。運用環境は運営ツールのインポート（`mst_config` のシートに**全設定の行を入れる**。db.md 3.4）。
2. 運営ツール（tool、:8091）の「マスターインポート」画面からエクスポート（`POST /master-import/export`）を実行する。`tool/public/masterdata/{hash}/` に `mst_player_level_{hash}.sqlite` と `mst_config_{hash}.sqlite` ができ、`sys_deploy_master_table` に行が登録され、新しい `sys_deploy` が有効になる。同じ内容のハッシュが既にあれば新規登録されず、既存の deploy_key が返る。
3. 確認: `sys_deploy_master_table` に `table_name` が `mst_player_level`・`mst_config` の行があり、`file_name` のファイルが配信ディレクトリにあること。`GET /auth/version` の `master.tables` に両方のキーがあること。
4. **開発環境での実行（人の許可済み。2026-11-14）**: 上の 2 のエクスポート（`POST /master-import/export`）を開発環境で行ってよい。**設計の段階では実行しない。S5 の前（クライアントの動作確認・撮影の準備）に、主担当が運用作業として行う**。実行後、3 の確認（`sys_deploy_master_table` に `mst_player_level`・`mst_config` の行、配信ファイル、`GET /auth/version` の `master.tables`）を記録する。
5. **動画撮影（S5）の前提**: `RecordingDeploySeeder` は `sys_deploy_master_table` を作らない（overview 8 節 Q12 の影響 4）。この設計では扱わず、別の Server 側タスクとする。
6. 運用環境での確認（人に依頼）: 運用の `sys_deploy_master_table` に `mst_player_level` の行があるか。無ければ 2 を実行する。

### 6.5 配信される内容がクライアントの読み取りに足りるか

| テーブル | 列（SQLite） | クライアントの用途 | 足りるか |
| --- | --- | --- | --- |
| `mst_player_level` | `deploy_key` INTEGER、`level` INTEGER（PK）、`required_exp` INTEGER、`max_stamina` INTEGER、`created_at`・`updated_at` TEXT | `sys_player.level` の行の `max_stamina`（最大スタミナ） | **足りる**（`level` と `max_stamina` があり、型も整数）。シードは 1〜100 の 100 行（実行）。**100 を超えるレベルは行が無い**（最大レベルは 100 が既定）ので、行が無いときの表示はクライアント（S4。U2） |
| `mst_config` | `deploy_key` INTEGER、`id` TEXT（PK）、`value` TEXT、`value_type` TEXT、`description` TEXT、`created_at`・`updated_at` TEXT | `stamina_recovery_interval_seconds` の行の `value`（整数に解釈）。`value_type` が `int` | **足りる**。`value` は文字列なので、クライアントが `value_type` に従って整数に変換する。変換できない・0 以下のときの扱いはクライアントの P5（回復を計算しない） |

### 6.6 版ずれ（U5）— 配信される値との整理

- クライアントが持つ `max_stamina`・回復間隔は、**最後に更新したマスターの値**。サーバーは自分の `mst` の値（Redis キャッシュ込み）で login の現在値と `last_recovery_at` を計算する（db.md 5 節）。
- `max_stamina` が変わったとき、サーバーは「最大値以上なら回復しない」の判定を新しい値で行い、クライアントは古い値で行う。ずれは表示の差で、次の login・マスター更新で直る。
- `X-Master-Update-Required`（200 応答に付く）でクライアントはマスターの更新を知れる（title-to-home）。更新後は再 login しなくても、クライアントは新しい値で計算できる。サーバーとの食い違いは、次のサーバー計算（消費・login）で解消する。

## 7. エラー・冪等・排他・更新リソース（login・sign_up の既存分は変更なし。platform ヘッダーのエラーが全ゲーム API に増える）

- **全ゲーム API（10.2 節の範囲）**: `X-Platform` の欠落・不正値は **HTTP 299 / `error_code` 99003**（仮置き。`GameErrorCode::INVALID_PLATFORM`）。`maintenance` の後・認証や署名の前に返るので、冪等キーには保存されない。詳細は 10.3 節。
- login: エラー（401 / 299(99999) / 409(40900) / 503(50300)）、冪等（`X-Unique-Request-Identifier`）、プレイヤーロック、`#[DoesNotReturnUpdatedResources]` は [title-to-home の api.md 6 節](../title-to-home/api.md) のまま。MstConfig の読み出しは、値の出どころが変わるだけで、書き込み・ロックを増やさない。MstConfig が使えないときは例外にせず既定値（db.md 4 節）なので、新しいエラーコードは**増えない**。
- **sign_up**: 初期データを足すが、エラーコード（10004 = 既存の端末、署名の 401、429）は増えない。初期データの付与は `ResourceDeliveryService` を使わず、サービスを直接呼ぶ（例外を握りつぶさないため。db.md 8.7。仮置き）。付与の失敗（level 1 の `mst_player_level` が無いときを含む）は例外が `executeWithTransaction` まで届き、全体がロールバックされる。HTTP ステータスは `_BaseController::determineStatusCode` による: 例外の `getCode()` が 100〜599 ならその値、0 や文字列は 500（読んだ。実行は未確認）。sign_up は冪等ミドルウェアの対象外（未認証）で、二重送信の防止は端末の一意性（`sys_player_device`）と端末ごとのレート制限（title-to-home）。このため、**初期データの二重付与は起きない**（2 回目は 10004 で、書き込みなしでロールバック）。
- 更新リソース（`updated_resources`。`docs/api_resources.md` の形式）: login は全量（現在値）を返す API で、`updated_resources` は使わない。**`docs/api_resources.md` の表は変更しない**（login は「現在値（全量）」、sign_up は「なし（後続の login で取得）」のまま）。ただし `is_delete` を真偽値にする変更（3.1 節）は、`updated_resources` の各行にも効くので、`docs/api_resources.md` に一文足す（S3）。
- `GET /auth/version`・配信ファイル: 変更なし（6 節）。

## 8. 回復計算の契約（login の中の変更）

login の応答を作る `HomeUseCase` → `StaminaService::findAllStaminas`（`nexus-stamina`）が使う回復間隔の出どころが、private 定数から `StaminaRecoveryIntervalProviderInterface`（MstConfig）に変わる（db.md 2 節）。応答の形・型は 2.3 節のまま。次は変わらない。

- 回復点数 = `floor(経過秒 × recovery_rate_multiplier ÷ 回復間隔)`、`current_stamina` = `min(current_stamina + 回復点数, 最大値)`（最大値以上なら回復しない）、`last_recovery_at` = 元の時刻 + `floor(回復点数 × 回復間隔 ÷ 倍率)` 秒。
- DB には保存しない（保存するのは消費・回復アイテム・レベルアップのとき。これらも同じ回復間隔を使う）。

## 9. 未解決点

| # | 内容 | 扱い |
| --- | --- | --- |
| U-1 | `is_delete` を真偽値にする `toResponseArray()` の変更（3.1 節。Q8 決定）が、他の API・クライアント機能・テストに影響しないか | **S3 の作業として洗い出す**（0/1 を前提にしているテスト・クライアントの有無） |
| U-2 | login を実際に HTTP で呼んだ応答（`is_delete`・`last_recovery_at` の実測、スタミナ行を含む） | 未確認（書き込みを伴うため）。5 節の手順で S3 のテストで確かめる |
| U-3 | 運用環境のマスターデプロイの状況 | 人に確認を依頼（6.4 の 6） |
| U-4 | （**閉じた**）初期付与はパッケージ版 `ItemService::addItem` を直接呼ぶ。`mst_item` の存在は検査されない（読んだ）。運営のシートへの `free_diamond` の追加が S3 の前提作業（db.md 8.5 節） | Q17 は決定済み。`mst_item` の行はシーダーとテストで担保 |
| U-5 | 新規プレイヤーに依存するテスト・撮影用シード | S3 で洗い出す（4 節） |
| U-6 | `MstItemSeeder` が `is_wallet` を書かない（開発 DB の `mst_item` 27 行すべて `is_wallet = 0`）。ホームの Coin 表示には影響しないが、`is_wallet` で振り分ける経路に影響 | db.md U-H。**S3 の必須作業** |
| Q20 | platform ヘッダー名 | **決定** `X-Platform`（10.1 節） |
| Q21 | 必須にする範囲 | **決定** `maintenance` のグループ内のゲーム API 全部。version・メンテ状態・`admin/*`・broadcasting・`/up` は除外（10.2 節） |
| Q22 | 旧版クライアントの互換 | **決定** 互換なし（S3 と同時に必須化）。**旧クライアントは未配布（人の回答 2026-11-14）なので、配布状況の確認（S3 の前提作業）は解消済み。2 段階案は不要（D7）** |
| Q23 | 署名にヘッダーを含めるか | **決定** 含めない。platform をセキュリティ判断に使わない（10.6 節） |
| Q24 | 欠落・不正値のエラー | **決定** HTTP 299 / 99003（`INVALID_PLATFORM`）。ミドルウェアが直接返す（10.3 節） |
| Q25 | `free_diamond` の `mst_item` の列 | **決定** `type = currency`、`effect = none`、`value = 0`、`is_wallet = false`（db.md 8.5 節） |
| Q26 | 初期の無償ダイヤが別機能の完了まで消費できない暫定の許容と、別機能を急ぐこと | **決定** 許容し、free-diamond-item を先に進める（台帳向けの論点: 別機能の優先度を高くする。db.md 8.6 節） |
| D5 | **login のダイヤ（有償・暫定の無償）は、`trx_diamond_list` ではなく、`X-Platform` の platform の 1 行を `trx_diamond`（オブジェクト）で返す**（人の決定 2026-11-14。複数にならないため）。付与の既定 platform も `X-Platform` に変える | **11 節**。行が無いときの `null` は仮置き（U-7） |
| D7 | **旧クライアントは未配布なので、互換なしで login の `trx_diamond` 化と `X-Platform` の必須化を行う**（人の決定 2026-11-14。S3 と同時でよい。Q22 の「配布状況の確認」の前提作業は解消済み） | 10.10・11.1 節 |
| U-7 | `trx_diamond` が無いときの返し方（`null` か、0 の行を作って返すか）。仮置きは `null` | 人に確認。サインアップ時に行を作るので、新規プレイヤーでは起きない。既存プレイヤー（Q18 で補填しない）と、platform を変えた端末（Apple で作ったアカウントを Google の端末で使う）で起きる |
| D6 | **U-8 の決定（人の回答 2026-11-14）: 「無償ダイヤは Platform を跨いだ共通管理（trx_item）なので、どちらからでも見れます」。** (1) 最終形: 無償ダイヤは `trx_item` の `free_diamond`（platform 共通）で、どの platform の端末からも見える。有償ダイヤだけが platform 別（`trx_diamond` の端末の platform の 1 行。他 platform の有償は見えない・使えないのが仕様）。(2) 暫定（別機能 free-diamond-item の移行前）: 過去に platform 別の `trx_diamond.free_amount` に入った無償ダイヤは、他 platform の端末では見えない。移行（`trx_item` へ合算して列を削除）で解消するので許容する。新規の付与は D5 で端末の platform の行に入るので、移行前でも見える。見えない分は `consumeDiamond`（全 platform 合算。変えない）では使われる。 | 11.3 節・db.md 8.6・8.8 節 |
| U-9 | `updated_resources` の `trx_diamond_list`（`docs/api_resources.md`。login 以外の API の応答）は変えるか | 決定の対象は login のみ。**変えない前提**（人に確認）。変えるなら別 PR で `api_resources.md` ごと |
| 決定済み | D4（`trx_diamond.free_amount` は廃止。別機能の範囲。ホームの S3 は依存しない。db.md 8.8 節）・Q15（値は API の定数）・Q17（`free_diamond` はホームの S3）・Q18（補填なし）・Q19（title-to-home の初期付与の上書き） | [db.md 8.3 節](db.md) |

## 10. 全 API 共通の契約変更: platform ヘッダー（Q16。人の決定）

人の決定（2026-11-14）: 「sign_up に限らず、HTTP ヘッダーで必ず platform を渡す」。**全 API 共通の契約変更**で、S3 の範囲が広がる。ヘッダー名・範囲・エラー・旧版互換・署名は**決定済み**（9 節の Q20〜Q24。2026-11-14）。旧版クライアントは未配布で互換不要（D7。10.10 節）。「読んだ」は `api/bootstrap/app.php`・`api/routes/api.php`・`VerifyClientSignature`・`ResolveLanguage`・`GameErrorCode`・`docs/client_authentication.md`。

### 10.1 ヘッダー名と許可値

| 項目 | 決定 | 理由 |
| --- | --- | --- |
| ヘッダー名 | **`X-Platform`** | 既存は `X-Unique-Request-Identifier`（冪等）、`X-Master-Hash`（マスター）、`X-Client-Timestamp` / `X-Client-Nonce` / `X-Client-Signature`（sign_up の署名）。`X-Client-*` は署名の組なので、署名と無関係な platform は `X-Platform` にして混ぜない |
| 許可値 | **`Apple` または `Google`**（完全一致・大文字小文字を区別・前後の空白なし） | `trx_diamond.platform` の値（`Apple` / `Google`）と、課金の `BuyRequest` の `platform`（`in:Apple,Google`）に揃える。小文字（`apple`）や `iOS` / `Android` は不正。大小を許すと、DB に入る値が `Apple` と `apple` に割れる |
| 他の OS・環境（Windows、エディタ、ブラウザなど） | 許可しない（不正値） | 開発のエディタ・デスクトップは、クライアントの設定で `Apple` か `Google` のどちらかを送る（クライアント側の判断。S4） |
| 既存の大文字小文字の割れ | `api/app/Models/Log/LogInAppPurchase.php`（60〜62 行）に小文字の `'apple'` / `'google'` の定数がある（ログ側。`trx_diamond.platform` と課金の `BuyRequest` は `Apple` / `Google`） | **本機能では変えない**。ヘッダーの許可値は `Apple` / `Google` のまま。大小の割れが既にログ側にあることを、別機能（課金・free-diamond-item）の論点として残す |
| 値の定義 | 1 か所の定数（`Apple`・`Google`。`App\Constants\Platform` など。名前は仮置き） | 既存は課金側の `BuyRequest` の `in:` と `DiamondDeliveryHandler` の `'Apple'` に分散している。定義の統一（既存コードの置き換え）は本機能では**しない**（影響は 10.7 節） |

### 10.2 必須にする範囲と、検証するミドルウェア

- **ゲーム API 全部で必須**とする。具体的には、`routes/api.php` の `Route::middleware('maintenance')->group(...)` の中のすべて（sign_up・sign_in・refresh_token・account の引き継ぎ・login・公開 GET を含む認証付き API）。このグループを `Route::middleware(['maintenance', 'platform'])->group(...)` にする。
- **例外（必須にしない）**: `GET /auth/version`、`GET /maintenance/status`、`admin/*`（運営用）、`/broadcasting/auth`、`/up`。いずれも `maintenance` のグループの外にある（読んだ）。version・メンテナンス状態は起動直後・メンテ中でも叩かれ、platform に依存しないので除外する。クライアントがこれらにも付けるのは自由。
- **注意: `POST /auth/version`（認証済み）は `maintenance` のグループ内なので `X-Platform` が必須**（除外されるのは `GET /auth/version` だけ）。
- **新しいミドルウェア `ResolvePlatform`（エイリアス `platform`。名前は仮置き）** を、`bootstrap/app.php` のエイリアスに足す。順序は `maintenance` → `platform` → （`client.signature` / `throttle.*` / `auth.token` / `idempotency` / `player.lock`）。理由: メンテ中は 503 を先に返す。署名やトークンの検証より前に platform を `ApiSession` に載せるので、未認証の sign_up でも使える。`ResolveLanguage` を `api` グループの先頭に足している既存の方式（全 API に言語を解決）と同様に見えるが、**platform は除外ルートがある**ので、`api` グループ全体ではなく `maintenance` のグループに付ける。
- `CheckMasterHash`（`api` グループの先頭）は応答の後処理で、200 以外は何もしないので、platform エラー（299）には付かない。

### 10.3 欠落・不正値のエラー

- **HTTP 299 + `{error_code, message}`**（業務エラーの形。`ErrorResponse::businessError`）。**既存のミドルウェアは `ErrorResponse` も `error_code` も使わない**: `VerifyClientSignature` は 401、`CheckMaintenance` は 503 で、素の `error` / `message` の JSON を返す（読んだ）。**`ResolvePlatform` は、ミドルウェアから `ErrorResponse::businessError`（299 + `error_code`）を返す最初の例になる**。`error_code` は **`GameErrorCode` の「アプリケーション汎用エラー（99000 番台）」に新しい定数を足す**（仮置き: `INVALID_PLATFORM = 99003`。99003 は空きと確認済み）。既存の `INVALID_PARAMETER`（99001）を使う案もあるが、クライアントが「ヘッダーの付け忘れ（実装の誤り）」を他の入力エラーと区別できるよう専用にする。
- ミドルウェアは `GameException` を投げず、**応答を直接返す**（`ErrorResponse::businessError(...)->toJsonResponse()`）。`GameException` を投げると、`bootstrap/app.php` の handler が Slack に通知するため、クライアントの実装の誤りで通知が溢れる恐れがある。
- **`maskForProduction()`**: `bootstrap/app.php` の例外ハンドラは `->maskForProduction()` を付けて本番でメッセージを隠す。ミドルウェアで返すときは**付けない**（既定案。仮置き）。欠落と不正値を区別できるメッセージ（「`X-Platform` is required」「`X-Platform` must be one of: Apple, Google」）を、本番でもクライアントの実装者が読めるように返す（ヘッダーの付け忘れは利用者の入力ではなく実装の誤りで、メッセージに内部情報が含まれないため）。
- 欠落と不正値は同じコード・同じ形式（メッセージで区別: 「`X-Platform` is required」「`X-Platform` must be one of: Apple, Google」）。
- 冪等ミドルウェアより外側で返るので、冪等キーでは**保存されない**（再送すれば、ヘッダーを直せば通る）。`ErrorResponse` の 401 / 429 / 503 の扱い（title-to-home の api.md）はそのまま。
- クライアントの分類: HTTP 299 の業務エラーとして扱う（title-to-home D5）。リトライしても直らない。

### 10.4 値の保持（`ApiSession`）

- `ApiSession` の既存の形に合わせる。`language` は **instance の set / get と static ヘルパーの組**なので、platform も組で設計する: instance の `setPlatformInstance(string)` / `getPlatformValue(): string`、static の `ApiSession::setPlatform(string)` / `ApiSession::getPlatform(): string`（名前は既存の `setPlayerIdInstance` / `getPlayerId` の流儀に合わせて S3 で確定）。`platform`（`?string`）を持たせる。**設定済みかを調べる `ApiSession::hasPlatform(): bool`（static。既存の `hasSysPlayerId()`・`hasNow()` と同じ形）も足す**（D5。11.3 節。例外を使わず安全に取るため）。**未設定のとき get は `RuntimeException`**（`getPlayerId()` と同じ。キュー・コマンドなど HTTP 以外の経路で暗黙の既定値を使わせない）。
- `ResolvePlatform` が、検証した値を `ApiSession::setPlatform()` で載せる。UseCase・Service は `ApiSession::getPlatform()` で読む。sign_up は未認証で `setSysPlayerId` を呼べない（呼ぶのは初期付与の前に UseCase が行う。db.md 8.7 節）が、platform はミドルウェアが認証より前に載せるので、sign_up でも読める。
- **`clear()` の対象に platform を足す**（`clearForTest()` だけでなく、`ApiSession` が持つ状態をリセットする既存の `clear()` 系の両方。S3 で `ApiSession` を読んで確定）。
- **注意（同一プロセスで状態が残る）**: 除外ルート（`GET /auth/version` など）は `setPlatform` を呼ばないので、**同一プロセスで複数リクエストを処理する場合（テスト）、前のリクエストの platform が残る**。`setSysPlayerId` も `connectionName` のキャッシュをリセットしない。したがって、**S3 のテスト（5 節の手順を含む）では各リクエストの間に `ApiSession::clearForTest()` を挟む**。`api-queue` は常駐プロセスで `ApiSession` が残るが、HTTP 以外の経路は `getPlatform()` を呼ばない（呼べば `RuntimeException`）設計なので影響しない。**D5 で `DiamondDeliveryHandler` が `hasPlatform()` を呼ぶようになるが、これも HTTP 以外では `false` を返すだけで、`getPlatform()` は呼ばない**（11.3 節）。ただし**同期で実行されるジョブ（`sync` キュー・テスト）は HTTP リクエストと同じプロセスなので platform が残っている**（11.3 節の注意）。

### 10.5 この機能での使い道

- **sign_up の初期 `trx_diamond` の行の `platform`**（paid 0・free 0）。固定の `Apple` は使わない。
- **login 応答の `trx_diamond` の行の選択**（D5。11.2 節）と、**`DiamondDeliveryHandler` の付与の既定 platform**（D5。11.3 節）。
- これ以外は**この機能では使わない**（10.7 節）。

### 10.6 署名（HMAC）との関係

- sign_up の署名は `HMAC-SHA256("{timestamp}:{nonce}:{body}", CLIENT_SECRET)`。**ヘッダー（platform を含む）は署名の対象ではない**（`VerifyClientSignature`・`docs/client_authentication.md`。読んだ）。
- **決定（Q23）: 署名に含めない**（署名の仕様を変えない。クライアント・サーバー・ドキュメントの変更が要らない）。platform は**セキュリティの判断に使わない**ことを条件にする。
- 含めない場合の改ざんの影響: 悪意のある利用者が platform を偽ると、自分のアカウントの `trx_diamond` の 0 行の `platform` が `Apple` / `Google` のどちらかになるだけ（他人には影響しない。他の API は Bearer 認証でプレイヤーが特定される）。**platform を使った認可・課金の検証・不正対策をしない**こと（課金の正当性はレシート検証。`BuyRequest` は body の `platform` と `billing_platform` を持つ）。
- 含める場合: sign_up の署名文字列に platform を足す変更で、クライアントの署名実装・`client_authentication.md`・テストを変える（採らない案）。

### 10.7 影響と、この機能で変えないもの（別機能の論点）

| 対象 | 現行 | この機能での扱い | 別機能の論点 |
| --- | --- | --- | --- |
| `DiamondDeliveryHandler` の `metadata['platform']` | 無指定は `'Apple'` | **変える（D5）**: 無指定の既定を **`ApiSession` の platform（`X-Platform`）** にする。platform の無い経路（ジョブ・バッチ・CLI）は従来どおり `Apple`。詳細は 11.3 節。これで **`X-Platform: Google` の人のログインボーナスなどの無償ダイヤは Google の行に入り**、login の `trx_diamond`（自分の行だけ）で見える。**既存データ（`Apple` の行に入っている無償ダイヤ）は変えない**（Q18 の補填なしと同様。別機能 free-diamond-item の移行で `trx_item` に合算される） | 別機能 free-diamond-item では、無償の付与先が `trx_item` に変わるので、この既定 platform の分岐は無償側では不要になる（有償は `metadata['platform']` のまま） |
| `DiamondService::consumeDiamond` | 全 platform の行を合算して消費（platform を見ない）。有償ダイヤが他 platform の行からも引ける | **変えない**（D4 の `free_amount` 廃止もこの別機能で行う） | 有償は platform をまたがない（人の仕様）。消費の対象行を `ApiSession::getPlatform()` で絞るか。**free-diamond-item**（overview 8 節 D1〜D3 の影響 3）。**無償ダイヤ（`trx_item`）が消費できない暫定**（db.md 8.6 節）もここ |
| `InAppPurchase` の `BuyRequest` の body `platform`（必須・`Apple` / `Google`） | 購入ごとに body で渡す | **変えない** | ヘッダー `X-Platform` と body `platform` の**重複・不一致**（不一致を拒否するか、ヘッダーを正とするか、body の項目をやめるか）は、課金・free-diamond-item の設計で決める。本機能では両方を受け付けるだけ |
| login の `trx_diamond_list` | platform 別の全行を返す | **変える（D5）**: `X-Platform` の platform の 1 行だけを `trx_diamond`（オブジェクト）で返す。11 節。**旧版（Q14 の「クライアントが自分の platform の行を選ぶ」）の案は、D5 で置き換わる** | U7（クライアントが platform をどう得るか）は、`X-Platform` を付けるので不要になった。クライアントは返った 1 行をそのまま使う |
| ユニット・アイテム・装備・ガチャなど他の API | platform を使わない | 変えない（ヘッダーは必須になるが、値は使わない） | |
| 運営ツール（`tool/`）・`admin/*` | | ヘッダー不要（`maintenance` のグループの外） | |

### 10.8 クライアントへの影響

- ApiClient の**全リクエストに `X-Platform` を付ける**（version・メンテ状態にも付けてよい）。値は自分の OS から決める: iOS → `Apple`、Android → `Google`。エディタ・デスクトップなど他の環境は、設定で `Apple` か `Google` のどちらかを選ぶ（S4 で決める）。
- 付け忘れると、ゲーム API は 299 / 99003 になる。これは**旧版クライアントが全 API で失敗する**ことを意味するが、旧版は未配布なので影響しない（D7。10.10 節）。
- 動画の撮影・e2e のドライバー（クライアント側の自動操作）が直接 HTTP を叩く場合は、ヘッダーを付ける必要がある（クライアントの ApiClient を通すなら不要。未確認）。

### 10.9 テスト・シード・e2e への影響

- **サーバーの機能テスト**: `api/tests/TestCase.php` には `authHeaders()`（`Authorization` だけ）しか無く、`postJson` / `getJson` の既定ヘッダーの仕組みは無い。`/api/...` を直接叩くテストは **29 本以上**（読んだ。正確な数は S3 で洗い出す）。**`setUp()` で `withHeader('X-Platform', 'Apple')` を既定にし、除外ルート・欠落・不正値のテストだけ外す**。
- 新規テスト: `ResolvePlatform` の単体（欠落・`apple`・`iOS`・空文字・正常 2 値・除外ルートはヘッダー無しで通る・**`POST /auth/version`（認証済み）はヘッダー必須、`GET /auth/version` だけ不要**・メンテ中は 503 が先）、sign_up で `X-Platform` の値が `trx_diamond` の `platform` に入ること。
- 動画の撮影用シード（`api/database/seeders/Recording/`）は DB への投入で HTTP を使わない（読んだ）ので影響しない。シードを HTTP で叩くスクリプトがあれば影響（未確認）。
- 既存の署名テスト（`VerifyClientSignatureSecretTest` など）が、`platform` を通した経路を叩くなら、ヘッダーを足す。

### 10.10 旧版クライアントとの互換（Q22 決定。旧版は未配布。D7）

- **決定（Q22・D7）: 必須化は S3 と同時で、旧版の考慮はしない**（ヘッダー無しを許さない）。**旧クライアントは未配布（人の回答 2026-11-14）なので、配布状況の確認（S3 の前提作業）は解消済み**。クライアントも本機能の S5 で `X-Platform` を付けるため、サーバーとクライアントを同時に出す。
- 配布済みの旧版がある場合の 2 段階案（①ヘッダー無しを許しフォールバック、②全更新後に必須化）は、**旧版が未配布のため不要**（参考として残す）。
- 旧版が配布されていないので、フォールバックは不要で、最初から必須にする。

### 10.11 U7（クライアントが platform をどう得るか）

overview の U7 は、クライアントが自分の OS を知っているので、`X-Platform` を付ける実装で**解消する見込み**（S4 で確定）。D5（11 節）でサーバーが自分の platform の 1 行を返すようになったので、クライアントが行を選ぶ必要もなくなった。課金の `BuyRequest` の `platform` と同じ値を使える。


## 11. login のダイヤを `trx_diamond`（1 件）にする（D5。人の決定 2026-11-14）と、付与の既定 platform

人の決定（2026-11-14）: 「login の `trx_diamond_list` は `trx_diamond` になるのでは？ X-Platform で 1 platform 分（1 レコード）しか返さないので複数にならないから」。**login 応答のダイヤ（有償・暫定の無償）は、一覧ではなく、`X-Platform` の platform の 1 行を `trx_diamond`（オブジェクト）で返す**。S3 の範囲に入る。「読んだ」は `HomeUseCase`・`HomeResponse`・`TrxDiamondRepository`・`_BaseTrxRepository::queryOrMemory`・`DiamondDeliveryHandler`・`StaminaGranterInterface`・`StaminaGranterAdapter`・`AppServiceProvider`・`ApiSession`。

### 11.1 変更点（title-to-home の api.md との差）

| | title-to-home の api.md（6 節の Response）・現行 | 本機能（D5） |
| --- | --- | --- |
| キー | `trx_diamond_list`（array。platform ごとの全行。空は `[]`） | **`trx_diamond`（object / null）**。`X-Platform` の platform の 1 行。行が無いとき `null`（仮置き。U-7） |
| 中身 | `platform`・`paid_amount`・`free_amount`・`is_delete` | 同じ（1 行の中身は変えない） |
| 他の `trx_*_list` | | 変更なし（配列のまま） |

- **応答のキーが変わる破壊的変更**。title-to-home の api.md の Response 表（`trx_diamond_list` の行と、例の `"trx_diamond_list": []`）は、**S3 の完了後に更新する**（4 節の影響一覧と同じ扱い。このドキュメントは複製・修正しない）。
- **旧クライアントの扱い**: **旧クライアントは未配布のため互換不要（決定 2026-11-14。D7）**。X-Platform の必須化と同じく S3 と同時に変えてよい。キーを併記（`trx_diamond_list` も返す）することも、2 段階にすることもしない。
- クライアントへの影響（S4・S5）: `trx_diamond_list` を読む箇所を `trx_diamond` に変える（オブジェクト。`null` は有償 0・無償 0）。「自分の platform の行を選ぶ」処理（overview の Q14）は不要。**`TRX_LIST_KEYS`（`contexts/login/data/api/login_response.gd:18`、`contexts/player/domain/entities/player_state.gd:21`、`.claude/api.md:100`）から `trx_diamond_list` を外し、`trx_diamond` を別扱いにする**。login は `trx_diamond`（object）、他 API の `updated_resources` は `trx_diamond_list`（配列）と、**キーと型が割れる**。`updated_resources` を PlayerState に取り込む将来機能では両形式に注意。ホームの暫定表示（P6）は、`trx_diamond` の `paid_amount` + `free_amount`（その platform の行の分だけ）＋ `trx_item_list` の `free_diamond`。

### 11.2 `HomeResponse` の組み立て

- 現行の `HomeUseCase` は `$trxDiamonds = $this->trxDiamondRepository->queryOrMemory()`（そのプレイヤーの全行）を `HomeResponse::trxDiamonds`（`array<int, TrxDiamond>`）に渡し、`toArray()` が `trx_diamond_list` に写す（読んだ）。
- **変更**: `HomeUseCase` で、`queryOrMemory()` の結果から `platform === ApiSession::getPlatform()` の 1 行を選び、`HomeResponse` には `?TrxDiamond $trxDiamond` を渡す（コンストラクタの引数を `trxDiamonds` から `trxDiamond` に変える）。`toArray()` は `'trx_diamond' => $this->trxDiamond?->toResponseArray()`（行が無ければ `null`）。
- **推奨: 全行を取得してから、取得後にフィルタする（DB 側では絞らない）**。理由:
  - `queryOrMemory()` は、リクエスト内で最初の呼び出しに DB から読み、同じプレイヤーの結果を保持する（`_BaseTrxRepository`。読んだ）。同じリクエストのログインボーナスの付与が積んだ未保存の行（メモリのキュー）も、同じ経路で見える。DB 側で絞る `selectByPlatform` は、キューを先に見てから DB に問い合わせる実装（`TrxDiamondRepository::selectByPlatform`。読んだ）で、**行が無いときに余分な SELECT が 1 回増える**。
  - プレイヤーの行は最大 2 行（Apple・Google）なので、全行取得のコストは問題にならない。
  - 他の `trx_*` と同じ `queryOrMemory()` の経路のままで済む（Repository に新しいメソッドを足さない）。
- `ApiSession::getPlatform()` は HTTP の login では必ず値がある（`ResolvePlatform` が載せる。10.4 節）。論理削除の行は `queryOrMemory()` が既に除く（現行のまま）。

### 11.3 付与の既定 platform（`DiamondDeliveryHandler`。新規の設計）

**なぜ変えるか**: login が端末の platform の行だけを返すので、現行のように無償ダイヤの付与が `Apple` 固定のままだと、Google の端末のユーザーのログインボーナスなどの無償ダイヤが `Apple` の行に入り、**見えなくなる**。付与の既定を `X-Platform` の値にして、自分の行に入れる。

**現行**（`packages/nexus-core/src/ResourceDelivery/Handlers/DiamondDeliveryHandler.php`。読んだ）: `$platform = $metadata['platform'] ?? 'Apple'`。

**変更後の決め方（優先順）**: (1) `metadata['platform']` があればそれ（従来どおり）。(2) 無ければ、現在の処理の platform の供給元（下記のインターフェース）が値を返せばそれ。**API のリクエスト内では `ApiSession` の platform（`X-Platform`）で、常に値がある**。(3) どちらも無ければ `Apple`（従来どおり。ジョブ・バッチ・CLI など platform の無い経路）。

**ApiSession の値の渡し方（packages が API の `ApiSession` に依存しない）**: 既存の `ResourceDelivery` の流儀に揃える。packages の `Contracts/` にインターフェースを置き、実装は API の Application 層の Adapter、`AppServiceProvider` で `bind`（`StaminaGranterInterface` ↔ `StaminaGranterAdapter`。読んだ）。

| 部品 | 置き場所 | 内容 |
| --- | --- | --- |
| `DefaultPlatformProviderInterface`（名前は S3 で確定） | `packages/nexus-core/src/ResourceDelivery/Contracts/` | `findCurrentPlatform(): ?string`。現在の処理に platform の文脈があればその値、無ければ `null`。**例外を投げない**契約 |
| `ApiSessionPlatformProvider`（名前・置き場所は仮置き） | S3 で `api/app/Adapters/`（既存例 `Adapters/Billing/DiamondBalanceAdapter.php`）か `Domain/Diamond/…` のどちらかに確定（参考: `StaminaGranterAdapter` は `api/app/Domain/Stamina/Services/`） | `ApiSession::hasPlatform() ? ApiSession::getPlatform() : null` |
| `ApiSession::hasPlatform()` | `api/app/Persistence/ApiSession.php` | static。既存の `hasSysPlayerId()`・`hasNow()` と同じ形（`ApiSession` が bind されていなければ `false`。10.4 節） |
| `DiamondDeliveryHandler` | packages | コンストラクタに `?DefaultPlatformProviderInterface $defaultPlatformProvider = null` を足す。`$metadata['platform'] ?? $this->defaultPlatformProvider?->findCurrentPlatform() ?? 'Apple'` |
| bind | `AppServiceProvider` | `DefaultPlatformProviderInterface::class` → `ApiSessionPlatformProvider::class`（`StaminaGranterInterface` の bind の隣） |

- **standalone テスト**: packages のテストは、フェイクの provider（値を返すだけの内部クラス。`StaminaDeliveryHandlerTest` の `RecordingStaminaGranter` と同じ流儀）か `null`（未指定）で `DiamondDeliveryHandler` を組み立てられる。`ApiSession`・Laravel のコンテナ・DB に依存しない。引数を省略可能にするのは、Laravel を使わない利用で従来どおり `Apple` にするため。
- **安全に取る形**: `getPlatform()` は未設定で `RuntimeException`（10.4 節）。Adapter が `hasPlatform()` で確かめてから読み、未設定なら `null` を返す。**ハンドラが `getPlatform()` を直接呼ぶ設計にはしない**（ジョブ・CLI で例外になるため）。
- **注意（ジョブ・同期実行）**: `AppServiceProvider.php:413` で `ApiSession` は `scoped` なので、キューワーカー（`queue:work`）ではジョブごとに解放され、`ResolvePlatform` も走らないので `hasPlatform()` は `false`（`Apple`）。platform が残るのは、HTTP リクエストと同じスコープで走る同期ジョブ（`sync` キュー・テスト）だけで、そのリクエストの `X-Platform` を使う。S3 の確認事項は「`clearForTest()` と scoped の解放で platform が次に漏れないこと」の 1 点。現行の `ShouldQueue` のジョブ（`SendNotificationJob` など）は `DiamondDeliveryHandler` を通らないので、**「ジョブ経路は `Apple` にフォールバック」は将来の経路に対する安全側の設計**である。
- **影響範囲**: `DiamondDeliveryHandler` を通る付与のうち、metadata に platform が無いものの既定が変わる（`AppServiceProvider` で `ResourceDeliveryService` に登録。読んだ）。メール（受け取り）・ログインボーナス・ミッション報酬など、`Resource::diamond()` / `Resource::paidDiamond()` で配送する経路（網羅は S3 で `grep` して洗い出す）。API のリクエスト内ではどれも `X-Platform` の行に入る。**変えないもの**: `InAppPurchase` の `BuyRequest` の body `platform`（課金は購入ごとに body で渡し、`DiamondService` を直接呼ぶので `DiamondDeliveryHandler` を通らない。ヘッダーと body の重複・不一致の論点は 10.7 節のまま別機能）、`DiamondService::addDiamond` の引数、`consumeDiamond`（全 platform の行を合算して消費。db.md 8.8 節）。
- これで **Google のユーザーの無償ダイヤも自分の行に入る**。**既存データ（`Apple` の行に入っている無償ダイヤ）は変えない**（Q18 の補填なしと同様。別機能 free-diamond-item の移行で `trx_item` の `free_diamond` に合算される）。
- **別機能の完了までの見え方の差**: login は端末の platform の 1 行の `free_amount` だけを返す。別 platform の行に残る過去の無償ダイヤは login の応答に**見えない**が、`consumeDiamond` は全 platform の行を合算して消費する（変えない）ので、**見えない残高も消費に使われる**。移行で解消する（db.md 8.8 節）。

### 11.4 S3 の実装の範囲（D5 の追加分）

- `HomeUseCase`・`HomeResponse` の組み立てと、login の応答の `trx_diamond`（11.2 節）。
- `ApiSession::hasPlatform()`、`DiamondDeliveryHandler` の既定 platform、`DefaultPlatformProviderInterface`・Adapter・bind（11.3 節）。
- テスト（追加）:
  - login の応答の `trx_diamond` が `X-Platform` の行だけであること（Apple・Google の 2 行があるプレイヤーで、Apple のヘッダーなら Apple の行、Google のヘッダーなら Google の行）。その platform の行が無いとき `null`。キー `trx_diamond_list` が無いこと。
  - **`X-Platform: Google` で、metadata に platform の無い無償ダイヤが付与されると Google の行に入る**（ログインボーナスまたはメールの受け取り）。`Apple` なら Apple の行。metadata の `platform` があればそれが優先。
  - **`X-Platform: Google` で、Apple の行だけがある状態からログインボーナスの無償ダイヤを付与すると、Google の行が新規作成され、同じ login 応答の `trx_diamond` に載ること**。未保存の新規行の `is_delete` が JSON の真偽値になるかを、`TrxDiamond::$casts` と `toResponseArray` で実測する項目を含める（3 節の `is_delete` の決定との整合）。
  - packages/nexus-core の standalone テストで、`new DiamondDeliveryHandler($service)`（1 引数）が通り、従来どおり `Apple` になること。
  - ジョブ・CLI 相当（`ApiSession` に platform が無い）は **`Apple` にフォールバック**する。packages 側は、フェイクの provider が `null` / `'Google'` を返す単体テスト（standalone）。
  - `X-Platform: Google` で sign_up した新規プレイヤーの login が `trx_diamond` に `Google` の行を返し、ログインボーナスの無償ダイヤが同じ行に入ること。

### 11.5 影響を受ける既存のテスト・クライアント

- サーバーのテスト: **login 側で `trx_diamond_list` をアサートするテストは無い**（`HomeSnapshotTest`・`AuthFlowTest`・`HomeUseCaseTest` は該当なし。`HomeResponse` の `trxDiamonds` 引数を使うのは `HomeUseCase` だけ）。`trx_diamond_list` をアサートしているのは `updated_resources` を見る `api/tests/Feature/Trade/TradeEndpointTest.php:121`・`Mailbox/MailboxReceiveContentTest.php:209`・`InAppPurchase/BuyEndpointTest.php:82` で、U-9 の「`updated_resources` は変えない」前提なので壊れない。`DiamondDeliveryHandler` が `Apple` の行に入ることを前提にしたテスト（`X-Platform: Apple` を既定にする 10.9 節の変更で、Apple ならそのまま通る見込み。S3 で確認）。
- クライアント（Godot）: login の応答のパース（`trx_diamond_list` を読む箇所）。S4・S5 で変更。動画・e2e のドライバーで読むものがあれば影響（未確認）。
- 別 PR で直すもの（このドキュメントでは直さない）: title-to-home の api.md、`docs/api_resources.md`（login の記述。U-9）、Nexus の要件・設計（`overview.md` の P6・Q14 の「クライアントが自分の platform の行を選ぶ」）。
