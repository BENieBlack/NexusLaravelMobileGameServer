# API ごとの、プレイヤーの状態（リソース）の返し方

クライアントが画面の状態を更新するための契約。対象は `ReturnedAsUpdatedResource` を実装した7つのモデルで、
`sys_player`（オブジェクト）、`trx_item_list`、`trx_unit_list`、`trx_equipment_list`、`trx_wallet_list`、`trx_diamond`（オブジェクト。`X-Platform` の行 1 件）、`trx_stamina_list`。
有償ダイヤは `trx_diamond`、無償ダイヤは `trx_item_list` の `free_diamond`（`free_amount` が残高）で返る。
増減は `api/tests/Architecture/UpdatedResourceModelsTest.php` の `EXPECTED` で固定している。

## 返し方は3種類

| 種類 | 内容 | 該当 |
|---|---|---|
| 現在値（全量） | 7テーブルを全部返す。スタミナは、自然回復を加算した現在値（DBには保存しない） | `auth/login` |
| 更新分（`updated_resources`） | その処理で INSERT / UPDATE した行だけ。変わったテーブルのキーだけが入る | 下の表の「更新分」 |
| 返さない | 7テーブルを更新しない | 下の表の「なし」 |

- `updated_resources` の中身は、同じ行は1つにまとまり、最後の状態になる。物理削除した行は入らない
- 各行の `is_delete` は JSON の真偽値（`true` / `false`）。DB から読んだ行も、そのリクエストで作った新規の行も同じ（`_BaseModel::toResponseArray()` で揃える）
- 新規プレイヤーは `auth/sign_up`、既存プレイヤーは `auth/sign_in` のあとに、必ず `auth/login` を呼んで現在値を全て取得する。`sign_up` / `sign_in` / `refresh_token` は `updated_resources` を返さない（`#[DoesNotReturnUpdatedResources]` で宣言している）
- 注意: 相対更新（`col + n`）は、メモリ上の値を返すため、DBと食い違う可能性がある。同じプレイヤーの書き込みは `LockPlayerRequest` で直列にしているので、通常は一致する

## 書き込み API

| API | 返し方 | 主に変わるもの |
|---|---|---|
| `auth/sign_up`、`auth/sign_in`、`auth/refresh_token` | なし（後続の `auth/login` で取得） | 初期付与、ログイン日時 |
| `auth/login` | 現在値（全量） | ログインボーナスの付与など |
| `item/use` | 更新分 | item、stamina、sys_player、wallet |
| `unit/level_up` | 更新分 | unit、item |
| `equipment/level_up` | 更新分 | equipment、item |
| `gacha/draw` | 更新分 | 有償ダイヤ（diamond）、無償ダイヤ（item の `free_diamond`）、wallet、item、unit、equipment。変更のあった方のキーだけが入る |
| `trade/exchange` | 更新分 | 消費したコストと、受け取った報酬 |
| `in_app_purchase/buy` | 更新分（Pack/Pass のときは `rewards` も返す。`paid_diamond_amount` は返さない） | 有償ダイヤ（diamond）、パックの item（無償ダイヤは `free_diamond`）、unit など。body の `platform` が `X-Platform` と違うと `INVALID_PLATFORM`（99003）で拒否する |
| `mailbox/receive`、`mailbox/receive_all` | 更新分 | 添付物の種類による（wallet、item（無償ダイヤは `free_diamond`）、有償ダイヤ（diamond。運営の調整用）、unit、equipment、stamina、sys_player） |
| `mission/receive` | 更新分 | 報酬の種類による |
| `reward_track/receive` | 更新分 | 報酬の種類による |
| `mailbox/lock`、`mailbox/open` | なし | （メールの状態のみ） |
| `agreement/consent` | なし | |
| `friend/*`（apply 系・delete）、`guild/*`（create・apply 系・leave）、`chat/*`（書き込み）、`notification/read`、`notification/read_all` | なし | |
| `account/*`（引き継ぎ・外部連携）、`admin/maintenance/*` | なし | |

## 読み取り API（GET）

プレイヤーの7テーブルの現在値は、`auth/login` だけが返す。`player/me` は `my_id` と `name` のみ。
`mailbox/list`、`mission/list`、`trade/list`、`album/list`、`friend/list`、`guild/*`、`chat/*`、`notification/list`、`reward_track/summary` などの一覧系は、7テーブルを返さない。

## 新しい書き込み API を作るとき

1. Response に `HasUpdatedResources` を使い、`ReturnsUpdatedResources` を実装して、`toArray()` に `'updated_resources' => $this->getUpdatedResources()->toResponseArray()` を足す
2. UseCase は `return $this->executeWithTransaction(fn () => new XxxResponse(...));` と書くだけ。コミット後に `executeWithTransaction()` が更新を付ける
3. 7テーブルを更新するのに返さない場合は、UseCase に `#[DoesNotReturnUpdatedResources('理由')]` を付ける。付け忘れると、開発・テスト環境で例外になる
4. Response のキーはスネークケース、配列は `_list` で終える（`ResponseKeySnakeCaseTest` / `ResponseKeyNamingTest`）
