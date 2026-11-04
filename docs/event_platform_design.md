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
| `ranking` | `start_at` | `end_at` | 順位の確定は `end_at` の後（フェーズ3） |

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
| `item_role` | `point`（イベントポイント。ランキングの得点の元）、`ticket`（ガチャチケット）、`collection`（収集アイテム）。ロジックを持つのは `point` だけで、他は表示用 |

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
| `item_role` | enum | `point`（イベントポイント。ランキングの得点の元）/ `ticket`（ガチャチケット）/ `collection`（収集アイテム）。mst なので ENUM でよい |
| `sort_order` | integer | クライアントでの表示順（既定 0） |
| `created_at` / `updated_at` | dateTime | 規約どおり |

- 主キーは `(mst_event_id, mst_item_id)`（名前は `pk_event_item`）。同じアイテムを複数のイベントに登録してよい（使い回し）。イベントの期間は重ならないので、リセットは衝突しない
- **`point` は1イベントに最大1つ**（取り込み時に検査する）。ランキングの得点の元が1つに決まるため
- `ticket` / `collection` は、1イベントに何個でも登録できる。ロジックは持たず、リセットと有効期限の対象になるだけ

`ItemService` が、プレイヤーがアイテム `X` に触れたとき（付与・消費・取得）に、次の順で動く。

1. `mst_event_item` から、`X` を持つイベントのうち、**今が `start_at` から `exchange_end_at` の間にあるイベント** `E` を探す（イベントの期間は重ならないので、あっても1つ）
2. 無ければ、`X` は今はどのイベントにも属さない（通常の Wallet アイテムとして扱う。付与の期間は下の「付与できる期間」）
3. あれば、`trx_event` に `(プレイヤー, E)` の行があるか見る。無ければ、`E` の**全ての** `mst_event_item` の残高を0にして、行を作る（`X` だけでなく `E` のアイテム全部。印はイベント単位なので、一部だけ残らない）
4. 付与なら、有効期限を `E` の `exchange_end_at` に差し替える

例（`event_a`: 11/10〜11/17（end_at）、11/20（exchange_end_at））:

| mst_event_id | mst_item_id | item_role |
| --- | --- | --- |
| `event_a` | `item_event_point` | `point` |
| `event_a` | `item_event_gacha_ticket` | `ticket` |
| `event_a` | `item_event_shard` | `collection` |

次のイベント `event_b`（12/01〜）が同じ `item_event_point` を使うなら、`event_b` にも同じアイテムで行を足す。`event_b` が始まって最初に触れたとき、前のイベントの残りは0になる。

**付与できる期間（確認中）**: `start_at` から `exchange_end_at` までは付与できる（交換期間中のミッション報酬などで配られるため）。それ以外の時刻に、イベントのアイテムを付与しようとしたときの扱いは、決める必要がある。この案は、**業務エラーにする**（`EVENT_ITEM_NOT_AVAILABLE`）。報酬の配布の途中で落ちることになるので、報酬の設定側（マスター取り込み時）でも、期間外に配られないように検査したい。

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
  deploy_key, mst_event_id, mst_item_id, item_role, sort_order, created_at, updated_at
  PK (mst_event_id, mst_item_id)   詳細は 4.4

trx_event                   （プレイヤーのシャード。イベントごとのリセット済みの印）
  sys_player_id, mst_event_id, entered_at, is_delete, created_at, updated_at
  PK (sys_player_id, mst_event_id)

log_action_event_reset      （プレイヤーと同じシャード）
  sys_player_id, mst_event_id, mst_item_id, discarded_free_amount, discarded_paid_amount, created_at
```

最初の案の `close_at` と `point_mst_item_id` は、`exchange_end_at` と `mst_event_item` に置き換えた。

### 5.2 パッケージと層

- `packages/nexus-event`
  - `EventService`: 開催中のイベントの検索、段階（開催前 / 開催中 / 交換期間中 / 終了）の判定
  - `EventPeriodResolver`: 3.4 の規則（コンテンツ → 採用するイベント → 差し替える期間）
  - `EventMasterRepositoryInterface`、`EventEntryRepositoryInterface`、`EventLogRepositoryInterface`
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

ランキングはイベントに限らない汎用のパッケージにする。イベントとの結び付きは、`mst_event_content`（`content_type = ranking`）と、得点を記録する側（api）で行い、パッケージは「得点を記録する・順位を引く・確定する・報酬を配る」だけを持つ。

得点の更新の規則は2種類を、パッケージに実装しておく。**どちらを使うかは、ドメイン側のコードが決める**（マスターにカラムは持たない）。

| 種類 | 更新の規則 | 記録の呼び出し | Redis |
| --- | --- | --- | --- |
| `total`（累計） | 加算する（`score += 増分`） | `recordTotal($rankingId, $playerId, $delta)` | `ZINCRBY` |
| `highscore`（最大値） | 大きいときだけ置き換える（`score = max(現在値, 新しい値)`） | `recordHighScore($rankingId, $playerId, $value)` | `ZADD GT` |

確定・報酬・一覧は2種類で共通。どちらも「大きいほど上位」（小さいほど上位のタイムアタックなどは範囲外）。

**1つのランキングで種類を混ぜない**のは、ドメイン側の責務にする。同じ `mst_ranking_id` に `recordTotal` と `recordHighScore` を混ぜて呼ぶと順位表が壊れるが、これはプログラムの誤りで、テストで検出できる。種類を DB に持つ案（`sys_ranking_state`）は、次の理由でやめた。

- 種類が必要なのは、得点を記録するときだけ。確定・報酬・一覧は、種類を使わない
- 最初の記録で行を作ると、複数のプレイヤーが同時に初回を記録して重複キーになる。書き込みは UnitOfWork のバッチ INSERT にまとまるため、1件の重複でコミット全体が失敗する

### 7.2 テーブル

```
mst_ranking
  deploy_key, id (string PK), start_at, end_at,
  display_limit         一覧に出す上位の人数
  sort_order, created_at, updated_at

mst_ranking__l10n       mst_ranking_id, language (PK), name

mst_ranking_reward
  deploy_key, mst_ranking_id, rank_from, rank_to,
  + 報酬の中身（nexus-trade の mst_trade_product_content と同じ形）
  PK (mst_ranking_id, rank_from)

trx_ranking_score       （プレイヤーのシャード。得点の正本。報酬を配った印も持つ）
  sys_player_id, mst_ranking_id, score, last_scored_at, rewarded_at, is_delete, created_at, updated_at
  PK (sys_player_id, mst_ranking_id)

sys_ranking_settlement  （全プレイヤー共通。確定の進み具合）
sys_ranking_result      （全プレイヤー共通。確定した順位）
```

sys の2テーブルの列・キー・流れは 7.6。

### 7.3 得点と順位

- 得点は `trx_ranking_score` に、プレイヤーのシャードのトランザクションで加算する（正本）
- ライブの順位表は Redis の sorted set（`ranking:{mst_ranking_id}`）に持つ。加算のたびに更新する。Redis を失っても、`trx_ranking_score` から作り直せる
- 同点は、**先にその得点に達した人が上位**。`last_scored_at` が早い方を上にする（`highscore` は、得点が上がったときだけ `last_scored_at` を更新する）。sorted set の値への埋め込み方（精度の範囲内に収める）は実装時に決める
- イベントとの結び付き: `item_role = point` のアイテムを**獲得したとき**（消費しても得点は減らない）、`EventPointEarned` のドメインイベントを出し、api のリスナーが、そのイベントの `ranking` に得点を加算する

### 7.4 確定と報酬

- `end_at` を過ぎたら、`ranking:settle` の Artisan コマンド（スケジューラが毎分起動）が確定する
- 確定は `sys_ranking_settlement` の `status` で、再実行しても二重に動かないようにする（`pending` → `running` → `done`。`running` のまま止まったら、続きから）
- 順位表は Redis から一定数ずつ読み、`sys_ranking_result` に保存する。報酬はメールボックスで配る。**配ったかどうかの印は、プレイヤーのシャードの `trx_ranking_score.rewarded_at` に置く**（メールボックスの INSERT と同じシャードのトランザクションで更新するので、途中で止まっても二重に配らず、配り漏れもしない。sys に印を置くと、sys とシャードの2つのDBにまたがってしまい、原子的に更新できない）
- 一覧 API は、`display_limit` までの上位と、自分の順位・得点を返す。プレイヤー名は `sys_player` から引く

### 7.5 範囲外

- ギルド対抗などの集団のランキング（個人だけ）
- 日次・週次のランキング（得点は、ランキングの期間の通しだけ。必要になれば、`mst_ranking` を期間ごとに複数登録すれば足りる）
- 小さいほど上位になるランキング
- 不正の検出

### 7.6 sys テーブルの詳細

sys の2テーブルは、規約（`.claude/database.md`）どおり `id` を持つ（複合主キーだけで済ませてよいのは trx / mst）。ENUM は使わず、`string` にして取りうる値をコメントに書く。値はスネークケース。

#### sys_ranking_settlement（確定の進み具合。ランキングごとに1行）

| 列 | 型 | 内容 |
| --- | --- | --- |
| `id` | bigint unsigned PK | 連番 |
| `mst_ranking_id` | string | `mst_ranking` のID。`uk_ranking_settlement_ranking` で一意 |
| `status` | string | `pending`（確定待ち）/ `running`（確定中）/ `done`（確定済み）。index |
| `saved_rank` | unsigned int | `sys_ranking_result` に保存し終えた最後の順位。止まったとき、ここから続ける。初期値 0 |
| `participant_count` | unsigned int, null | 確定時の参加人数 |
| `started_at` / `finished_at` | dateTime, null | 確定の開始・完了 |
| `created_at` / `updated_at` | dateTime | 規約どおり |

流れ:
1. `ranking:settle`（毎分）が、`end_at` を過ぎていて行が無い `mst_ranking` に、`pending` の行を作る
2. ランキングごとにロック（`Cache::lock("ranking-settle:{id}")`）を取り、`running` にして、Redis の順位表を `saved_rank` の続きから一定数ずつ読み、`sys_ranking_result` に保存して `saved_rank` を進める
3. 保存し終えたら `done` にする。続けて報酬を配る（7.4）
- 複数のサーバーが同時に起動しても、ロックが取れた1つだけが動く。行の作成は、ロックを取った後に行うので、重複しない
- `running` のまま止まったら、次の起動が `saved_rank` から続ける。保存済みの順位を書き直しても同じ値になるので、二重にはならない

#### sys_ranking_result（確定した順位。ランキング × プレイヤーで1行）

| 列 | 型 | 内容 |
| --- | --- | --- |
| `id` | bigint unsigned PK | 連番 |
| `mst_ranking_id` | string | `mst_ranking` のID |
| `sys_player_id` | unsigned bigint | `sys_player` のID |
| `rank` | unsigned int | 確定した順位（1が最上位） |
| `score` | unsigned bigint | 確定した得点 |
| `created_at` / `updated_at` | dateTime | 規約どおり |

- キー: `uk_ranking_result_player`（`mst_ranking_id`, `sys_player_id`）で一意。`index(mst_ranking_id, rank)` で、順位順の一覧と、`saved_rank` の続きの読み出しに使う
- 報酬を配った印（`rewarded_at`）は、ここには持たない。`trx_ranking_score.rewarded_at` に置く（7.4）
- 読み出しは、必ず `mst_ranking_id` で絞る。`NoFullTableSelectOnSysTest` が、sys テーブルの全件取得（`Model::all()` など）を禁止している
- 件数は、参加人数と同じだけ増える。sys は単一のDBなので、保存期間（何か月分を残すか）を決めておく。古い結果は削除してよい（確定後の結果は、報酬を配り終えていれば、一覧の表示にしか使わない）

---

## 8. 決めたこと（2026-11-05）

| # | 内容 | 決定 |
| ---: | --- | --- |
| 1 | イベントの期間の重なり（3.4、3.5） | **全てのイベントで禁止**。同時に開催中のイベントは最大1つ。次のイベントは前の `exchange_end_at` より後に始める |
| 2 | リセットが捨てる範囲（4.3） | そのアイテムの全残高を0にしてよい |
| 3 | ミッションの受け取り期限（3.2） | `exchange_end_at`。進捗の加算は `end_at` まで |
| 4 | ランキングの順位の確定時刻 | `end_at` |
| 5 | ランキングの範囲（7.5） | 個人だけ。得点の更新は `total`（累計）と `highscore`（最大値）の2種類をパッケージに実装し、どちらを使うかはドメイン側が決める。日次・週次は範囲外 |

### 確認中

| # | 内容 | この案 |
| ---: | --- | --- |
| 6 | イベントのアイテムを、`start_at` から `exchange_end_at` の外で付与しようとしたときの扱い（4.4） | 業務エラー（`EVENT_ITEM_NOT_AVAILABLE`） |
| 7 | `sys_ranking_result` を、全参加者分持つか（7.6）。持つ場合の保存期間 | 全参加者分。保存期間は運用で決める（確定後の結果は、報酬を配り終えれば表示にしか使わない） |

---

## 9. 影響を受ける既存のコード

| 場所 | 変更 |
| --- | --- |
| `nexus-wallet` の `WalletService` | W1：残高の取得・消費で期限切れを除く |
| api の `ItemService` | `addItem` / `consumeItem` / `findItemAmount` が `walletItemMigrator->migrate()` を呼んでいる位置に、`ensureEntered` を呼ぶ。`addItem` は `$expireAt` を受け取るので、イベントのアイテムなら `exchange_end_at` に差し替える |
| ガチャ（`MstGachaRepository`。`GachaValidationService` が使う）、ミッション・交換所のマスター読み取りの Adapter、`LoginBonusRepositoryAdapter`、`RewardTrackMasterRepository` | マスター読み取りで `EventPeriodResolver` を使う。ガチャは package の DTO ではなく api のモデルを返すので、モデルの `start_at` / `end_at` をメモリ上で差し替える。SQL で期間を絞る2か所は PHP の判定に直す |
| `nexus-mission` | フェーズ2：`scope` の追加（`trx_progress`、`trx_mission`、`ProgressRepositoryInterface`） |
| 運営ツール | マスター取り込み時の検査（3.5） |
| `.claude/*.md`、`CLAUDE.md` | 新しいテーブル・API・パッケージの追記。`PackageRegistrationTest` のため、`nexus-event` と `nexus-ranking` を api の `composer.json`・`phpstan.neon`・`phpunit.xml` に登録 |
