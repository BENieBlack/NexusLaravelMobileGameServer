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
| アイテムの表示名（design-change） | `mst_item__l10n` の行をシーダーで入れる（10 節）。**テーブル・列は変えない**（既存。新しい mst テーブルではない）。クライアントがホーム・ログインボーナスなどで全アイテムの名前を引く。あわせて `mst_item` に `stamina_50` を足し、ログインボーナス 5 日目をそれにする（10.3.1 節）。`mst_item` は 29 行 |
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

反映状況は各行の末尾に書いた（design-change「表示名の配信」）。

| # | 食い違い | 直し方（案） |
| --- | --- | --- |
| R1 | 要件 F9 (3) は VIP の初期行を「`content_type = diamond`・`content_mst_id = free_diamond`」と書くが、実際の初期行（`create_vip_tables.php:86〜117`）の `content_mst_id` は **`free`** | 事実の補正。決定（`item` / `free_diamond` に直す）は変わらない **反映済み（要件 F9 (3) を補正）。** |
| R2 | 要件 Q12 は専用ログ 2 表の `free_amount` 系を取り除くとするが、`log_change_trx_diamond_balance` に該当列は無い（3 節） | 「`log_change_trx_diamond` の 3 列」に補正 **反映済み（要件 Q12・F6 を補正）。** |
| R3 | 要件 C4・F3・全体設計 2・3 節は「応答の `contents`・`total_free_diamond_amount`」を前提にするが、**API の応答には載っていなかった**（`BuyResponse` は `paid_diamond_amount`・`rewards`・`updated_resources` だけ）。`contents` は `InAppPurchasePackService` の戻り値で API では `rewards` として出る。人の決定（B）で `paid_diamond_amount` も外し、`total_*_diamond_amount` は載せない | 要件 F3 を「`total_free_diamond_amount` は応答に項目として出さず、残高は `updated_resources`（`trx_item_list` の `free_diamond`）で返す」に直す。C4 の記述も実態に（応答は `updated_resources`・`rewards`）。全体設計 2 節・3 節の `total_free_diamond_amount` の記述も同様 **反映済み（要件 F3・Q7 を補正し、決定 B の 1 行を足した。overview.md も同様）。** |
| R4 | U-5: 要件 F2 は `contents` の扱いを「Q7」に委ねるが、Q7 は `total_free_diamond_amount` の決定のみ。実質は api.md 5 節の `rewards`（F3 は上の R3 のとおり直す） | 次に要件を触るとき、F2 の参照を `rewards` の説明に直す **反映済み（要件 F2 の参照を直した。overview.md U-5 も解決済みに）。** |
| R5 | 要件 F9 (3) は VIP の「`content_type`（`nexus-vip`）」を enum として書くが、`mst_vip_login_bonus_content` は string 列でマイグレーションに初期行は無い（enum は `mst_vip_level_reward`。6.1） | 6.1 の分け方に合わせて補正 **対象外（今回は直していない。従来のまま）。** |
| R7 | サーバーの `.claude/database.md:222`（`trx_diamond_balance` の「`id` 昇順が購入順」）は、TiDB モードの UUID の `id` では成り立たない（同 11 節）。FIFO の順は `created_at` 昇順（同点は `id`）にした（5 節） | S3 で `.claude/database.md` の記述を「`created_at` 昇順が購入順（同じ秒は保証しない）」に追従する（api.md 7 節の仕様書の行に加えた） **対象外（S3 で `.claude/database.md` を追従する。従来のまま）。** |
| R6 | 全体設計 7 節の確定事項「課金パックの中身に有償ダイヤは入れられない」は、enum を `['item','unit']` にしたあとも成り立つ（5 節） | 変更なし（確認のみ） **対象外（変更なし・確認のみ）。** |

## 9. 未解決点（確認事項と既定案）

この設計で新しく出た点。人の決定が要るものだけを書き、推測で埋めない。仮置きで進める点は既定案を添える。

| # | 確認事項 | 既定案 |
| --- | --- | --- |
| ~~U-S1~~（解決済み。人の決定 B） | 購入応答は `updated_resources` と `rewards`（Pack/Pass のときだけ）だけ。`paid_diamond_amount`・`total_paid_diamond_amount`・`total_free_diamond_amount` は載せない。サービス戻り値からも外し、`findFreeAmount`・`findBalance` の読み取りは作らない／無くす | 要件 F3 の直しを申し送り（R3） |
| U-S2 | 課金パックの `rewards`（API の応答）の無償ダイヤの表現 | `{"type": "item", "item_id": "free_diamond", "amount": n}`（`item` の分岐に統一。F2）。いまの `{"type": "free_diamond", "amount": n}` は無くなる。クライアントは `rewards` をまだ読まない（未確認。クライアントのコードで `rewards` の参照を検索していない。S4 で確認） |
| U-S3 | U-1 の結論（値の直しは無く、コメントだけ）を要件 F9 の範囲に書き足すか | **足さない**（コメントの直しだけなので、要件の追記は不要。S3 で直す）。要件の追記が要ると判断するなら人が依頼する |
| ~~U-S4~~（確定。旧 U-Q2 を統合） | **確定（人の決定）: 案 A**。balance 合計が引く量に満たなければ、例外でトランザクションごと巻き戻す（`TransactionDataException` 系。`DIAMOND_NOT_ENOUGH` にしない）。運営調整は `paid_amount` と balance を両方整合させて操作する想定（別機能）なので、合計 = `paid_amount` の前提は保たれる。`PAID_DIAMOND` 経路を運営調整の入口に使うかは別機能で決める | 案 B・C は採らない |
| U-S5 | 消費サービスが platform を受け取る方法 | `DiamondBalanceInterface` の引数は変えず、`InAppPurchaseDiamondBalanceService` が `ApiSession::getPlatform()` で `X-Platform` を読む（未設定なら `RuntimeException`。`ApiSession.php:422-430`。暗黙の既定値を使わせない。HTTP 以外の経路からの消費は想定しない）。**代替**: 既存の `DiamondDeliveryHandler` と同じ `DefaultPlatformProviderInterface`（`ApiSessionPlatformProvider`）経由にそろえる。テストで差し替えやすい。ただし未設定のとき `null` を返すので、消費では `null` を例外にする（`Apple` に落とさない）。引数に `platform` を足す案は、呼び出し元（ガチャ・交換）の変更が増えるので採らない |
| U-S6 | `free_diamond` の ID の定数の名前と置き場所（6.4） | `nexus-core` の `Nexus\Core\Resource\` 配下に置く（具体名は S3 で決める）。`InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID` もそれを指す |
| U-S7 | ガチャ・交換の `cost_type = item` で `free_diamond` を指すマスターを、マスターの取り込み時に拒否するか | **拒否しない**。ランタイムのガード（6.4）で足りる。取り込み時の検査は別の機能 |
| U-S8 | `mst_item__l10n` の `ja` の `name`（10.3 節の一覧）の確定。**運営が決める**（仮置きのまま出荷しない） | 10.3 節の仮置きで進める。`free_diamond` だけは `無償ダイヤ`（クライアント設計 5-3 S-1 の必須。人の決定済み）。`description` は全行 `null` |
| ~~U-S9~~（解決済み。人の決定） | ログインボーナスの `stamina` 報酬。**決定**: ログインボーナスでスタミナを渡せるようにする。`mst_item` に `id = stamina_50`・`value = 50` を足し、アイテムとして配り、使用するとスタミナが回復する | 設計は 10.3.1 節。列の解釈（人の言う `category = stamina` を `type = 'stamina'` と読む）は U-S12 で確認 |
| ~~U-S10~~（解決済み。人の回答＋確定） | `MstItemL10n` モデルと `MstItem::l10n()` | **足す（確定）**。l10n のモデルは `MstMessageL10n`・`MstTagL10n`・`MstMissionL10n`・`MstTradeL10n`・`MstTradeProductL10n`・`MstEventL10n` が既にあり、`MstItemL10n` だけ無い。`.claude/coding-standards.md` 99 行目が「`<親>__l10n` にもモデルを作り、親から `l10n(): HasMany` で引く」と定める。キャッシュの扱いは 10.7 節（U-S13） |
| ~~U-S11~~（解決済み。人の決定） | `mst_item` の全行に `mst_item__l10n` の行があることを検査するテスト | **入れる**（29 行。10.4 節） |
| U-S12 | 人の言う「`category = stamina`」は、実際の `mst_item` の列（`type`・`effect`・`value`。`category` 列は無い）では **`type = 'stamina'`** のことか | **`type = 'stamina'`**（`effect = 'stamina_recover'`・`value = 50`・`is_wallet = false`）と解釈して設計した（10.3.1 節）。`type` は string 列（enum ではない）で、いまの値は `recovery`・`boost`・`experience`・`unit_enhancement`・`material`・`currency`・`natural_resource`・`points`。`UseItemUseCase` は `effect` だけで分岐するので、`type` は表示・分類用。**違うなら人が指示**（例: `type = 'recovery'` に入れる） |
| U-S13 | 人の提案ルール「l10n は親を eager load して一緒にキャッシュする」を、この機能で `MstItemRepository` に適用するか（10.7 節） | **適用する**（S3）。基底 `_BaseMstRepository`（`packages/nexus-core`）に eager load の指定を足し、`MstItemRepository` が `['l10n']` を指定する。この機能の API は l10n を読まない（読み手が無い）ので効果は将来の読み手向け。**読み手が出るまで入れない**案もあり、人が決める。ルールの `.claude/` への明文化は S3 |

## 10. アイテムの表示名: `mst_item__l10n` の行を入れる（design-change。要件「表示名の配信」）

背景（人の決定）: クライアントはホーム・ログインボーナス画面で、アイテム（無償ダイヤを含む全アイテム）の表示名を **`mst_item__l10n`** から引く。クライアントの言語は `ja` 固定で、`Accept-Language: ja` を送る（[api.md](api.md) 10 節）。ところが `mst_item__l10n` に行を入れるシーダーが無く、いまは常に空（読んだ。`MstItemSeeder` は `mst_item` だけ。`database/seeders` に l10n を入れるものは他にも無い）。クライアント設計の申し送り S-1〜S-6 は `NexusGodotMobileGameClient/docs/design/free-diamond-item/client_design.md` の 5-3・5-3-1 が正本（参照のみ）。

### 10.1 テーブルと配信（変更なし）

| 項目 | 内容（読んだ） |
| --- | --- |
| テーブル | `mst_item__l10n`（`packages/nexus-core/.../2026_01_01_000001_create_resource_tables.php:82-93`。既存。**マイグレーションは変えない**） |
| 列 | `deploy_key`（既定 202601010）・`mst_item_id`（string。`mst_item.id`）・`language`（ENUM。`api/config/language.php` の `supported` と同じ一覧）・`name`（必須）・`description`（null 可）・`created_at`・`updated_at` |
| 主キー | 複合 `(mst_item_id, language)`（名前 `pk_item_language`）。**`id` 列は無い** |
| 配信 | 運営ツールの `MasterDataExporter` は `mst_%` の全テーブルを出し、`__l10n` を親にまとめる（`groupTables`）ので、`mst_item__l10n` は **`mst_item` の SQLite（`master.tables` のキーは `mst_item`）に入る**。エクスポートの変更は要らない |
| この機能の API | 応答に名前を載せない（l10n を使わない。[api.md](api.md) 10 節）。クライアントが配信された SQLite から引く |

### 10.2 方針（シーダー・規約）

- **`MstItemSeeder` の全行**（`free_diamond` と、この設計で足す `stamina_50` を含む。10.3 節の 29 行）に、`language = ja` の行を 1 行ずつ入れる。名前の正本は `MstItemSeeder` の `$items` の各要素に `name`（必須）・`description`（省略可。省略は `null`）を足して持つ。ID と名前を別の配列に分けない（足し漏れを防ぐ）。
- 冪等: `mst_item` と同じく、実行の頭で `mst_item__l10n` も `truncate`（`mst_item` の truncate の直後）。
- `deploy_key`: `mst_item` の行と同じ値（`MstItemSeeder` の `202601010`）。
- **規約に反しない点**: `mst_item__l10n` は mst の「string の `id` 必須」の対象外（主キーは親の ID + `language`。`MstRequiresStringIdTest` は `__l10n` を除外し、`.claude/database.md` 3 節・6 節）。新しいテーブルを作らないので `PENDING_VIOLATIONS` も変えない（7 節）。`deploy_key` 列は既にある（同 7 節）。`name`・`description` に `id` 的な値は入れない。
- `language` は `ja` だけ入れる（クライアントは `ja` 固定。他言語の行は運営がマスター投入で足す。ENUM の値は変えない）。
- 書き込みは `_BaseMst::allowWrites()` の中（既存のシーダーと同じ）。モデルは `MstItemL10n`（U-S10 確定。`MstMessageL10n` の形）。

### 10.3 `mst_item` の全行と `ja` の名前（**仮置き。運営が確定する**）

`MstItemSeeder` の全行（いまの 28 行に `stamina_50` を足して 29 行。2026-11-14 に読んだ）。手掛かりは、`ResourceType::label()`（`packages/nexus-core/src/Resource/Enums/ResourceType.php`。ゴールド・コイン・食料など Wallet 管理の名前）、シーダーの `type`・`effect`・`value`、テストが作る名前（`UnitLevelUpTest` の `ユニット経験値ポーション`・`スタミナポーション`。シーダーの行ではなくテストが入れる別 ID）。「現状の名前」は、いま名前を持つ場所があるものだけ書く。

| `mst_item.id` | `type` / `effect`（`value`）| 現状の名前・説明の手掛かり | `ja` の `name`（仮置き） |
| --- | --- | --- | --- |
| `item_heal_001` | recovery / heal_hp（100） | 無し | 回復ポーション（小） |
| `item_heal_002` | recovery / heal_hp（500） | 無し | 回復ポーション（中） |
| `item_heal_003` | recovery / heal_hp（1000） | 無し | 回復ポーション（大） |
| `item_boost_001` | boost / boost_attack（1.2） | 無し | 攻撃力アップ薬 |
| `item_boost_002` | boost / boost_defense（1.2） | 無し | 防御力アップ薬 |
| `item_boost_003` | boost / boost_speed（1.3） | 無し | 素早さアップ薬 |
| `item_exp_001` | experience / add_exp（1000） | 無し（`MstVipLoginBonusSeeder` が `item_exp_002`・`item_exp_003` を VIP ボーナスの景品にする） | 経験値アイテム（小） |
| `item_exp_002` | experience / add_exp（5000） | 同上（VIP ボーナスの景品） | 経験値アイテム（中） |
| `item_exp_003` | experience / add_exp（10000） | 同上（VIP ボーナスの景品） | 経験値アイテム（大） |
| `unit_exp_100` | unit_enhancement / unit_exp（100） | 無し（テストの `unit_exp_potion` の名前 `ユニット経験値ポーション` が近い） | ユニット経験値（100） |
| `unit_exp_1000` | 同上（1000） | 同上 | ユニット経験値（1000） |
| `unit_exp_10000` | 同上（10000） | 同上 | ユニット経験値（10000） |
| `unit_exp_100000` | 同上（100000） | 同上 | ユニット経験値（100000） |
| `item_material_001` | material / none | 無し | 素材 1 |
| `item_material_002` | material / none | 無し | 素材 2 |
| `item_material_003` | material / none | 無し | 素材 3 |
| `gold` | currency / none（Wallet） | `ResourceType::GOLD` のラベル `ゴールド` | ゴールド |
| `coin` | currency / none（Wallet） | `ResourceType::COIN` のラベル `コイン` | コイン |
| `stamina_50` | stamina / stamina_recover（50）（**新規行。10.3.1 節**。`trx_item`） | 無し（ログインボーナス 5 日目の `stamina` 50 を置き換える） | スタミナ回復薬（50） |
| `free_diamond` | currency / none（`trx_item`） | 要件・クライアント設計の名前 `無償ダイヤ`（`ResourceType::DIAMOND` のラベルは `ダイヤ`。廃止予定） | **無償ダイヤ**（必須。人の決定済み） |
| `food` | natural_resource / none（Wallet） | ラベル `食料` | 食料 |
| `wood` | 同上 | ラベル `木材` | 木材 |
| `stone` | 同上 | ラベル `石材` | 石材 |
| `iron` | 同上 | ラベル `鉄` | 鉄 |
| `alliance_points` | points / none（Wallet） | ラベル `アライアンスポイント` | アライアンスポイント |
| `pvp_points` | 同上 | ラベル `PvPポイント` | PvPポイント |
| `event_points` | 同上 | ラベル `イベントポイント` | イベントポイント |
| `achievement_points` | 同上 | ラベル `達成ポイント` | 達成ポイント |
| `vip_points` | 同上 | ラベル `VIPポイント` | VIPポイント |

- 上の表は 29 行。`stamina_50` の `ja` の名前も**仮置き**（U-S8）。
- `description` は全行 `null`（仮置き。名前だけで足りる。説明文は運営が決める。U-S8）。
- 上の一覧にあるのは**シーダーの ID だけ**。テストが自分で作る ID（`unit_exp_potion`・`stamina_potion`・`equipment_exp_potion` など）は対象外（各テストが自分の l10n を入れ、後始末する）。
- 「手掛かり」が `ResourceType::label()` のものは、ラベルと名前が二重管理になる（ラベルは配信側の `Resource` の表示用。クライアントは l10n を引く）。**この機能では揃えるだけで、どちらかを消さない**。

### 10.3.1 スタミナ回復薬 `stamina_50`（人の決定。U-S9）

**決定**: ログインボーナスでスタミナを渡せるようにする。`mst_item` に `id = stamina_50`・`value = 50` を足す。アイテムとして配り、使用するとスタミナが回復する。

| 項目 | 設計 |
| --- | --- |
| `mst_item` の行 | `['id' => 'stamina_50', 'type' => 'stamina', 'effect' => 'stamina_recover', 'value' => 50, 'is_wallet' => false]`（`MstItemSeeder`。`type = 'stamina'` は人の言う `category` の解釈で、U-S12 で人に確認）。`effect` は既存の `ItemEffectType::STAMINA_RECOVER`（`packages/nexus-core/src/Resource/Enums/ItemEffectType.php`）。`is_wallet = false` なので `trx_item` で持つ |
| 付与 | ログインボーナス 5 日目（`MstLoginBonusSeeder.php:41`）を `content_type = stamina`・`content_mst_id = stamina`・`amount = 50` から **`content_type = item`・`content_mst_id = stamina_50`・`amount = 1`** に変える。付与は `ItemDeliveryHandler` 経由で `trx_item` の `stamina_50` 行の `free_amount` に足される（要件 F1 と同じ経路。ログインボーナスの付与は login の同じトランザクション内） |
| 使用 | `POST /api/item/use`（`UseItemUseCase`）。`ItemEffectType::STAMINA_RECOVER` の分岐が `value × 使用個数` を整数にして `staminaGranter->grantStamina()` へ渡す（読んだ。`UseItemUseCase.php:117`）。**新しいコードは要らない** |
| `ResourceType::STAMINA` | **残す**（削除しない。他の経路 `StaminaDeliveryHandler` がある）。ログインボーナスの `content_type = stamina` の値が、このシーダーから無くなるだけ |
| 既知の不具合との関係（事実） | 従来、5 日目のスタミナ報酬は `StaminaDeliveryHandler` → `grantStamina` で付与され、スタミナ行が無いと `recoverStaminaByItem` が「Stamina record not found」を返すのに、呼び出し側（`StaminaGranterAdapter`）は結果を見ずに捨てるため、**付与が黙って無くなる**（home-player-info の db.md 205 行目。読んだ）。アイテムとして配る形にすると、**ログインボーナスの付与は `trx_item` への加算だけでスタミナ行に依存しないので、この経路で黙って捨てられることは無くなる**。ただし使用（`/item/use`）は同じ `grantStamina` を通るので、スタミナ行が無いときの扱い（アイテムの消費は先に済む）は変わらない。初期スタミナ行をサインアップで作る対応（home-player-info 側）が前提。この機能では直さない |
| 他の直し | (1) `RecordingLoginBonusSeeder` の 5 日目（`5 => ['stamina', 'stamina', 50]`）も同じく `item` / `stamina_50` / 1 に。(2) `MstLoginBonusSeeder` のコメント（`content_type` の例）。(3) テスト: `MstItemSeederTest` に `stamina_50` の行（`type`・`effect`・`value`・`is_wallet = false`）、ログインボーナスの 5 日目のテスト（あれば）。(4) `ja` の名前（10.3 の仮置き）を `mst_item__l10n` に |
| クライアント | **変更不要**。ログインボーナス表示は `item` の名前を `mst_item`（l10n）から引く（クライアント設計 3-2。`stamina` は従来「`mst_item` を引かない」分類だったが、`item` になるので名前が出る）。名前が引けないときは従来の「アイテム (ID:…)」のフォールバック |

### 10.4 テスト（S3 で足す）

- `MstItemSeederTest`: ① `mst_item` の**全行（29 行）**に `language = ja` の `mst_item__l10n` の行がある（名前が空でない）、② `free_diamond` の `ja` の `name` が `無償ダイヤ`、③ `stamina_50` の行が `type = stamina`・`effect = stamina_recover`・`value = 50`・`is_wallet = false` で入る、④ 同じシーダーを 2 回流しても行が増えない（主キー重複で落ちない＝冪等）、⑤ `tearDown` で `mst_item__l10n` も消す（いまは `mst_item` だけ消す。行を残すと他のテストの後始末と食い違う）。
- アーキテクチャ: `MstRequiresStringIdTest`・`MstNoAutoIncrementIdTest` は変更なし（10.2）。

### 10.5 録画用シード

**専用のシナリオは足さない**。録画用のシード（`Recording/`）は `make seed`（`MstItemSeeder`）済みが前提で、`MstItemSeeder` が全行の `ja` の l10n を入れるので、`RecordingLoginBonusSeeder` が使う `free_diamond`・`gold` の名前もそれで足りる（重複を避ける）。`README.md` の前提の節に「`mst_item__l10n` も `make seed` が入れる」と 1 行足すだけ。ただし `RecordingLoginBonusSeeder` の 5 日目は `item` / `stamina_50` / 1 に直す（10.3.1 節）。`stamina_50` の `mst_item` の行は `MstItemSeeder` が入れるので、`make seed` 済みが前提のままで足りる。

### 10.6 運用作業（コードの変更ではない）

- エクスポート（`POST /master-import/export`）は**変更しない**。行を入れたあとに**再エクスポート**し、version API の `master.tables` に `mst_item` が載る（`mst_item__l10n` は同じファイルに入る）ことを確かめる（クライアントの S5 の動画の前提）。
- 本番のマスター投入（スプレッドシート → `MasterImportService`）で `mst_item__l10n` を持つかは、運営の運用（この機能の対象外）。シーダーは開発・検証環境の初期値。

### 10.7 l10n の読み方とキャッシュ（U-S10・U-S13。事実と設計）

**事実（読んだ）**

| 項目 | 現状 |
| --- | --- |
| l10n のモデル | `MstMessageL10n`・`MstTagL10n`・`MstMissionL10n`・`MstTradeL10n`・`MstTradeProductL10n`・`MstEventL10n`（`api/app/Models/Mst/`）。**`MstItemL10n` は無い**。親側に `l10n(): HasMany` があるのは `MstMessage` だけ（`MstMessage::l10n()`） |
| 規約 | `.claude/coding-standards.md` 99 行目: 「`<親>__l10n` にもモデルを作り、親から `l10n(): HasMany` で引く」 |
| mst のキャッシュ | `_BaseMstRepository::queryOrMemory()`（`packages/nexus-core/src/Repositories/Mst/`）は、**親テーブル 1 つ**の全行を `Model::all()` で読み、Redis に `mst:{table}:all` のキーで `cacheTtl` の間置く。`all()` はリレーションを読まないので、**l10n はキャッシュに入らない**。l10n 専用の Repository・キャッシュも無い |
| 実際の l10n の読み方 | サーバーが l10n を読むのは**メール一覧だけ**。`TrxMailboxRepository` が `with(['mstMailbox.message.l10n', ...])` で、一覧の取得時に `mst_message__l10n` をまとめて DB から SELECT する（Redis のキャッシュは通らない）。`MailboxItemAssemblyService` はその結果から `ApiSession::getLanguage()` の言語を選ぶ（無ければ既定 `ja`）。ミッション・交換のモデル（`MstMissionL10n` など）は定義されているが、応答の `ListResponse` は名前を載せず、クライアントがマスターから引く |
| `MstItemRepository` | `MstItem::class` を指定するだけ。`selectById`・`selectListByIds`・`selectWalletManaged` が `queryOrMemory()` のキャッシュを使う。**`mst_item` はアイテムの付与・消費のたびに読まれる**（`isWalletManaged` ほか） |

**設計（人の提案ルール: 「l10n は親テーブルを eager load して、親と一緒にキャッシュする」）**

- 基底 `_BaseMstRepository`（`packages/nexus-core`）に、`protected array $eagerLoad = []` のような指定を足し、`queryOrMemory()` の読み込み（`Model::all()`）を `Model::with($this->eagerLoad)->get()` にする。**指定が空なら今と同じ動き**で、他の Repository は変わらない。
- `MstItemRepository` は `$eagerLoad = ['l10n']` を指定する。これで `mst_item` の全行と各行の `l10n`（`mst_item__l10n`）が 1 つのキャッシュ（`mst:mst_item:all`）に入り、読み手が `$mstItem->l10n` を引いても追加の SELECT が出ない。N+1 にならない。
- 読み手は `ApiSession::getLanguage()` の言語で `l10n` から選ぶ（無ければ `ja`。`MailboxItemAssemblyService` と同じ流儀）。
- 影響: キャッシュの 1 キーが大きくなる（29 行 × 言語数。`ja` だけなら 29 行増）。`mst_item__l10n` だけを差し替えたときは、親（`mst_item`）のキャッシュを消す必要がある（`clearCache()` は親のキーを消すので、そのまま効く。マスターの再デプロイでキャッシュが作り直される運用は変わらない）。
- **この機能の API は l10n を読まない**ので、適用の効果は将来の読み手向け（U-S13。人が「読み手が出るまで入れない」を選んでもよい）。
- **S3 への申し送り**: 規約として `.claude/coding-standards.md`（l10n の節）または `.claude/database.md` 6 節に「l10n は親を eager load して一緒にキャッシュする（`$eagerLoad`）」と明文化する。今回は規約ファイルを触らない。既存の `MstMessage` は `TrxMailboxRepository` の `with()` で読む形のままにする（今回は対象外。揃えるかは別の機会）。
