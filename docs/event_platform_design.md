# イベント基盤 設計

| 項目 | 内容 |
| --- | --- |
| 状態 | 設計中（実装前）。決定事項は「8. 決めたこと」 |
| 作成日 | 2026-11-05 |
| 前提のコミット | `85027792` (main) |
| 範囲 | フェーズ1（束ねる + 期間 + Walletアイテム）、フェーズ2（イベント期間中だけ数えるミッション）、フェーズ3（`nexus-ranking`） |

---

## 1. 決定事項（2026-11-05）

| # | 決定 |
| ---: | --- |
| 1 | 期間の正本は `mst_event` の `start_at` / `end_at` / `exchange_end_at`。`mst_event_content` に登録した子コンテンツの期間は、**子自身の `start_at` / `end_at` を無視して、イベントの期間で上書きする**。**同じコンテンツを複数のイベントで使い回す**ことを前提にする |
| 2 | イベントで使うアイテムは、イベントごとに別とは限らない（同じアイテムを使い回す）。使い回すときは**イベント開始時に所持数を0にリセット**する。イベント専用のガチャチケットや収集アイテムは `mst_item` に登録して `is_wallet = true` にし、Wallet（期限付き・先入先出）で運用する |
| 3 | フェーズ2・3も作る。ランキングは `nexus-ranking` として基本設計をパッケージ化する |
| 4 | 交換期間の終わりを `exchange_end_at` というカラム名で持つ |

---

## 2. 現状（設計の前提）

| 項目 | 現状 |
| --- | --- |
| 期間の持ち方 | ガチャ・ミッション・交換所・ログインボーナス・リワードトラックが、それぞれのマスターに `start_at` / `end_at` を持つ |
| 期間の判定 | 5機能に分散していて、方式も揃っていない。SQL の `where` で絞るもの（`LoginBonusRepositoryAdapter`、`RewardTrackMasterRepository`）と、メモリ上で `ClockUtility` を使って比べるもの（`GachaValidationService`、`MissionService`、`TradeService`）がある |
| Wallet | `mst_item.is_wallet = true` のアイテムは `trx_item` ではなく Wallet に入り、取得単位の有効期限と先入先出の消費ができる。**ただし期限切れの除去（`removeExpiredCurrency`）は、api にも packages にも呼び出し元が無い**。消費・残高は、期限切れを含む `total_amount` を見る（W1） |
| Wallet の入口 | `mst_item` のアイテムは api の `ItemService`（付与・消費・取得）、通貨・ポイントは `CurrencyDeliveryHandler` / `PointsDeliveryHandler`（`WalletService` を直接使う） |
| 報酬の期限 | `ResourceDeliveryContent::getExpireAt()` があり、`PointsDeliveryHandler` は付与時の有効期限として使う |
| ミッションの進捗 | `trx_progress` は通算（主キーは `sys_player_id, trigger_type, sub_target_id`）。出来事は、どのミッションがあるかに関係なく数える。受け取りは `trx_mission`（主キーは `sys_player_id, mst_mission_id`） |
| マスター配信 | 運営ツールが mst の各テーブルを SQLite に書き出す（`tool/app/Services/MasterDataExporter.php`。テーブル単位）。`mst_event*` はテーブルを足すだけで配信される。クライアントは SQLite を丸ごと持つ |
| ランキング | 仕組みは無い |

### W1. 期限切れの Wallet 残高が消えない（先に直す）

`WalletService::consumeCurrency()` は `walletRepository->selectByCurrencyId()` の `total_amount` で残高を判定し、期限切れの行も残高に数える。`removeExpiredCurrency()` を呼ぶ処理がどこにも無いため、**有効期限を付けて配った通貨は、期限が過ぎても残る**。
イベントのポイントやチケットに `exchange_end_at` を有効期限として付けても、このままでは消えない。フェーズ1の前提として、`WalletService` が残高の取得・消費の前に期限切れを取り除く（または、期限切れを残高から除いて数える）よう直す。

---

## 3. 期間の仕組み（決定1）

### 3.1 期間

```
start_at ─────────────── end_at ───────── exchange_end_at
   │        プレイ期間        │   交換期間    │
   開催前 │      開催中       │  交換期間中   │   終了
```

- `end_at`: ポイントの獲得、イベント内のガチャ、進捗の加算の締め
- `exchange_end_at`: 交換所での交換、報酬の受け取りの締め。`end_at` 以降の日時で、`end_at` と同じ値にもできる（交換期間なし）
- いずれも `Y-m-d H:i:s` の文字列

### 3.2 子コンテンツの期間を上書きする

子コンテンツ（`mst_event_content` に登録されたもの）は、自分の `start_at` / `end_at` を持っていても、**イベントの期間で上書きされる**。コンテンツの種類ごとに、イベントのどの時刻を子の `end_at` として使うかが決まる。

| `content_type` | 子の `start_at` ← | 子の `end_at` ← | 補足 |
| --- | --- | --- | --- |
| `gacha` | イベントの `start_at` | `end_at` | |
| `login_bonus` | `start_at` | `end_at` | |
| `reward_track` | `start_at` | `end_at` | 進捗の加算の締め |
| `trade` | `start_at` | **`exchange_end_at`** | 交換所は交換期間まで使える |
| `mission` | `start_at` | `exchange_end_at` | 進捗の加算は `end_at` まで、報酬の受け取りは `exchange_end_at` まで（フェーズ2で扱う） |
| `ranking` | `start_at` | `end_at` | 順位は `end_at` で動かなくなる。報酬の受け取りは `exchange_end_at` まで（ランキングの `exchange_end_at` も、イベントの `exchange_end_at` で上書きする。フェーズ3） |

### 3.3 上書きの実装：マスターを読む境界で解決する

各機能の判定ロジックは変えず、**api 側のマスター読み取り（`*RepositoryAdapter`）が、子の期間をイベントの期間に差し替えて返す**。

```
Gacha / Mission / Trade / LoginBonus / RewardTrack の Adapter
        │  子のマスター行を読む
        ▼
EventPeriodResolver::resolve(content_type, content_mst_id, now)
        │  mst_event_content から、そのコンテンツを含むイベントを引く
        ▼
  イベントがある → start_at / end_at を差し替えた DTO を返す
  イベントが無い → 子自身の start_at / end_at のまま返す（今までどおり）
```

- パッケージ（`nexus-gacha` など）は `nexus-event` を知らない。差し替えは api の Adapter だけで行うので、パッケージの依存は増えない
- SQL で期間を絞っている2か所（`LoginBonusRepositoryAdapter`、`RewardTrackMasterRepository`）は、期間の条件を SQL から外し、差し替えた後に PHP で判定する形に直す。イベントの期間は SQL の `where` では表せないため
- 「どのイベントを採用するか」は、`EventPeriodResolver` だけが決める（3.4）

### 3.4 イベントの期間は重ならない／同じコンテンツを複数のイベントで使い回す

**全てのイベントの期間（`start_at` から `exchange_end_at`）は重ならない。同時に開催中（交換期間中を含む）のイベントは、常に最大1つ**。次のイベントは、前のイベントの `exchange_end_at` より後にしか始められない（`start_at > 前の exchange_end_at`。同時刻も不可）。

同じ `content_type` + `content_mst_id` は、複数のイベントに登録してよい（使い回し）。イベントの期間が重ならないので、現在時刻に対して採用するイベントは、次の順で1つに決まる。

1. 開催中または交換期間中のイベント（`start_at <= now <= exchange_end_at`）。重ならないので、あっても1つ
2. 無ければ、次に始まるイベント（`start_at` が最も近い未来）
3. 無ければ、直近に終わったイベント（`exchange_end_at` が最も新しい過去）

- イベントの数は少なく、開催中は最大1つなので、`EventPeriodResolver` はリクエストごとに `mst_event` / `mst_event_content` を1回だけ読んで使い回せる（コンテンツごとに SQL を発行しない）
- 常設のコンテンツ（期間限定でないもの）は `mst_event_content` に入れない。入れると、イベントの期間の外では使えなくなる

### 3.5 マスター取り込み時の検査

運営ツールのマスターデプロイで、次を検査して、違反があれば取り込みを止める。

- `start_at <= end_at <= exchange_end_at`
- `content_type` が上の表の値で、`content_mst_id` が実在する
- 全てのイベントの期間が重なっていない（`start_at > 前のイベントの exchange_end_at`）
- `mst_event_item` の `mst_item_id` が実在し、`is_wallet = true`

### 3.6 クライアントも同じ規則で解決する

マスターはクライアントが丸ごと持つので、クライアントは `mst_event` と `mst_event_content` から、3.2〜3.4 と同じ規則でコンテンツの期間を求められる。サーバーは `GET /api/event/list` に、サーバー時刻での段階を返す（クライアントの時計のずれを避けるため）。規則はクライアント側にも伝える必要がある。

---

## 4. イベントのアイテムと所持数のリセット（決定2）

### 4.1 テーブル

`mst_event_item` に、そのイベントが管理する Wallet アイテムを登録する。

| 列 | 内容 |
| --- | --- |
| `mst_event_id` | `mst_event.id` |
| `mst_item_id` | `mst_item.id`（`is_wallet = true` のもの） |

### 4.2 付与するときの有効期限

イベントのアイテムは、**付与するときの有効期限を、そのイベントの `exchange_end_at` にする**。`ItemService` が、付与するアイテムが `mst_event_item` に登録されていれば、有効期限を差し替える。報酬側が別の有効期限を持っていても、イベントのものが優先される。
W1 を直せば、`exchange_end_at` を過ぎたものは消える。

### 4.3 開始時のリセット

同じアイテムを次のイベントでも使うため、前のイベントの残りが次に混ざらないようにする。**リセットは、プレイヤーごとに、そのイベントのアイテムに最初に触れたときに行う**（全プレイヤーを開始時刻に一斉に処理することはできない。プレイヤーはシャードに分かれている）。

- `trx_event`（`sys_player_id`, `mst_event_id`, `entered_at`）を「リセット済み」の印にする。行が無く、かつイベントが開始している（`start_at <= now`）なら、リセットして行を作る
- リセットは、そのイベントの `mst_event_item` の各アイテムについて、期限切れを除いた残高を全て消費して0にする。捨てた数量は `log_action_event_reset` に残す
- 印の作成とリセットは、同じトランザクションで行う（再実行しても、二重にリセットしない）
- 触れたときとは、`ItemService` の付与・消費・取得、`GET /api/event/list`、イベントのポイントを配る処理。入口が `ItemService` に集まっているので、ここに `EventItemEntryInterface::ensureEntered(sys_player_id, mst_item_id)` を呼ぶ。`CurrencyDeliveryHandler` / `PointsDeliveryHandler` は `mst_item` を経由しないため、イベントのアイテムをこれらで配らない運用にする
- イベントの期間は重ならない（3.4）ので、同じアイテムを使うイベントのリセットが衝突しない。前のイベントの残りは、`exchange_end_at` で有効期限が切れるか、次のイベントで最初に触れたときのリセットで消える

**リセットの対象は、そのアイテムの全残高**（そのイベントの外から得たものも含む）。イベント専用のアイテムとして扱うことが前提。

### 4.4 mst_event_item の詳細

| 列 | 型 | 内容 |
| --- | --- | --- |
| `deploy_key` | integer | デプロイキー（mst 共通。index） |
| `mst_event_id` | string | `mst_event` のID |
| `mst_item_id` | string | `mst_item` のID。`is_wallet = true` のもの。index（`ItemService` が、アイテムからイベントを引くため） |
| `created_at` / `updated_at` | dateTime | 規約どおり |

- 主キーは `(mst_event_id, mst_item_id)`（名前は `pk_event_item`）。同じアイテムを複数のイベントに登録してよい（使い回し）。イベントの期間は重ならないので、リセットは衝突しない
- 登録するアイテムの数に制限は無い（ガチャチケット、収集アイテム、ポイントなど）。**登録されていること自体が「このイベントのアイテム」という意味**で、リセットと有効期限の対象になる
- **リセットしない通貨（ゴールド、ショップコインなど、ダイヤ以外の永続の Wallet 通貨）は、`mst_event_item` に登録しない**。リセットも有効期限の差し替えも、登録したアイテムだけに働くので、登録しなければ、今までどおり残高が残り、有効期限は付与のときに指定した値（`null` なら無期限）のまま。登録した時点で、そのアイテムは**全てのイベントの開始でリセットされ、期間外は付与できなくなる**（イベント専用のアイテムになる）。永続の通貨と、イベント専用のアイテムを、同じ `mst_item` で兼ねることはできない
- 登録していてもリセットしたくないアイテムが必要になったら、`mst_event_item` に `reset_at_start`（bool、既定 true）を足す。今は、そのようなアイテムが無いので持たない（`end_at` の後は、有効期限の差し替えで、どのみち `exchange_end_at` に消える）
- アイテムの役割（チケットか収集アイテムか）は、サーバーでは使わないので持たない。表示の都合で必要になったら、クライアントに必要な列を足す
- `mst_event` に `mst_item_id` を1列持たせる形にしないのは、1つのイベントが複数のアイテムを持つため（チケットと収集アイテムなど）。1列だと、アイテムが2つ以上のイベントを表せず、リセットと有効期限の対象を1つにしか指定できない
`ItemService` が、プレイヤーがアイテム `X` に触れたとき（付与・消費・取得）に、次の順で動く。

1. `mst_event_item` から、`X` を持つイベントのうち、**今が `start_at` から `exchange_end_at` の間にあるイベント** `E` を探す（イベントの期間は重ならないので、あっても1つ）
2. 無ければ、`X` は今はどのイベントにも属さない（通常の Wallet アイテムとして扱う。付与の期間は下の「付与できる期間」）
3. あれば、`trx_event` に `(プレイヤー, E)` の行があるか見る。無ければ、`E` の**全ての** `mst_event_item` の残高を0にして、行を作る（`X` だけでなく `E` のアイテム全部。印はイベント単位なので、一部だけ残らない）
4. 付与なら、有効期限を `E` の `exchange_end_at` に差し替える

例（`event_a`: 11/10〜11/17（end_at）、11/20（exchange_end_at））:

| mst_event_id | mst_item_id |
| --- | --- |
| `event_a` | `item_event_point` |
| `event_a` | `item_event_gacha_ticket` |
| `event_a` | `item_event_shard` |

次のイベント `event_b`（12/01〜）が同じ `item_event_point` を使うなら、`event_b` にも同じアイテムで行を足す。`event_b` が始まって最初に触れたとき、前のイベントの残りは0になる。

**付与できる期間**: `start_at` から `exchange_end_at` までは付与できる（交換期間中のミッション報酬などで配られるため）。それ以外の時刻に付与しようとしたときは、**業務エラー（`EVENT_ITEM_NOT_AVAILABLE`）にする。ただし、そのアイテムを配ろうとした受け取り元の操作は、完了させる**（4.5）。

### 4.5 期間外の付与：エラーを返しつつ、同じ受け取りが再び起きないようにする

イベントのアイテムを期間外に付与しようとしたとき、**エラーは返すが、受け取り元の操作は完了させる**。たとえば、バトル終了 API の報酬にイベントのアイテムが含まれていて期間外だったとき、エラーを返したうえで、バトル中セッションの削除や、他の報酬の付与は済ませる。そうしないと、クライアントが同じバトル終了を再送しても、同じエラーが返り続け、セッションが残ったままになる。

トランザクションの中で例外を投げると全接続がロールバックされる（`.claude/architecture.md` 7節）ので、**エラーはコミットの後に投げる**。

1. `ItemService::addItem()` が、期間外のイベントのアイテムを見つけたら、**例外を投げず、そのアイテムの付与だけを飛ばす**
2. 飛ばしたことを `log_action_event_item_rejected` に残す（トランザクションの一部としてコミットされる）
3. `EventItemNotAvailableException`（`GameException` 系、`GameErrorCode::EVENT_ITEM_NOT_AVAILABLE`）を、リクエストスコープの `DeferredGameErrors` に登録する
4. `executeWithTransaction()` が**全接続をコミットした後**に、`DeferredGameErrors` に登録されたエラーがあれば、最初の1件を投げる（`UseCaseTrait` に足す）
5. `_BaseController::execute()` が、いつもどおり HTTP 299 の `{error_code, message}` に変換する

結果:
- セッションの削除、他の報酬の付与、ミッションの受け取り済みの記録などは、コミットされる。クライアントが再送しても、同じ受け取りは起きない
- 冪等キャッシュは HTTP 2xx（299を含む）を保存するので、同じ `X-Unique-Request-Identifier` の再送には、同じエラーが返り、処理は再実行されない
- 飛ばしたイベントのアイテムは、救済しない。`log_action_event_item_rejected`（`sys_player_id`, `mst_item_id`, `free_amount`, `paid_amount`, `reason`（`out_of_period`）, `mst_event_id`（`3.4` の規則で採用されるイベント。無ければ null）, `created_at`）が、CS の調査の手がかりになる
- **クライアントは、このエラーを受けても、操作は完了している**ものとして、画面の状態（所持品、セッション）を取り直す必要がある（`.claude/api.md` に書く）
- 付与の入り口は `ItemService::addItem()` だけなので、バトル終了、ミッション、ガチャなど、どの受け取り元でも同じ仕組みが働く

---

## 5. フェーズ1：束ねる + 期間 + アイテム

### 5.1 テーブル

```
mst_event
  deploy_key, id (string PK),
  start_at, end_at, exchange_end_at,
  sort_order, created_at, updated_at

mst_event__l10n
  mst_event_id, language (PK), name, description

mst_event_content
  deploy_key, mst_event_id, content_type, content_mst_id, sort_order
  PK (mst_event_id, content_type, content_mst_id)

mst_event_item
  deploy_key, mst_event_id, mst_item_id, created_at, updated_at
  PK (mst_event_id, mst_item_id)   詳細は 4.4

trx_event                   （プレイヤーのシャード。イベントごとのリセット済みの印）
  sys_player_id, mst_event_id, entered_at, is_delete, created_at, updated_at
  PK (sys_player_id, mst_event_id)

trx_event_score             （プレイヤーのシャード。イベントごとの得点。ランキングの有無にかかわらず持つ）
  sys_player_id, mst_event_id,
  total_score           累計の得点（unsigned bigint、既定 0）
  high_score            ハイスコア（unsigned bigint、既定 0）
  high_score_detail     ハイスコアの付属情報（json、null 可。クリアパーティなど。ハイスコアが更新されたときに置き換える）
  total_scored_at       累計が最後に増えた日時（null 可）
  high_scored_at        現在のハイスコアに達した日時（null 可）
  is_delete, created_at, updated_at
  PK (sys_player_id, mst_event_id)

log_action_event_item_rejected （プレイヤーと同じシャード。期間外で付与を飛ばした記録。4.5）
  sys_player_id, mst_item_id, free_amount, paid_amount, reason, mst_event_id (null 可), created_at

log_action_event_reset      （プレイヤーと同じシャード）
  sys_player_id, mst_event_id, mst_item_id, discarded_free_amount, discarded_paid_amount, created_at
```

最初の案の `close_at` と `point_mst_item_id` は、`exchange_end_at` と `mst_event_item`（役割を持たない、アイテムの一覧）に置き換えた。

### 5.2 パッケージと層

- `packages/nexus-event`
  - `EventService`: 開催中のイベントの検索、段階（開催前 / 開催中 / 交換期間中 / 終了）の判定
  - `EventPeriodResolver`: 3.4 の規則（コンテンツ → 採用するイベント → 差し替える期間）
  - `EventScoreService`: イベントの得点を記録する（5.4）
  - `EventMasterRepositoryInterface`、`EventEntryRepositoryInterface`、`EventScoreRepositoryInterface`、`EventLogRepositoryInterface`
  - ガチャやミッションのパッケージは参照しない（`content_type` は文字列のデータ）
- `api/app/Domain/Event`: `ListUseCase`、`EventItemEntryService`（4.3）、`Contracts\EventItemEntryInterface`
- api 側の Adapter が、`*RepositoryAdapter` で `nexus-event` の契約を実装する。5機能のマスター読み取りの Adapter は `EventPeriodResolver` を使う
- API: `GET /api/event/list`。開催中・交換期間中のイベントと、サーバー時刻での段階、`exchange_end_at`、イベントのアイテムの残高を返す。マスターはクライアントが持っているので、サーバー時刻の段階とプレイヤーの状態に絞る

### 5.3 作業の順

1. W1（`WalletService` の期限切れ）を直す
2. テーブルと `nexus-event`
3. `EventPeriodResolver` を5機能の Adapter に入れる（機能ごとに、イベントに入っているコンテンツで期間が差し替わるテストを足す）
4. `mst_event_item`、付与時の有効期限、開始時のリセット
5. マスター取り込み時の検査（3.5）
6. `GET /api/event/list`
7. `trx_event_score` と `EventScoreService`（5.4）

### 5.4 イベントの得点（ランキングの有無にかかわらず持つ）

イベントの得点は、**ランキングが無いイベントでも**、累計とハイスコアの両方を `trx_event_score` に持つ。ランキングは、この得点を順位に変換する、あってもなくてもよい層。

- `EventScoreService::recordScore($mstEventId, $sysPlayerId, $value, $detail = null)`: 1回の呼び出しで、**累計とハイスコアの両方を更新する**
  - 累計: `total_score += $value`、`total_scored_at` を現在時刻にする
  - ハイスコア: `$value > high_score` のときだけ、`high_score = $value`、`high_score_detail = $detail`、`high_scored_at` を現在時刻にする。同じ値や低い値では更新しない（付属情報も、最高のときのものが残る）
- 何を得点として記録するかは、ドメインが決める（ステージのスコア、イベントポイントの獲得量など。イベントポイントを得点にするなら、獲得したときのリスナーが `recordScore` を呼ぶ）
- 記録できる期間は、イベントの `start_at` から `end_at` まで（プレイ期間。3.1）
- 得点を更新したら、`EventScoreUpdated` のドメインイベントを出す。ランキングを使うイベントでは、api のリスナーがこれを受けて、順位表に登録する（7.3）

---

## 6. フェーズ2：イベント期間中だけ数えるミッション

今の `trx_progress` は通算で、「イベント中に100回」を数えられない。

- `trx_progress` と `trx_mission` に `scope` を足す。値は `lifetime` か `event:{mst_event_id}`。主キーは `(sys_player_id, scope, trigger_type, sub_target_id)` と `(sys_player_id, scope, mst_mission_id)` にする。**リリース前なので、既存のマイグレーションを書き換える**（CLAUDE.md の運用）
- ミッションがイベントに入っているか（`mst_event_content` の `content_type = mission`）で、どちらの進捗を読むかが決まる。ミッションのマスターに `mst_event_id` は足さない（使い回しのため。同じミッションが別のイベントに入るときは、イベントごとの `scope` で別々に数え、受け取りも別になる）
- 出来事が起きたら、`lifetime` と、**プレイ期間中（`start_at <= now <= end_at`）のイベント（最大1つ）が、その `trigger_type` のミッションを持つなら、そのイベント**の `scope` を進める
- 進捗の加算は `end_at` まで、報酬の受け取りは `exchange_end_at` まで
- 後から足したイベントは、足した時点から数える（通算のように過去にさかのぼれない）。通算の「どのミッションがあるかに関係なく数える」とは、ここが違う

---

## 7. フェーズ3：`nexus-ranking`

### 7.1 方針

`nexus-ranking` は、**順位表（Redis）・報酬の受け取り・一覧だけ**を持つ汎用のパッケージにする。バッチや確定の処理は持たない（7.4）。イベントに属さない**常設のランキングも想定している**（現在は無い。7.7）。得点そのものは持たず、ランキングは「プレイヤーごとの得点を1つ受け取って、順位に変える」。

- 得点は、イベントの `trx_event_score`（5.4）が、ランキングの有無にかかわらず常に持つ（累計とハイスコアの両方）
- ランキングを使うイベントだけ、**どちらの得点を順位にするかを、ドメインのコードが選んで**、パッケージに渡す（マスターにカラムは持たない）
- 順位表に登録するのは、ランキングを使うときだけ。ランキングが無いイベントでは、Redis を使わない
- イベントとの結び付きは、`mst_event_content`（`content_type = ranking`）で表す。パッケージはイベントを知らない

| 渡す得点 | 元 | 同点のときの順位 |
| --- | --- | --- |
| 累計 | `trx_event_score.total_score` | `total_scored_at` が早い方が上位 |
| ハイスコア | `trx_event_score.high_score` | `high_scored_at` が早い方が上位 |

どちらも「大きいほど上位」（小さいほど上位のタイムアタックなどは範囲外）。

**前の案からの変更**: `nexus-ranking` が `recordTotal` / `recordHighScore` を持つ案をやめた。累計とハイスコアの更新は `EventScoreService` が一度に行い、ランキングは選んだ方の値を受け取るだけにした。ランキングが無いイベントでも得点を残せ、同じ得点から累計とハイスコアの2種類のランキングを作ることもできる。

**どちらの得点を使うか**は、api のコード（`EventRankingScoreSource` のようなクラス、または設定の対応表。`mst_ranking_id` → `total` / `highscore`）で持つ。

### 7.2 テーブル

```
mst_ranking
  deploy_key, id (string PK),
  start_at, end_at (null 可), exchange_end_at (null 可)   イベントに入っていればイベントの期間で上書きされる（3.2）。null は終わりなし / end_at と同じ（7.7）
  display_limit         一覧に出す上位の人数
  sort_order, created_at, updated_at

mst_ranking__l10n       mst_ranking_id, language (PK), name

mst_ranking_reward
  deploy_key, mst_ranking_id, rank_from, rank_to,
  + 報酬の中身（nexus-trade の mst_trade_product_content と同じ形）
  PK (mst_ranking_id, rank_from)

trx_ranking             （プレイヤーのシャード。報酬を受け取った印と、そのときの順位・得点）
  sys_player_id, mst_ranking_id, final_rank, final_score, rewarded_at, is_delete, created_at, updated_at
  PK (sys_player_id, mst_ranking_id)

```

- 得点は `trx_event_score`（5.1）にあり、ランキング側には持たない
- `trx_ranking` は、**プレイヤーが報酬を受け取るとき**に、メールボックスの INSERT と同じシャードのトランザクションで作る。報酬の対象外の順位のプレイヤーには作らない
- ランキングのために sys のテーブルは持たない（確定の処理が無いため。7.4）

### 7.3 順位表への登録

- ライブの順位表は Redis の sorted set（`ranking:{mst_ranking_id}`）に持つ。**ランキングを使うイベントだけ**に作る
- `EventScoreUpdated` を受けた api のリスナーが、そのイベントの `mst_event_content` から `content_type = ranking` のランキングを引き、各ランキングについて、ドメインが選んだ得点（累計かハイスコア）の**現在値を絶対値で `ZADD GT` する**（加算ではなく上書き。`GT` は、今より大きいときだけ更新する）。正本は `trx_event_score` なので、`ZINCRBY` のような差分更新をしない。Redis とのずれが積み重ならず、同じ更新を2回登録しても、再構築と同時に動いても、古い値で上書きしない（得点は増える一方で、同点の判定に使う時刻を含めた値も、得点が上がれば大きくなる）
- 同点は、**先にその得点に達した人が上位**。`total_scored_at` / `high_scored_at` が早い方を上にする。sorted set の値への埋め込み方（精度の範囲内に収める）は実装時に決める
- 順位表の寿命: 最初に登録するときに、`EXPIREAT ranking:{id}` を、イベントの `exchange_end_at` の7日後にする（受け取りの期限の後、念のために残す）
- Redis を失ったとき、またはイベントの途中でランキングを足したときは、`ranking:rebuild {mst_ranking_id}` で、各シャードの得点から作り直す（得点の取り出しは、ランキングごとの `RankingScoreSourceInterface` の実装が行う。イベントのランキングは `trx_event_score`。7.7）。**定期実行はしない手動の運用コマンド**で、得点は `ZADD GT` で入れるので、イベントの進行中に流しても安全
- ランキングを使うイベントでも、ランキングに載るのは、1回でも得点を記録したプレイヤーだけ

### 7.4 終了後と報酬の受け取り（バッチは持たない）

- `end_at` を過ぎると、`EventScoreService` は得点を受け付けない（5.4）ので、順位表は動かなくなる。**確定の処理も、バッチも、スケジューラも無い**。一覧は Redis をそのまま読む
- 順位が最終になるのは、**`end_at` + 猶予（60秒。設定値）**。猶予は、`end_at` の直前に始まったリクエストの `ZADD` が終わるのを待つため。`end_at` から猶予の終わりまでの間は、一覧は `phase = finalizing` を返し、報酬は受け取れない
- 報酬は、**プレイヤーが `POST /api/ranking/reward/receive` を呼んだときに、そのプレイヤー分だけ発行する**（ミッションの報酬と同じ受け取り方式）。クライアントは、ホーム画面やイベント画面で、終了したランキングについて自動で呼ぶ
- 受け取れる期限は、イベントの **`exchange_end_at`**（3.2 のミッションと同じ）
- **配ったかどうかの印は `trx_ranking`**（プレイヤーのシャード）。メールボックスへの INSERT と `trx_ranking` の作成が、同じシャードの1つのトランザクションなので、二重に配らず、配り漏れもしない。sys とシャードの2つのDBにまたがる更新が無い
- 受け取らないままのプレイヤーには、報酬は届かない。期限を過ぎたら、順位表は `exchange_end_at` の7日後に消える

### 7.5 範囲外

- ギルド対抗などの集団のランキング（個人だけ）
- 日次・週次のランキング（得点は、ランキングの期間の通しだけ。必要になれば、`mst_ranking` を期間ごとに複数登録すれば足りる）
- 小さいほど上位になるランキング
- 常設のランキング（今は作らない。構造は想定してある。7.7）
- 不正の検出

### 7.6 API と受け取りの詳細

#### `GET /api/ranking/info`（`mst_ranking_id`）

| キー | 内容 |
| --- | --- |
| `mst_ranking_id` | 指定したランキング |
| `phase` | `playing`（`end_at` まで）/ `finalizing`（`end_at` から猶予の終わりまで）/ `final`（猶予の後） |
| `ranking_list` | 上位 `display_limit` 人。要素は `rank`、`my_id`、`name`、`score`（`my_id` で他のプレイヤーを指す。`.claude/api.md`） |
| `my_rank` / `my_score` | 自分の順位・得点。参加していなければ `null` |
| `is_reward_received` | 受け取り済みか（`trx_ranking` の有無） |

- 順位表は `ZREVRANGE`、自分の順位は `ZREVRANK`、プレイヤー名は `sys_player` から引く（`display_limit` 人分だけ）
- 順位表が無い（Redis を失った）ときは、`RANKING_NOT_READY` を返す。`ranking:rebuild`（7.3）で戻す

#### `POST /api/ranking/reward/receive`（`mst_ranking_id`）

1. `phase` が `final` で、今が `exchange_end_at` 以前か確かめる（有効な期間は、`EventPeriodResolver`（3.3）が返すイベントの期間）
2. Redis から順位（`ZREVRANK` + 1）と得点を取る。参加していなければ `RANKING_NOT_PARTICIPATED`
3. `mst_ranking_reward` から、順位に当たる報酬を引く
4. `executeWithTransaction()` の中で、`trx_ranking` が既にあれば `RANKING_REWARD_ALREADY_RECEIVED`。無ければ、報酬をメールボックスに入れ、`trx_ranking`（`final_rank`、`final_score`、`rewarded_at`）を作る
5. 報酬の対象外の順位なら、報酬は空（`reward_list: []`）で成功にし、`trx_ranking` は作らない

- 同時に2回呼ばれても、`trx_ranking` の主キー `(sys_player_id, mst_ranking_id)` が重複して、コミット全体（メールの INSERT を含む）が失敗するので、二重には配られない。通常の再送は `X-Unique-Request-Identifier`（冪等キー）で、同じレスポンスを返す
- レスポンスは `mst_ranking_id`、`rank`、`score`、`reward_list`

#### 新しいエラーコード（`GameErrorCode` に追加）

`RANKING_NOT_FOUND`、`RANKING_NOT_READY`、`RANKING_NOT_FINISHED`（`phase` が `final` でない）、`RANKING_RECEIVE_EXPIRED`（`exchange_end_at` の後）、`RANKING_NOT_PARTICIPATED`、`RANKING_REWARD_ALREADY_RECEIVED`

### 7.7 常設のランキングの想定（今は作らない）

イベントに属さない常設のランキングは、現在は無いが、後から足せるようにしておく。`nexus-ranking` はイベントを知らないので、パッケージの変更は要らない。足すときに必要なものは次のとおり。

| 要るもの | 内容 |
| --- | --- |
| 得点の置き場 | 得点を持つドメインが、自分のテーブルで持つ（イベントなら `trx_event_score`）。`nexus-ranking` は得点を持たない |
| 期間 | `mst_ranking` が自分の `start_at` / `end_at` / `exchange_end_at` を持つ。イベントに入っていれば、3.2 のとおりイベントの期間で上書きされる。イベントに入っていなければ、自分の期間のまま使う（`EventPeriodResolver` の既定の動き。3.3） |
| 順位表への登録 | 得点を更新するドメインが、`RankingBoard::register()` を直接呼ぶ（イベントのランキングは、`EventScoreUpdated` のリスナーが呼ぶ） |
| 再構築 | そのランキングの `RankingScoreSourceInterface` の実装を足す（`ranking:rebuild` が、これを通して得点を読む） |
| 報酬 | 受け取りの期限は、`mst_ranking.exchange_end_at`（null なら `end_at`）。ランキングの期間の終わりごとに、受け取りができる |

- `mst_ranking` の `end_at` と `exchange_end_at` は、**null 可**にしておく（終わりのない常設のランキング）。`end_at` が null のランキングは、順位は更新され続け、報酬は受け取れない（`phase` は常に `playing`）
- 期間が季節ごとに区切られる常設のランキング（シーズン制）は、シーズンごとに `mst_ranking` を1行ずつ足す（`mst_ranking_id` が別なので、順位表も別になる）

---

## 8. 決めたこと（2026-11-05）

| # | 内容 | 決定 |
| ---: | --- | --- |
| 1 | イベントの期間の重なり（3.4、3.5） | **全てのイベントで禁止**。同時に開催中のイベントは最大1つ。次のイベントは前の `exchange_end_at` より後に始める |
| 2 | リセットが捨てる範囲（4.3） | そのアイテムの全残高を0にしてよい |
| 3 | ミッションの受け取り期限（3.2） | `exchange_end_at`。進捗の加算は `end_at` まで |
| 4 | ランキングの順位が動かなくなる時刻 | `end_at`（確定の処理は持たない。7.4） |
| 5 | ランキングの範囲（7.5） | 個人だけ。日次・週次は範囲外 |
| 6 | 得点の持ち方（5.4、7.1） | ランキングの有無にかかわらず、累計とハイスコアの両方を `trx_event_score` に持つ（クリアパーティなどの付属情報つき）。Redis に登録するのは、ランキングを使うときだけ。どちらの得点を順位にするかは、ドメインのコードが選ぶ |
| 7 | ランキング報酬の配り方（7.4） | **受け取り方式**。確定のバッチを持たない。`sys_ranking_settlement` / `sys_ranking_result` / `ranking:settle` / スケジューラは作らない |
| 8 | テーブル名 | `trx_event_score`（得点）、`trx_ranking`（報酬を受け取った印）。`trx_player_event` は `trx_event` |
| 9 | イベントのアイテムを期間外に付与しようとしたとき（4.4、4.5） | 業務エラー（`EVENT_ITEM_NOT_AVAILABLE`）。ただし受け取り元の操作は完了させ、同じ受け取りが再び起きないようにする。エラーは**コミットの後**に投げる |
| 10 | ランキング報酬の受け取り期限と、順位が最終になるまでの猶予（7.4） | 期限は `exchange_end_at`、猶予は60秒 |
| 11 | 常設のランキング（7.7） | 現在は無いが、想定しておく。`nexus-ranking` はイベントを知らず、`mst_ranking` が自分の期間を持つ（null 可）。作るのは必要になってから |

---

## 9. 影響を受ける既存のコード

| 場所 | 変更 |
| --- | --- |
| `nexus-wallet` の `WalletService` | W1：残高の取得・消費で期限切れを除く |
| api の `ItemService` | `addItem` / `consumeItem` / `findItemAmount` が `walletItemMigrator->migrate()` を呼んでいる位置に、`ensureEntered` を呼ぶ。`addItem` は `$expireAt` を受け取るので、イベントのアイテムなら `exchange_end_at` に差し替える。期間外のイベントのアイテムは、付与を飛ばして `log_action_event_item_rejected` に残し、`DeferredGameErrors` に登録する（4.5） |
| `App\Traits\UseCaseTrait::executeWithTransaction()`、新しい `DeferredGameErrors`（`scoped`） | 全接続のコミットの後に、登録されたエラーの最初の1件を投げる（4.5）。登録が無ければ、今までと同じ動き |
| `GameErrorCode`、`.claude/api.md` | `EVENT_ITEM_NOT_AVAILABLE`、`RANKING_*` の追加。「このエラーでも操作は完了している。クライアントは状態を取り直す」ことを `api.md` に書く |
| ガチャ（`MstGachaRepository`。`GachaValidationService` が使う）、ミッション・交換所のマスター読み取りの Adapter、`LoginBonusRepositoryAdapter`、`RewardTrackMasterRepository` | マスター読み取りで `EventPeriodResolver` を使う。ガチャは package の DTO ではなく api のモデルを返すので、モデルの `start_at` / `end_at` をメモリ上で差し替える。SQL で期間を絞る2か所は PHP の判定に直す |
| `nexus-mission` | フェーズ2：`scope` の追加（`trx_progress`、`trx_mission`、`ProgressRepositoryInterface`） |
| 運営ツール | マスター取り込み時の検査（3.5） |
| `nexus-event` の `EventScoreService`、api のリスナー | 得点の記録（`trx_event_score`）と、ランキングを使うイベントでの順位表への登録（`EventScoreUpdated`） |
| `.claude/*.md`、`CLAUDE.md` | 新しいテーブル・API・パッケージの追記。`PackageRegistrationTest` のため、`nexus-event` と `nexus-ranking` を api の `composer.json`・`phpstan.neon`・`phpunit.xml` に登録 |
