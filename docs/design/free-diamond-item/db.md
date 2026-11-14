# 無償ダイヤの trx_item 管理への移行 DB 設計（S2）

機能 `free-diamond-item` のサーバー側 DB 設計。対象は (1) `trx_diamond.free_amount` の取り除き、(2) ダイヤ専用ログ表の `free_amount` 系の列の取り除き、(3) `trx_item` の `free_diamond` 行の扱い、(4) `trx_diamond_balance` の有償のみ・消費連動、(5) mst の `content_type` の `diamond` / `free_diamond` から `item` + `free_diamond` への変更対象。**新しいテーブルは作らない。既存の create マイグレーションを書き換える**（別の列削除マイグレーションは作らない）。

- 要件: `Nexus/docs/requirements/free-diamond-item.md`（Nexus リポジトリ。Q1〜Q24 確定。**仕様の正本**。この文書は決定を複製せず、節番号 C / F / Q で指す）
- 全体設計: `Nexus/docs/design/free-diamond-item/overview.md`（マージ済み。U-1〜U-5 は 6 節で答える）
- API: [api.md](api.md)、シーケンス: [sequence.md](sequence.md)
- 手本: [home-player-info の db.md](../home-player-info/db.md)。DB 全般の規約: `.claude/database.md`
- 確かめ方の凡例: 「読んだ」= コードを読んだだけ。「未確認」= 読めていない。サーバーは main `f42dd2e7` 時点。開発 DB への書き込み（seed・migrate・login など）は行っていない。

## 1. 対象と結論

| 項目 | 内容 |
| --- | --- |
| スキーマの変更 | 2 テーブルだけ。`trx_diamond` の `free_amount` 列、`log_change_trx_diamond` の `free_amount_diff`・`before_free_amount`・`after_free_amount` の 3 列を取り除く（2・3 節）。`trx_item`・`mst_item`・`trx_diamond_balance` は列を変えない |
| 無償ダイヤの置き場 | `trx_item`（`sys_player_id` × `mst_item_id = free_diamond`）の `free_amount`。platform 共通。新しい列・索引は要らない（4 節） |
| `trx_diamond_balance` | 有償のみ。列は変えない。消費のたびに `current_amount` を減らす新しい書き込みが増える（5 節。索引は既存で足りる） |
| mst | 列の型の変更は enum の値の取り除きだけ（6 節）。新しい mst テーブルは無いので、mst の string id 規約（`MstRequiresStringIdTest`・`MstNoAutoIncrementIdTest`）に新しく触れない（7 節） |
| 既存データ | 移行しない（要件 Q3・Q6）。`make migrate-fresh && make seed` で作り直す |
| マイグレーションの置き場 | すべて既存ファイルの書き換え（`.claude/database.md` 3 節。共有の検証環境を作るまで許される）。新規ファイルは作らない |

## 2. `trx_diamond` から `free_amount` を取り除く（F6）

ファイル: `packages/nexus-core-billing/database/migrations/trx/2026_01_01_000001_create_billing_tables.php`（読んだ）。

| 変更前 | 変更後 |
| --- | --- |
| `sys_player_id`・`platform`・`paid_amount`・**`free_amount`**・`is_delete`・`created_at`・`updated_at`。主キー `(sys_player_id, platform)` | `free_amount` の行（`$table->unsignedInteger('free_amount')->default(0)->comment('無償ダイヤモンド数')`）を削除。ほかは変えない |

- 主キー・索引は変えない（`free_amount` を含む索引は無い）。
- `paid_amount` のコメントは「有償ダイヤモンド数」のまま。テーブルのコメント（ファイル内の `trx_diamond: ダイヤモンド現在値管理`）を「有償ダイヤの現在値（platform 別）。無償ダイヤは `trx_item` の `free_diamond`」に直す。
- `trx_diamond` の行は、サインアップ時に `paid_amount = 0` で作られる（`InitialGrantService`）。購入が初めてのときも同じ行に加算する。行が無くても無償は `trx_item` にあるので、ホームの表示は壊れない。

### コード側の追従（列を無くすために消す・直すもの。S3 の範囲）

| 場所 | 直し方 |
| --- | --- |
| `App\Models\Trx\TrxDiamond` | `$fillable`・`$casts` の `free_amount`、`getFreeAmount()`・`setFreeAmount()`、クラスコメントの `free_amount` を削除。`ReturnedAsUpdatedResource` を `ReturnedAsSingleUpdatedResource` に替える（api.md 3 節。`UpdatedResourceModelsTest` の EXPECTED の集合は変わらない。同じ 7 モデル） |
| `Nexus\Core\Resource\DataTransferObjects\DiamondBalance` | `freeAmount`・`getFreeAmount()`・`setFreeAmount()`・`getTotalAmount()`、`fromArray` / `toArray` の `free_amount`・`total_amount` を削除 |
| `DiamondBalanceAdapter`・`DiamondRepositoryAdapter` | `free_amount` の変換・保存の行を削除 |
| `Nexus\Core\Resource\Services\DiamondService` | 有償だけの形にする（sequence.md 2 節）。`addDiamond` の `isPaid`、`findBalance` の `free_amount`・`total_amount`、`consumeFreeThenPaidDiamond`・`validateTotalBalance` を無くす |
| `InitialGrantService` | `addDiamond($sysPlayerId, $platform, 0, true)` を、有償だけの行作成（`addPaidDiamond(..., 0)`）に替え、「`free_amount` に依存しない」の注記を直す |
| テスト・シーダー | `DiamondBalanceAdapterTest`・`InAppPurchaseDiamondBalanceServiceTest`・`TradeEndpointTest`・`MailboxReceiveContentTest`・`BuyEndpointTest`・`WalkthroughTest` ほか `free_amount` を読むもの。**`trx_item` の `free_amount` は残る列なので、検索で `TrxItem` / `trx_item` の `free_amount` を巻き込んで消さない**（`TrxItemSeeder`・`ItemRepositoryAdapter`・`TrxWallet` などは対象外） |
| 運営ツール（`tool/`） | `trx_diamond.free_amount` を読み書きする箇所は無い（`tool/` 全体を `free_amount` / `paid_amount` で検索して 0 件。読んだ）。`PlayerHistoryController` はダイヤのログ 2 表を一覧するだけ |

## 3. ダイヤ専用ログ表の `free_amount` 系の列を取り除く（F6・Q12）

ファイル: `packages/nexus-core-billing/database/migrations/log/2026_01_01_000001_create_billing_log_tables.php`（読んだ）。

| テーブル | 取り除く列 | 備考 |
| --- | --- | --- |
| `log_change_trx_diamond` | `free_amount_diff`・`before_free_amount`・`after_free_amount` | `paid_amount_diff`・`before_paid_amount`・`after_paid_amount` は残す。`reason` のコメント（`purchase, consume_gacha, ...`）は変えない |
| `log_change_trx_diamond_balance` | **無し** | 要件 Q12 は「両方の `free_amount` 系の列」と書くが、この表の列は `amount_diff`・`before_current_amount`・`after_current_amount` などで **`free_amount` 系の列は元から無い**（読んだ）。要件側の記述の補正を申し送る（8 節） |

- この 2 表は**書き込む処理が無い**（要件の非機能）。実際の記録は PITR の `TrxChangeLogger`（`log_change_trx`。`before_data` / `after_data` の JSON）。`free_amount` を取り除いたあとの `trx_diamond` の変更は、JSON から `free_amount` のキーが消えるだけで、記録の仕組みは変わらない。
- `log_change_trx_diamond` の列を指すコードは `free_amount_diff` などで検索して **マイグレーション以外に 0 件**（`api`・`packages`・`tool`。読んだ。`nexus-wallet` の同名の列は別表なので触らない）。
- `log_change_trx_item`（`nexus-core` の log マイグレーション 92 行）の `free_amount_diff` は `trx_item` の列の記録で、残る。
- `log_change_trx_diamond_balance` は、消費連動（5 節）で `current_amount` が変わるようになっても、書き込む処理が無いのは同じ。`TrxChangeLogger` が `trx_diamond_balance` の UPDATE を汎用ログに書く（読んだ範囲では `TrxDiamondBalance` は `_BaseTrx` の UPDATE の対象。**ログの実際の出力は未確認**。S3 で、消費後に `log_change_trx` に `trx_diamond_balance` の行が出ることをテストで確かめる）。

## 4. `trx_item` の `free_diamond` 行（F1・F5・Q8）

### 4.1 テーブル（変更なし）

`trx_item`（`packages/nexus-core/database/migrations/trx/2026_01_01_000001_create_resource_tables.php:58`。読んだ）。

| カラム | 型 | 説明 |
| --- | --- | --- |
| sys_player_id | unsignedBigInteger | 主キーの一部 |
| mst_item_id | string | 主キーの一部。無償ダイヤは **`free_diamond`** |
| free_amount | unsignedInteger（既定 0） | 無償ダイヤの残高。加算のみ・消費で減る |
| paid_amount | unsignedInteger（既定 0） | **`free_diamond` では常に 0**（付与の経路が `freeAmount` だけを渡す） |
| is_delete | boolean | 論理削除フラグ。`free_diamond` では使わない |
| created_at / updated_at | dateTime | |

- 列・索引・主キーは変えない。行は付与の初回に作られる（`addItem` が既存を探して無ければ作る）か、サインアップで作られる（`InitialGrantService`。50）。**付与と消費は同じ 1 行**（`PRIMARY KEY (sys_player_id, mst_item_id)` で 1 プレイヤー 1 行）。
- **`free_amount = 0` になっても行は削除しない**（F5・Q8）。`NexusCore\Resource\Services\ItemService::consumeItem` は減らした値を保存するだけで、削除も `is_delete` の更新もしない（読んだ）。行を消さないので、`updated_resources` の `trx_item_list` には 0 の行が載る。
- `paid_amount` が常に 0 であることは、付与の経路が保つ。`consumeItem` の有償優先の分岐は `free_diamond` では使われない（`paid_amount = 0` なので）。
- 付与は `ItemService::addItem(freeAmount: n)`（`mst_item.is_wallet = false` なのでパッケージ版 `addItem`）、消費はこの機能が新設する経路（sequence.md 3 節）だけ。**通常のアイテム消費経路は `free_diamond` を拒否する**（6.4 節の U-3）。
- `mst_item.free_diamond` の `is_wallet` は **`false` のまま固定**（`MstItemSeederTest` で固定する）。`true` にすると付与・読み取りが Wallet に振り替わり、この設計が前提にする `trx_item` の 1 行が使われなくなる。

### 4.2 `trx_diamond` の行が無い・platform が違うとき

無償は `trx_item` にあり platform に依らない。有償は `X-Platform` の行だけを見る（F12）。`trx_diamond` に `X-Platform` の行が無ければ有償は 0 として扱う（消費の判定・login の `trx_diamond: null`）。

## 5. `trx_diamond_balance`（有償のみ・current_amount 連動。F14・Q4・Q21〜Q23）

ファイル: `packages/nexus-core-billing/database/migrations/trx/2026_01_01_000001_create_billing_tables.php`（読んだ）。**列・主キー・索引は変えない**。

| カラム | 型 | 意味（この機能での使い方） |
| --- | --- | --- |
| id | 連番（`$table->id()`） | 購入単位の行 ID。**作成順の判定に使わない**（TiDB モードでは UUID。`.claude/database.md` 11 節）。同じ秒の同点を決めるためだけに第 2 キーにする |
| sys_player_id | unsignedBigInteger | |
| platform | string | 端末の platform。**消費で行を選ぶキー**（`X-Platform` と一致する行だけ。F12・F14） |
| billing_platform | string | 決済の platform。**消費では見ない**（Q21） |
| current_amount | unsignedInteger | **消費のたびに減らす**（新規）。0 になっても行は消さない |
| purchase_amount | unsignedInteger | 購入時の数量。変えない |
| unit_price | decimal(10,2) | 返金計算用。変えない |
| is_delete・created_at・updated_at | | |

- **有償のみ**: 行は有償の購入（`addPaidDiamondWithBalance`）でだけ作られる。無償は入らない（現状どおり。C5）。課金パックの中身に有償ダイヤは入れられない（`mst_in_app_purchase_content.content_type` は `item`・`unit` だけになる。6 節）ので、**パック購入で行は作らない**（Q23 の「パックが有償ダイヤを含む場合」への答え。含まれないので扱わない）。`type = pass` で `paid_diamond_amount > 0` の購入は `BuyDiamondUseCase` と同じく行を作る（`BuyPassUseCase` が `purchaseDiamond` を呼ぶ。読んだ）。
- **消費の連動**（F14 (2)）: 有償を `paid_amount` から引くのと**同じトランザクション**で、`X-Platform` の行を `created_at` の古い順（同点は `id` の小さい順）に、`current_amount > 0` の行から引き、足りない分は次の行へ。書き込みは `TrxDiamondBalanceRepository::setModel()`（UnitOfWork のキュー。コミット時にまとめて反映。直書きしない）。
- **整合**（Q23）: 同じ `(sys_player_id, platform)` の `is_delete = false` の行の `current_amount` の合計が `trx_diamond.paid_amount` と一致する。作り直した環境は初期の `paid_amount = 0` ・行なしで一致している。**一致しないデータ（引ききれない）に出会ったら、例外でトランザクションごと巻き戻す**（確定。sequence.md 3.3 節。「`DIAMOND_NOT_ENOUGH` にしない」。9 節 U-S4）。合計が合うことは `InAppPurchaseDiamondBalanceServiceTest` 系で購入 → 消費 → 残りで確かめる（テストの追加は S3）。
- **索引**: 取得の条件は `sys_player_id`・`platform`・`is_delete = 0`・`current_amount > 0`・`ORDER BY created_at, id`。既存の `idx_diamond_balance_player_platform (sys_player_id, platform, billing_platform)` の先頭 2 列で絞れる（1 プレイヤーの購入行は少数）。**索引は足さない**（`created_at` は絞った行をメモリで並べる）。
- **Repository・モデル**（S3）:
  - `TrxDiamondBalanceRepository` に `selectConsumableByPlatform(int $sysPlayerId, string $platform)` を足す（`CustomCollection` で返す）。`TrxDiamondRepository::selectByPlayerId` と同じく、**キュー（このリクエストで更新済みの行）を先に見て、DB の行とマージ**する（同じトランザクションで 2 回消費したとき、2 回目が 1 回目の結果から計算できるようにするため。`NoDirectTrxReadInDomainTest` に従い、読み取りは Repository 内）。
  - `TrxDiamondBalance` モデルに型付き getter（`getCurrentAmount()`・`getPlatform()`・`getCreatedAt()`・`getBillingPlatform()`）を足す（いま getter は無く setter だけ。`getAttribute` を外から呼べない規約のため）。
- **テスト seed の方針（M1。S3）**: 既存テストの固定データは、消費が `X-Platform`（`Apple` / `Google`）の行だけを見るようになると壊れる。例: `TradeEndpointTest.php:241`（`platform => 'ios'`・`paid_amount => 50`・`free_amount => 100`）、`GachaDrawTest.php:93-98`（`platform => 'ios'`）、`WalkthroughTest`（`'ios'` を使う。読んだ）。`'ios'` の行は `X-Platform` の行に当たらず有償 0 になり、仮に当たっても **balance の行が無いため FIFO で引ききれず例外**になる。直し方:
  1. `platform` を `ios` → `Apple`（リクエストの `X-Platform` と同じ値）に直す。`free_amount` の列は消す（2 節）。無償は `trx_item` の `free_diamond` の行で seed する。
  2. **有償を持つテストは `trx_diamond_balance` の行も seed する**ヘルパーを作る（例: `seedPaidDiamond(sysPlayerId, platform, amount)`。`trx_diamond` の `paid_amount` と、同じ合計の balance 行 1 件（`current_amount = purchase_amount = amount`）を 1 組で作る）。テストごとに 2 表を手で入れない。合計が一致する前提（Q23）を seed の側で保つため。
  3. 有償が 0 のテストは balance 行なしでよい（`paid_amount = 0` と合計 0 で一致）。
- **運営調整と balance の整合（人の決定。U-S4 確定）**: 将来の運営調整は、返金処理（balance を減算）や、課金だけされて反映されなかったときの救済（balance を 1 レコード追加）を想定し、**`paid_amount` と `trx_diamond_balance` の両方を整合させて操作する**。よって「balance 合計 = `paid_amount`」の前提は運営調整後も保たれる（整合は調整機能の側が取る。別機能）。`DiamondDeliveryHandler` の `PAID_DIAMOND` 経路は balance 行を作らないが、それを運営調整の入口に使うかは別機能で決める。この機能では、合計が合わないデータに出会ったら例外で巻き戻す（上の整合の項）。
- 返金・失効の処理はこの機能の対象外（Q22）。

## 6. mst の `content_type`: `diamond` / `free_diamond` から `item` + `free_diamond` への変更対象（F2・F9）

無償ダイヤの報酬は、すべて `content_type = item`・`content_mst_id = free_diamond` にする。マスターの**値**と**列の定義**を、次の表のとおり直す。マイグレーションはすべて既存の create ファイルの書き換え。

### 6.1 要件で決まっている対象

| 機能 | 列の定義（マイグレーション） | 値（マイグレーション内の初期行・シーダー） |
| --- | --- | --- |
| 課金パックの中身 `mst_in_app_purchase_content`（`nexus-core-billing` の mst `:93`） | enum `['item', 'unit', 'free_diamond']` → **`['item', 'unit']`** | `MstInAppPurchaseSeeder`: 122 行付近（スターターパック。500）と 156 行付近（月間パス。100）の `content_type = free_diamond`・`content_mst_id = diamond` → **`item`・`free_diamond`**。注: **パス商品の `contents` は購入時に付与されない**（`BuyPassUseCase` は有償ダイヤとパス効果だけ。`contents` を読むのは `InAppPurchasePackService` だけ。読んだ）。値の直しはマスターの整合のためで、挙動は変わらない |
| ログインボーナス `mst_login_bonus_content`（`nexus-login` の mst `:59`） | enum `['item','unit','equipment','diamond','wallet','stamina']` → **`diamond` を除く**。`is_paid` のコメント（「wallet/diamondの場合」）を「walletの場合」に | `MstLoginBonusSeeder`: 1・3・7 日目（`diamond` / `free_diamond`）と **6 日目（`diamond` / `diamond`。混在していた。Q11）** → すべて **`item` / `free_diamond`**。6 日目の量（20000）は変えない。`RecordingLoginBonusSeeder` の同じ 1・3・6・7 日目も同様 |
| VIP のレベル報酬 `mst_vip_level_reward`（`nexus-vip` の mst `:66`） | enum 同上から `diamond` を除く。`content_mst_id` のコメント（「diamond/stamina/walletはダミー値」）、`is_paid` のコメントを直す | マイグレーション内の初期行（`:86〜117`）の `content_type = diamond` の 10 行 → **`item` / `free_diamond`**。**注: 初期行の `content_mst_id` は `free_diamond` ではなく `free`**（要件 F9 (3) の記述は `free_diamond`。読んだ事実の補正。8 節）。`free` は `mst_item` に無い値なので、`item` / `free_diamond` に直す |
| VIP ログインボーナス `mst_vip_login_bonus_content`（同ファイル `:144`。**string(32) 列**で enum ではない） | 列は変えない。コメント（「diamond, item, unit等」）を「item, unit, wallet等」に | `MstVipLoginBonusSeeder`: `TYPE_DIAMOND = 'diamond'`・`DIAMOND_ID = 'diamond'` を使う箇所（153〜160 行）を **`item` / `free_diamond`** に。定数名は `TYPE_FREE_DIAMOND`（`'item'`）ではなく、`TYPE_ITEM`（既存の `'item'` 直書きに揃える）と `FREE_DIAMOND_ID = 'free_diamond'` にまとめる（仮置き）。`VipLoginBonusSeederTest` と `DatabaseSeeder:36` のコメントも直す |
| メール添付 `mst_mailbox_content`（`nexus-mailbox` の mst `:105`） | enum から **`'diamond'` だけ除く**（`'paid_diamond'` は残す。Q24） | メールのシーダー・初期行は無い（`api/database/seeders` に mailbox は無い。読んだ）。テストの `diamond` を `item` / `free_diamond` に |

### 6.2 U-1: ミッション・報酬トラック・交換の景品（要件に記載なし）

読んだ結果（`api`・`packages`・`tool`・シーダー・マイグレーション・テストを `diamond` で全文検索）。

| 機能 | 列 | `diamond` の値・シーダー・付与経路 |
| --- | --- | --- |
| ミッション `mst_mission_content`（`create_mission_tables.php:67-68`） | string | **値もシーダーも無い**。あるのは列コメント（「item / unit / equipment / diamond / stamina など」「diamond/staminaはダミー値」）だけ |
| 報酬トラック `mst_reward_track_content`（`create_reward_track_tables.php:92-93`） | string | 同上。コメントだけ（「item / unit / equipment / diamond / wallet / stamina」） |
| 交換の景品 `mst_trade_product_content`（`create_trade_tables.php:94-95`） | string | 同上。コメントだけ。なお `TradeCostType` のコメント（`:11`「値は報酬の content_type（diamond / item / wallet）と揃えている」）は、報酬の `content_type` との対応を述べているので、`diamond` の語を直す（コスト側の `diamond` = 合計は変えない。Q18） |

- **付与経路**: 3 機能とも `content_type` の文字列をそのまま `ResourceDeliveryContent::fromArray(['type' => ...])` に渡し、`ResourceDeliveryService` が `ResourceType` に解釈して Handler を選ぶ（`MissionService::deliver`・`TradeService::deliver`・`RewardTrackService` の配布。読んだ）。`ResourceType::DIAMOND` を廃止すると、運営のシートの取り込みで `diamond` が入ったとき、配布時に `ResourceType` に解釈できず例外（`ValueError: Invalid resource type`）になる。**黙って無償ダイヤが付かないのではなく、失敗する**。
- **結論（U-1 への答え）**: マスターの**値**に直す対象は**無い**。直すのは**コメントだけ**（上の 3 列のコメントと `TradeCostType` の注記。`MstMissionContent`・`MstTradeProductContent` のモデルには `diamond` の語が無い。読んだ）。コメントを `item` / `free_diamond` で無償ダイヤを表す説明に直す。要件 F9 の範囲に**値の直しは追加しない**。コメントの直しを要件に足すかは人に確認する（9 節 U-S3。既定案は「要件の追記は不要。S3 で直す」）。
- 列は string のままなので、`item` + `free_diamond` はそのまま入れられる（enum の追加・マイグレーションの変更は不要）。

### 6.3 U-4: `free_diamond` を指す報酬の別口付与

付与の入口は一本（`ResourceType::ITEM` ほか → `ItemDeliveryHandler` → `ItemGranterInterface::grantItem` → `ItemService::addItem(freeAmount:)`）。読んだ限り、`mst_item.id = free_diamond` を指しうる報酬は次のとおりで、**どれも意図した経路**。

| 経路 | `free_diamond` を指せるか | 扱い |
| --- | --- | --- |
| ログインボーナス・VIP ログインボーナス・ミッション・報酬トラック・交換の景品・メール添付・課金パックの中身 | 指す（`item` / `free_diamond`。6.1・6.2） | 意図どおり。同じ 1 行に加算する |
| ガチャの景品（`create_gacha_tables.php:104,178`。enum `item`・`unit`・`equipment`） | `item` + `free_diamond` を**置こうと思えば置ける**（値の制限は enum だけ） | シーダーには無い。置いたとき付与は同じ経路で `trx_item` の行に入る。置くことを禁止はしない（運営のデータ判断）。`MstGachaSeeder` の景品に `free_diamond` が無いことは検索で確かめた |
| `ResourceType` の `CONSUMABLE`・`MATERIAL`・`TICKET`・`GACHA_TICKET`（`ItemDeliveryHandler` が受ける別の type 値） | `content_mst_id = free_diamond` なら同じ行に入る | 運営の入力。`item` に統一する運用（6.1）。拒否はしない |
| `/item/use`・ユニット/装備のレベルアップ | 付与しない（消費だけ） | 消費の側は 6.4 |
| `InitialGrantService`（サインアップ） | 50 を同じ行へ | 実装済み。変えない |
| 運営ツール（`tool/`） | ダイヤ・`trx_item` を書く処理は無い（読んだ） | 対象外（運営ツールによる調整は別機能。Q24） |

- 付与量の `is_paid`（ログインボーナスの列）は `metadata['is_paid']` に入るだけで、`ItemDeliveryHandler` は読まない。`free_diamond` の報酬に `is_paid = true` を置いても、**有償としては入らず無償として加算される**（`paid_amount` は 0 のまま）。`MstLoginBonusSeeder` の行は全部 `is_paid = false`。ガードは足さない（運営のシートの `is_paid` は `wallet` 用。コメントを直す。上の 6.1）。
- 上限超過（Q20）: `ResourceDeliveryPolicy` の `DIAMOND` の行を消しても**挙動は変わらない**。理由（読んだ）: (1) 上限超過を検出する Handler が無い（`setFailureReason` の定義はあるが呼び出しが `packages/*/src` と `api/app` に無く、`RESOURCE_LIMIT_REACHED` / `INVENTORY_FULL` が立たないので `hasResourceOverflow` は常に false）、(2) `createThrowErrorWhenResourceLimitReachedPolicy` の呼び出し元が無い、(3) 既定ポリシーの `SEND_TO_MAILBOX` を実行する処理も無い。`ITEM` の行（エラーポリシー側に既にある。既定ポリシーでは `NONE`）で結果は同じ。`free_diamond` 個別の方針は足さない。

### 6.4 U-3: `free_diamond` を通常のアイテム消費から守る（DB ではなくコードの設計。ここにまとめる）

`ItemService::consumeItem` を呼ぶ箇所（`ItemInventoryInterface::consumeItem`。読んだ）と、いま `free_diamond` が守られているか。

| 呼び出し | 場所 | いま守られているか |
| --- | --- | --- |
| POST `/item/use` | `UseItemUseCase.php:105` | **守られている（偶然）**。`validation` が `mst_item.effect` を `ItemEffectType` に解釈し、`free_diamond` は `effect = none` なので `INVALID_ITEM_TYPE`（1903）になる |
| ユニットのレベルアップ | `Unit/UseCases/LevelUpUseCase.php:117` | 同上（`effect` が `UNIT_EXP` でなければ `INVALID_ITEM_TYPE`） |
| 装備のレベルアップ | `Equipment/UseCases/LevelUpUseCase.php:161` | 同上（`EQUIPMENT_EXP`） |
| ガチャのコスト `cost_type = item` | `GachaCostService.php:72` | **守られていない**。`cost_mst_id = free_diamond` なら所持数を確かめて `consumeItem` する |
| 交換のコスト `item`（・`wallet`） | `TradeCostPayerAdapter.php:72` | **守られていない**。同上 |

守られている 3 つは `effect = none` というデータに依存しているだけで、ID の拒否ではない。**塞ぐ方法（決定）**: 5 つが通る一点 `App\Domain\Item\Services\ItemService::consumeItem`（`ItemInventoryInterface` の実装）の先頭で、`mstItemId` が `free_diamond` なら **何も変更する前に `GameException(GameErrorCode::INVALID_ITEM_TYPE, ...)`** を投げる。

- ID の定数は、`ItemService`（Item コンテキスト）から他コンテキストの定数を参照できない（`ModuleBoundaryTest`）ので、**パッケージ側に置く**（`nexus-core` の `Nexus\Core\Resource\` 配下に `free_diamond` の ID の定数。名前と置き場所は仮置き。9 節 U-S6）。`InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID` もこれを指す形にそろえる。
- **無償ダイヤの正規の消費は、このガードを通らない別の経路**（パッケージ版 `Nexus\Core\Resource\Services\ItemService::consumeItem` を、ダイヤの消費サービスが直接呼ぶ。`InitialGrantService` がパッケージ版 `addItem` を直接呼ぶのと同じ形。sequence.md 3 節）。
- **ガードの位置**: `ItemService::consumeItem` の**先頭、`isWalletManaged` の分岐より前**（Wallet の移行 `walletItemMigrator->migrate` などの副作用より前に拒否する）。
- **`effect = none` の事実（確認済み）**: `ItemEffectType` の case は `player_exp`・`unit_exp`・`equipment_exp`・`stamina_recover` の 4 つで、`none` は無い。`tryFromEffect('none')` は `null`。`UseItemUseCase::validation`（`$effectType === null` で `invalidItemType`）・ユニット/装備のレベルアップ（`!== UNIT_EXP` / `EQUIPMENT_EXP`）が `INVALID_ITEM_TYPE` で拒否する（読んだ）。
- **正規の無償消費の側の注意**: パッケージ版 `consumeItem` は行が無いと `\Exception('Item not found')` を投げ、有償優先で減らす。そのため消費サービスは**先に無償残高 F を判定し、行が無い・`F = 0`・`f = 0` のときは呼ばない**（sequence.md 3.2 節）。`free_diamond` は `paid_amount` が常に 0 なので有償優先の分岐は影響しない。
- 読み取り（`findItemAmount`）はガードしない。所持数の参照は害が無い。ただし `GachaCostService` は `findItemAmount` で足りていれば次に `consumeItem` を呼んでガードに当たる。足りていなければ `ITEM_NOT_ENOUGH` が先に返る（どちらも拒否。エラーコードが場合で変わる点は許容。仮置き）。
- 付与（`addItem`）はガードしない（F1 のとおり、付与の経路は `free_diamond` を含む）。
- 予防として、マスター側の拒否（ガチャ・交換の `cost_type = item` で `cost_mst_id = free_diamond` を弾く）は**足さない**。ランタイムのガードで足りるため。
- テスト（S3）: ガチャ・交換の `cost_type = item` + `free_diamond` が拒否され、`trx_item` が変わらないこと。`/item/use` ほか 3 つの既存の拒否が保たれること。

## 7. mst の id 規約・アーキテクチャテストとの整合

- **新しい mst テーブルを作らない**。`MstRequiresStringIdTest`（string の `id` 列）・`MstNoAutoIncrementIdTest`（`$table->id()`・`increments`・`autoIncrement` 禁止）の検査対象に**新しい違反を足さない**。
- 書き換えるマイグレーションのうち、`mst_in_app_purchase_content`・`mst_login_bonus_content`・`mst_mailbox_content`・`mst_vip_level_reward`・`mst_vip_login_bonus_content`・`mst_mission_content`・`mst_reward_track_content`・`mst_trade_product_content` は、すでに `MstRequiresStringIdTest::PENDING_VIOLATIONS`（解消待ち）に載っている。**この機能は enum の値とコメントを変えるだけで `id` 列を足さない**ので、一覧は**変えない**（一覧を足しも消しもしない。id 列の追加は別の作り直し）。
- 新しい mst の値は、既存の `mst_item.id = free_diamond`（string の ID。追加済み。`MstItemSeeder`）を指すだけ。mst に連番を要する列は増えない。
- `MigrationNameUniquenessTest`（trx と log の同名のみ）・`PackageRegistrationTest` に影響しない（新規ファイルを作らない）。
- `UpdatedResourceModelsTest`: EXPECTED の集合は変わらない（`TrxDiamond` は残る。`ReturnedAsSingleUpdatedResource` は `ReturnedAsUpdatedResource` を継承するので `is_subclass_of` の判定は変わらない。読んだ）。`TrxDiamondBalance` は付けない（内訳のテーブルは返さない）。
- `NoDirectTrxReadInDomainTest`: Domain から `TrxXxx::query()` を呼ばない。FIFO の取得は `TrxDiamondBalanceRepository` に置く（5 節）。
- `ModuleBoundaryTest`: 無償ダイヤの付与・消費で `App\Domain\Diamond` ・`Item` ・`InAppPurchase` の内部クラスを直接参照しない。ダイヤの消費サービス（`InAppPurchaseDiamondBalanceService`。`DiamondBalanceInterface` の実装）は、パッケージ版の `ItemService` / `DiamondService`（`Nexus\Core\...`）を使う（Item コンテキストの `ItemInventoryInterface` ではない。6.4 のガードをすり抜ける正規の経路のため。パッケージの参照は境界の違反にならない）。

## 8. 要件・全体設計への申し送り（コードで確かめて、記述と食い違った点）

人の決定は変えない。次に要件・全体設計を触るときの直しとして申し送る。

| # | 食い違い | 直し方（案） |
| --- | --- | --- |
| R1 | 要件 F9 (3) は VIP の初期行を「`content_type = diamond`・`content_mst_id = free_diamond`」と書くが、実際の初期行（`create_vip_tables.php:86〜117`）の `content_mst_id` は **`free`** | 事実の補正。決定（`item` / `free_diamond` に直す）は変わらない |
| R2 | 要件 Q12 は専用ログ 2 表の `free_amount` 系を取り除くとするが、`log_change_trx_diamond_balance` に該当列は無い（3 節） | 「`log_change_trx_diamond` の 3 列」に補正 |
| R3 | 要件 C4・F3・全体設計 2・3 節は「応答の `contents`・`total_free_diamond_amount`」を前提にするが、**API の応答には載っていない**（`BuyResponse` は `paid_diamond_amount`・`rewards`・`updated_resources` だけ）。`contents` は `InAppPurchasePackService` の戻り値で、API では `rewards` として出る。`total_free_diamond_amount` は `InAppPurchaseDiamondService` / `InAppPurchasePackService` の戻り値にあるが、`BuyDiamondUseCase`・`BuyPackUseCase`・`BuyPassUseCase` のどれも応答に載せていない（読んだ。api.md 5 節） | 事実の補正。API に載せるかは人に確認（9 節 U-S1） |
| R4 | U-5: 要件 F2 は `contents` の扱いを「Q7」に委ねるが、Q7 は `total_free_diamond_amount` の決定のみ。実質は F3 と api.md 5 節の `rewards` | 次に要件を触るとき、F2 の参照を F3 に直す |
| R5 | 要件 F9 (3) は VIP の「`content_type`（`nexus-vip`）」を enum として書くが、`mst_vip_login_bonus_content` は string 列でマイグレーションに初期行は無い（enum は `mst_vip_level_reward`。6.1） | 6.1 の分け方に合わせて補正 |
| R7 | サーバーの `.claude/database.md:222`（`trx_diamond_balance` の「`id` 昇順が購入順」）は、TiDB モードの UUID の `id` では成り立たない（同 11 節）。FIFO の順は `created_at` 昇順（同点は `id`）にした（5 節） | S3 で `.claude/database.md` の記述を「`created_at` 昇順が購入順（同じ秒は保証しない）」に追従する（api.md 7 節の仕様書の行に加えた） |
| R6 | 全体設計 7 節の確定事項「課金パックの中身に有償ダイヤは入れられない」は、enum を `['item','unit']` にしたあとも成り立つ（5 節） | 変更なし（確認のみ） |

## 9. 未解決点（確認事項と既定案）

この設計で新しく出た点。人の決定が要るものだけを書き、推測で埋めない。仮置きで進める点は既定案を添える。

| # | 確認事項 | 既定案 |
| --- | --- | --- |
| U-S1 | API の応答に `total_free_diamond_amount` を載せるか（R3。要件 F3 は「`trx_item` の `free_diamond` の残高を返す」。全体設計は購入系 API の応答と読めるが、現状はどの応答にも無い） | **載せない**。サービスの戻り値の意味だけを残高に変える（F3 の文面どおり）。クライアントに読む箇所は無く（全体設計 K3）、残高の変化は `updated_resources.trx_item_list` の `free_diamond` で分かるため。**副作用**: 戻り値だけ変えて呼び出し元（3 つの `BuyXxxUseCase`）が読まないなら、`findFreeAmount` は**無駄な読み取り**になる。有償ダイヤの購入と、`rewards` なしの経路（`pass`）にも入る。載せない場合は、戻り値から `total_free_diamond_amount` を落として `findFreeAmount` を呼ばない形も選べる（F3 の文面との差は人が判断）。載せるなら `BuyResponse` に `total_free_diamond_amount`（int。3 種類の商品すべて）を足す（api.md 5.3 節） **事実（人の確認）**: 現行の購入 API の JSON は `{paid_diamond_amount, updated_resources, rewards（Pack のときだけ）}` のみ。サービス戻り値の `total_paid_diamond_amount`・`total_free_diamond_amount`・`contents` は、コントローラ側の `BuyResponse` で捨てられている（`BuyResponse.php`、`BuyDiamondUseCase.php:49-60`、`BuyPackUseCase.php:49-56`）。**人が応答の実態を確認中で、未決** |
| U-S2 | 課金パックの `rewards`（API の応答）の無償ダイヤの表現 | `{"type": "item", "item_id": "free_diamond", "amount": n}`（`item` の分岐に統一。F2）。いまの `{"type": "free_diamond", "amount": n}` は無くなる。クライアントは `rewards` をまだ読まない（未確認。クライアントのコードで `rewards` の参照を検索していない。S4 で確認） |
| U-S3 | U-1 の結論（値の直しは無く、コメントだけ）を要件 F9 の範囲に書き足すか | **足さない**（コメントの直しだけなので、要件の追記は不要。S3 で直す）。要件の追記が要ると判断するなら人が依頼する |
| ~~U-S4~~（確定。旧 U-Q2 を統合） | **確定（人の決定）: 案 A**。balance 合計が引く量に満たなければ、例外でトランザクションごと巻き戻す（`TransactionDataException` 系。`DIAMOND_NOT_ENOUGH` にしない）。運営調整は `paid_amount` と balance を両方整合させて操作する想定（別機能）なので、合計 = `paid_amount` の前提は保たれる。`PAID_DIAMOND` 経路を運営調整の入口に使うかは別機能で決める | 案 B・C は採らない |
| U-S5 | 消費サービスが platform を受け取る方法 | `DiamondBalanceInterface` の引数は変えず、`InAppPurchaseDiamondBalanceService` が `ApiSession::getPlatform()` で `X-Platform` を読む（未設定なら `RuntimeException`。`ApiSession.php:422-430`。暗黙の既定値を使わせない。HTTP 以外の経路からの消費は想定しない）。**代替**: 既存の `DiamondDeliveryHandler` と同じ `DefaultPlatformProviderInterface`（`ApiSessionPlatformProvider`）経由にそろえる。テストで差し替えやすい。ただし未設定のとき `null` を返すので、消費では `null` を例外にする（`Apple` に落とさない）。引数に `platform` を足す案は、呼び出し元（ガチャ・交換）の変更が増えるので採らない |
| U-S6 | `free_diamond` の ID の定数の名前と置き場所（6.4） | `nexus-core` の `Nexus\Core\Resource\` 配下に置く（具体名は S3 で決める）。`InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID` もそれを指す |
| U-S7 | ガチャ・交換の `cost_type = item` で `free_diamond` を指すマスターを、マスターの取り込み時に拒否するか | **拒否しない**。ランタイムのガード（6.4）で足りる。取り込み時の検査は別の機能 |
