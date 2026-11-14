# 無償ダイヤの trx_item 管理への移行 API 設計（S2）

機能 `free-diamond-item` のサーバーの API 設計。**新しい API は足さない**（要件 Q5）。既存の応答の形（login の `trx_diamond`、`updated_resources`、購入の応答、ガチャ・交換・メール・ミッション・報酬トラックの応答）が変わる。

- 要件: `Nexus/docs/requirements/free-diamond-item.md`（**仕様の正本**。F / C / Q の節番号で指す）、全体設計: `Nexus/docs/design/free-diamond-item/overview.md`
- DB: [db.md](db.md)、シーケンス: [sequence.md](sequence.md)
- 手本: [home-player-info の api.md](../home-player-info/api.md)（login 応答の項目表）。形式（HTTP 299 + `error_code`、`updated_resources` の作り方）は `.claude/api.md`・`docs/api_resources.md`
- 「読んだ」= コードを読んだだけ。実行では確かめていない。サーバーは main `f42dd2e7`

## 1. 結論

| 項目 | 変更 |
| --- | --- |
| login の `trx_diamond` | `free_amount` を外す（`platform`・`paid_amount`・`is_delete` が残る）。行が無ければ `null`（従来どおり）。無償は `trx_item_list` の `free_diamond` で返る（実装済み） |
| `updated_resources` の `trx_diamond_list` | **単数の `trx_diamond`（オブジェクト 1 件。`X-Platform` の行）に変える**（F11）。その処理で**変更があった方だけ**載る（F15・Q15）。変更の無い側のキーは省略 |
| `updated_resources` の `trx_item_list` | 無償ダイヤを使った・もらったとき、`free_diamond` の行が載る（`free_amount` が残高。0 でも載る）。配列のまま（変更なし） |
| 購入（`/in_app_purchase/buy`） | body の `platform` と `X-Platform` が不一致なら `INVALID_PLATFORM`（99003）で拒否（F13）。パックの `rewards` の無償ダイヤは `{type: item, item_id: free_diamond}` に（F2） |
| 報酬の種別 | 無償ダイヤの `type` は `item`（`id = free_diamond`）。`diamond` は廃止（F9）。login の `login_bonus_list`・メールの添付に影響 |
| 通常のアイテム消費 | `free_diamond` は拒否（`INVALID_ITEM_TYPE`。db.md 6.4 節。U-3） |
| 破壊的変更 | 上のすべて。旧クライアントは未配布なので互換は持たない（D7）。キーの併記・2 段階の移行はしない |

## 2. `updated_resources` の共通の約束（F11・F15・Q15）

| ルール | 内容 |
| --- | --- |
| キー | ダイヤの有償: `trx_diamond`（**オブジェクト**）。無償ダイヤ: `trx_item_list`（配列。ほかのアイテムと同じ表） |
| 載る条件 | その処理で INSERT / UPDATE された行だけ。変わらなかった方のキーは**省略**（`null` や `[]` を入れない）。クライアントは「キーがあれば上書き、無ければ現状維持」で読む |
| `trx_diamond` の行 | **`X-Platform` の行だけ**。他 platform の行は載らない（有償の読み書きを `X-Platform` の行だけにするため。F12）。購入も、body の `platform` が `X-Platform` と一致したときだけ通るので同じ |
| 同じ行が複数回更新されたとき | 1 件にまとまり、最後の状態が載る（`UpdatedResources` の仕様） |
| `trx_diamond_balance` | 載らない（`ReturnedAsUpdatedResource` を付けない。内訳のテーブル。`UpdatedResourceModelsTest`） |
| 作り方 | `TrxDiamond` に `ReturnedAsSingleUpdatedResource`（`ReturnedAsUpdatedResource` を継承。`SysPlayer` と同じ）を付ける。`UpdatedResources::toResponseArray()` が単数のキー `trx_diamond` で返す。Response 側の組み立ては変えない（`trx_diamond_list` の文字列は Response 側に無い。C10） |
| login | `updated_resources` ではなく、現在値の全量。`trx_diamond`（無ければ `null`）と `trx_item_list`（全量）。変更点は 3 節 |

形の例（無償だけを使った交換）:

```json
{
  "updated_resources": {
    "trx_item_list": [
      { "mst_item_id": "free_diamond", "free_amount": 30, "paid_amount": 0, "is_delete": false }
    ]
  }
}
```

形の例（無償 → 有償にまたがったガチャ）:

```json
{
  "updated_resources": {
    "trx_item_list": [
      { "mst_item_id": "free_diamond", "free_amount": 0, "paid_amount": 0, "is_delete": false }
    ],
    "trx_diamond": { "platform": "Apple", "paid_amount": 70, "is_delete": false }
  }
}
```

（`_BaseModel::toResponseArray()` が `created_at`・`updated_at` を取り除き、`is_delete` を真偽値にする。読んだ。S3 で `trx_diamond` の応答に `free_amount` が無いことをテストで確かめる。）

## 3. POST `/api/auth/login` の応答（変更点だけ。手本 home-player-info の api.md 2.5・2.6 節）

### 3.1 `trx_diamond`（オブジェクト / null。`X-Platform` の行。F6・F8）

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| trx_diamond | object / null | 可 | `X-Platform` の platform の行。無ければ `null`（サインアップで作られるので通常はある。クライアントは `null` を有償 0 として扱う） |
| trx_diamond.platform | string | 不可 | `Apple` / `Google`。常に `X-Platform` の値 |
| trx_diamond.paid_amount | int | 不可 | 有償ダイヤ |
| ~~trx_diamond.free_amount~~ | - | - | **廃止**（F6・F8）。無償は `trx_item_list` の `free_diamond` の `free_amount` |
| trx_diamond.is_delete | boolean | 不可 | |

- 組み立ては `HomeUseCase`（`X-Platform` の行を 1 件選ぶ）・`HomeResponse::toArray`（`'trx_diamond' => $this->trxDiamond?->toResponseArray()`）のまま。`TrxDiamond` から `free_amount` が無くなるので、**コードの変更は不要**（モデルの列定義の変更で応答が変わる。db.md 2 節）。
- ホームの合計は、クライアントが `trx_diamond.paid_amount` と `trx_item_list` の `free_diamond` の `free_amount`（削除行を除く）を足す（D2・D3。F10。サーバーは合計を返さない）。

### 3.2 `trx_item_list`（配列。全量）

構造の変更なし。`free_diamond` の行が、サインアップの初期付与（50）と、すべての無償ダイヤの付与・消費の結果を持つ（1 プレイヤー 1 行）。0 になっても行は残る（Q8）。

### 3.3 `login_bonus_list`（配列。ログインボーナスの報酬）

要素は `Resource::toArray()`（`unique_id`・`type`・`id`・`amount`・`expire_at`・`metadata`）。**無償ダイヤの要素が変わる**:

| 項目 | 変更前 | 変更後 |
| --- | --- | --- |
| type | `diamond` | **`item`** |
| id | `free_diamond`（6 日目だけ `diamond`。混在） | `free_diamond`（統一。Q11） |
| amount | 配布量 | 同じ |

- クライアントの `login_bonus_presenter.gd` の `"diamond"` の分岐は、`item` かつ `free_diamond` を無償ダイヤとして表示する形に直す（全体設計 K2。S4。このドキュメントの対象外）。
- 付与の先は `trx_item` の `free_diamond`。ログインボーナスの付与は login の同じトランザクションの中（変更なし。home-player-info の sequence.md 1 節）。

## 4. ダイヤを消費・付与する API の `updated_resources`（F8・F15）

形は 2 節。**実際に変更された方だけ**が載る。

| API | 消費 | 付与 | `updated_resources` の例 |
| --- | --- | --- | --- |
| POST `/api/gacha/draw` | コスト `diamond`（無償 → 有償）・`paid_diamond`（有償のみ）・`item`（`free_diamond` は拒否） | 景品（`item`・`unit`・`equipment`。景品が `free_diamond` なら `trx_item_list`） | 無償だけ: `trx_item_list` のみ。有償だけ: `trx_diamond` のみ。またがる: 両方。景品の `trx_unit_list` などが加わる |
| POST `/api/trade/exchange` | コスト `diamond`・`paid_diamond`・`item`・`wallet` | 景品（`content_type` は `item` など） | 同上 |
| POST `/api/mailbox/receive`・`/receive_all` | なし | 添付: 無償は `item` / `free_diamond`、有償は `paid_diamond`（`trx_diamond`。platform は従来どおり `metadata['platform']` → `X-Platform` → `Apple`） | 無償: `trx_item_list`。有償（運営の調整用。通常は使わない）: `trx_diamond` |
| POST `/api/mission/receive`・`/api/reward_track/receive` | なし | 報酬（`item` / `free_diamond` で無償ダイヤ） | `trx_item_list` |
| POST `/api/auth/login`（ログインボーナス） | なし | 無償ダイヤ（`item` / `free_diamond`） | login は全量を返す（3 節） |
| POST `/api/in_app_purchase/buy` | なし | 有償（購入）・パックの無償 | 5 節 |

- **消費の判定**（残高不足）: `DIAMOND_NOT_ENOUGH`（1902。HTTP 299）。ガチャは `BusinessLogicException::diamondNotEnough`、交換は `GameException`（現行のまま）。判定は何も書く前。メッセージは `Diamond not enough. Required: n, Current: m`（ガチャ）、`Diamond is not enough. required=n, owned=m`（交換）。`Current` / `owned` は**有償（`X-Platform` の行）＋ 無償**（コスト `paid_diamond` は有償のみ）。
- **メールの応答 `resources`**: `ReceiveResponse`・`ReceiveAllResponse` が返す添付の `resources` の無償ダイヤの要素は、`type: diamond` から **`type: item`（`id: free_diamond`）** になる（`ContentType::DIAMOND` の廃止。F9 (2)。`ContentTypeTest`・`ReceiveAllResponseTest` の直し）。メール一覧の `content_type` の表示ラベルも、`diamond`（「ダイヤ」）の case が無くなる（`MailboxItemAssemblyService` の `contentTypeLabel` は `ContentType::fromString` の結果を使う。無ければ `content_type` の文字列がそのまま出る。読んだ）。
- テスト（S3）: 無償だけ・有償だけ・またがる、のそれぞれで `updated_resources` のキーが変更のあった方だけであること（`TradeEndpointTest`・`GachaDrawTest`・`MailboxReceiveContentTest`）。F11 の直し（7 節）。

## 5. POST `/api/in_app_purchase/buy`（F2・F3・F13）

### 5.1 リクエスト

| 項目 | 変更 |
| --- | --- |
| body `platform`（`Apple` / `Google`） | **残す**（必須）。`X-Platform` と**一致しなければエラー**（5.2 節） |
| ヘッダー `X-Platform` | 既存の必須（`ResolvePlatform`）。購入の加算先の platform はこの値（= body と一致した値） |
| その他（`mst_in_app_purchase_id`・`billing_platform`・`receipt`・`transaction_id`・`product_id`） | 変更なし |

### 5.2 platform の不一致（F13・Q19）

| 項目 | 内容 |
| --- | --- |
| 条件 | body の `platform` ≠ `X-Platform`（`ApiSession::getPlatform()`） |
| 応答 | HTTP 299 ＋ `{"error_code": 99003, "message": "..."}`。`GameErrorCode::INVALID_PLATFORM`（99003）を流用し、**メッセージで区別**する。メッセージの案: `platform in body does not match X-Platform` |
| 副作用 | **なし**。レシート検証（外部 API）の前に拒否する。購入履歴・有償・無償の加算・VIP ポイント・課金ログ・購入失敗ログのいずれも行わない（sequence.md 4 節） |
| Slack 通知 | **出さない**（人の決定）。`_BaseController::execute()` は `GameException` を `notifySlack` するので、通知を避ける形（`ErrorResponse` を直接返す等）にする。手段は S3（sequence.md 8 節 U-Q3） |
| 位置 | `_BaseBuyUseCase::handle` の最初（Diamond・Pack・Pass の 3 UseCase に共通）。Controller は値を取り出して渡すだけ（変更なし）。`BuyRequest` のバリデーション（422 になる）では扱わない（業務エラーは 299 で返す規約） |
| 欠落・不正との区別 | `X-Platform` の欠落・不正は `ResolvePlatform` が先に `INVALID_PLATFORM` を返す（`X-Platform header is required.` など。既存）。クライアントは `error_code` では同じで、**メッセージで見分ける**（人の決定 Q19） |

### 5.3 応答

現行の応答（`BuyResponse::toArray`。読んだ）に、この機能で変わる点を示す。

| キー | 型 | 変更 |
| --- | --- | --- |
| paid_diamond_amount | int | 変更なし。今回購入した有償ダイヤの数（パックは 0） |
| rewards | array | パック・パスのときだけ載る（空なら省略）。**無償ダイヤの要素が変わる**（下） |
| updated_resources | object | `trx_diamond`（単数。有償の購入）・`trx_item_list`（パックの無償）。2 節 |
| ~~total_free_diamond_amount~~ | - | **現状、応答に無い**。現行の購入 API の JSON は `{paid_diamond_amount, updated_resources, rewards（Pack のときだけ）}` のみ。サービス戻り値の `total_paid_diamond_amount`・`total_free_diamond_amount`・`contents` は `BuyResponse` で捨てられている（`BuyResponse.php`、`BuyDiamondUseCase.php:49-60`、`BuyPackUseCase.php:49-56`）。載せるかは**未決**（人が確認中。db.md 9 節 U-S1。既定案は載せない）。載せる場合は `int`・3 種の商品すべて・`free_diamond` の残高 |

`rewards` の要素（パック。`InAppPurchasePackService` の `contents` が `BuyPackUseCase` で `rewards` として出る。読んだ。API のキーは `rewards`で、要件・全体設計の `contents` はサービスの戻り値のキー）:

| `type` | キー | 変更 |
| --- | --- | --- |
| `free_diamond` | `amount` | **廃止**（F2。`item` の分岐に統一） |
| `item` | `item_id`・`amount` | 無償ダイヤは `{ "type": "item", "item_id": "free_diamond", "amount": n }` で出る（他のアイテムと同じ形） |
| `unit` | `mst_unit_id`・`amount` | 変更なし |

- サービスの戻り値 `total_free_diamond_amount` の意味（F3）: 付与後の `trx_item` の `free_diamond` の `free_amount`（残高。行が無ければ 0）。**パックは「今回の付与量」から「残高」に変わる**。今回の付与量は `rewards` の `item` / `free_diamond` の `amount` で分かる。有償ダイヤの購入の戻り値 `total_paid_diamond_amount` は `X-Platform` の行の `paid_amount`（変更なし）。
- `updated_resources`（有償ダイヤの購入）: `trx_diamond`（単数。`X-Platform` の行。`paid_amount` が加算後）。**Apple と Google の 2 行が載る形はなくなる**（不一致を拒否するので）。`BuyEndpointTest` の「body `platform = Google`・`X-Platform: Apple`」のケースは不一致エラーのテストに変わり、一致のケースは同じ platform で書き直す（F13）。
- `type = pass`・`paid_diamond_amount > 0`（`BuyPassUseCase`）も同じ（不一致エラー・有償の加算・balance の作成。全体設計 3 節）。パス商品の `contents`（無償ダイヤ）は購入時に付与されない（既存。db.md 6.1 節）。

### 5.4 エラー（変更・追加）

| 状況 | `error_code` | メッセージ |
| --- | --- | --- |
| body の `platform` ≠ `X-Platform`（新規） | 99003（`INVALID_PLATFORM`） | `platform in body does not match X-Platform`（案） |
| `X-Platform` の欠落・不正（既存） | 99003 | `X-Platform header is required.` など |
| 購入制限・レシート検証・価格・プロダクト ID（既存） | 既存 | 変更なし |

## 6. エラーコード（この機能で関わるもの）

| コード | 名前 | 使う場面 |
| --- | --- | --- |
| 1902 | `DIAMOND_NOT_ENOUGH`（`ResourceErrorCode::INSUFFICIENT_DIAMOND`） | ガチャ・交換のコスト `diamond` / `paid_diamond` の残高不足（有償は `X-Platform` の行 ＋ 無償。`paid_diamond` は有償のみ） |
| 1903 | `INVALID_ITEM_TYPE` | `free_diamond` を通常のアイテム消費（ガチャ・交換の `cost_type = item` ほか）に使った。`/item/use`・レベルアップは従来どおり `effect` で拒否（db.md 6.4 節） |
| 1901 | `ITEM_NOT_ENOUGH`（`ResourceErrorCode::INSUFFICIENT_ITEM`） | `free_diamond` をコスト `item` に指定し、かつ所持数が足りないとき（ガードの前に判定。許容。db.md 6.4 節） |
| 99003 | `INVALID_PLATFORM` | `X-Platform` の欠落・不正（既存）。購入の body との不一致（新規。メッセージで区別） |
| 40900 / 50300 | プレイヤーロックの 409 / 503（既存） | 変更なし |

新しいエラーコードは足さない（Q19）。

## 7. 直す範囲（F11 の洗い出し。漏れなく）

`trx_diamond_list` → 単数 `trx_diamond`、`free_amount` の削除に伴うもの。**検索で確かめた**（`api`・`packages`・`docs`・`tool`。読んだ）。

| 種類 | 場所 | 直し方 |
| --- | --- | --- |
| テスト | `api/tests/Feature/Trade/TradeEndpointTest.php:121,124` | `updated_resources.trx_diamond_list.0.free_amount` を、無償は `trx_item_list` の `free_diamond` の行（`mst_item_id` で探す。**並びに依存しない**）、有償は `trx_diamond.paid_amount` に。`:124` の `trx_item_list.0.free_amount` は `free_diamond` が入ると並びが変わりうるので `mst_item_id` で探す（C10） |
| テスト | `api/tests/Feature/Mailbox/MailboxReceiveContentTest.php:209,214` | `['trx_diamond_list'][0]['free_amount']` を `trx_item_list` の `free_diamond` に。`:214` の `trx_item_list[0]` は並びに依存しない探し方に |
| テスト | `api/tests/Feature/InAppPurchase/BuyEndpointTest.php:63-90,146` | 「body `Google`・`X-Platform: Apple`」を不一致エラーのテストに。一致のケースは `trx_diamond`（単数）の `paid_amount` を見る |
| テスト | `api/tests/Feature/Login/HomeSnapshotTest.php:37`（コメント「updated_resources は従来どおり trx_diamond_list」） | 「`updated_resources` は単数の `trx_diamond`」に直す |
| テスト | `api/tests/Feature/Login/HomeTrxDiamondTest.php`（`trx_diamond_list` を検索で拾った） | `free_amount` が応答に無いこと・キー `trx_diamond_list` が無いことを確かめる形に（内容は S3 で確認） |
| テスト | `GachaDrawTest`・`DiamondDeliveryHandlerTest`・`ContentTypeTest`・ログインボーナス・メール・VIP・課金パックの各テスト（`diamond` / `free_diamond`） | F9 (6)。`item` / `free_diamond` に |
| 仕様書 | `docs/api_resources.md:4`（「7 つのモデル」の説明に `trx_diamond_list`）。`:29`・`:31`・`:32` の「diamond」（変わるものの説明）も、有償は `trx_diamond`、無償は `trx_item` と読める書き方に | 「`trx_diamond`（単数。`X-Platform` の行）」と書く。7 つのモデルの集合は変わらない |
| 仕様書 | `docs/design/home-player-info/api.md` U-9（274 行。「変えない前提」）・2.5 節・4 節・11 節の `free_amount` と `trx_diamond_list` の記述 | この機能の完了後に追従（home-player-info の文書は、この機能の PR では**直さない**。別の小さな追従。要件 Q14 と同じ扱い。**このドキュメントは複製・修正しない**） |
| 仕様書 | `docs/design/title-to-home/api.md:308,337`（login の `trx_diamond_list`） | home-player-info の S3 の後に直す予定のものと同じ扱い（`trx_diamond` の単数・`free_amount` なし） |
| 仕様書 | サーバーの `.claude/database.md:222`（`trx_diamond_balance` の「`id` 昇順が購入順」。TiDB の UUID で成り立たないので「`created_at` 昇順」に追従）、`.claude/database.md:212,221`（`paid_amount` / `free_amount`、「現在の有償・無償ダイヤ数」）・`.claude/coding-standards.md:105`（`trx_diamond` を `ReturnedAsUpdatedResource` の例に挙げ、1 行だけ単数にするのは `sys_player` とする記述）。`.claude/api.md:145`（`cost_type`）は変えない（Q18） | S3 で `trx_diamond` も単数で返すこと、`free_amount` が `trx_item` にだけ残ることを書く。直したら `api/tests/Architecture/AgentDocsInSyncTest` が通ることを確かめる（同期の対象は未確認） |
| クライアント | `diamond_total.gd`・`login_bonus_presenter.gd` ほか | 全体設計 6 節（K1〜K4）。このドキュメントの対象外 |

## 8. 影響の整理（クライアント・動画）

- クライアントは login 以外の `updated_resources` を**まだ読まない**（要件 C11）。この機能で応答の形が変わっても、今のクライアントは壊れない。将来読むときは、単数 `trx_diamond` と配列 `trx_item_list` の型が違うことに注意する（全体設計 K4）。
- クライアントの変更は小さい（K1: ホームの合計から `trx_diamond.free_amount` を外す。K2: `login_bonus_list` の `item` / `free_diamond` を無償ダイヤとして表示）。サーバーの F6・F8 と同じ時期に出す（Q10）。
- 動画の撮影用シード（`api/database/seeders/Recording/`）は `RecordingLoginBonusSeeder` の `diamond` を `item` / `free_diamond` に直す（db.md 6.1 節）。

## 9. 未解決点

要件の確認事項はすべて解決済み。この設計で新しく出た点は [db.md](db.md) 9 節（U-S1〜U-S7。U-S4 は旧 U-Q2 を統合）と [sequence.md](sequence.md) 8 節（U-Q1・U-Q3）に集約している。API の応答・エラーに関わるのは次の 3 点。

| # | 確認事項 | 既定案 |
| --- | --- | --- |
| U-S1（再掲） | 購入の応答に `total_free_diamond_amount` を載せるか | 載せない（5.3 節）。載せるなら `BuyResponse` に int を足す |
| U-S2（再掲） | パックの `rewards` の無償ダイヤの表現 | `{type: item, item_id: free_diamond, amount}` |
| U-Q3（確定） | 不一致エラーを Slack に通知するか | 通知しない（5.2 節。確定） |
