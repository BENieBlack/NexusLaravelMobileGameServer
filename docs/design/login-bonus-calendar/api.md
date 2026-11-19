# ログインボーナスのカレンダー表示とアイテムアイコン API 設計（S2）

機能 `login-bonus-calendar` のサーバーの API 設計。変えるのは (1) `POST /auth/login` の応答に**キーを 2 つ追加**、(2) **新設する 2 つの API**（`POST /login_bonus/list`・`POST /login_bonus/receive`。LoginBonus ドメイン）。

- 要件: `Nexus/docs/requirements/login-bonus-calendar.md`（**仕様の正本**。T / C / K / D / H / R / Q の節記号で指す。仕様はここに写さない）
- 全体設計: `Nexus/docs/design/login-bonus-calendar/overview.md`（マージ済み。U-1〜U-10 は 9 節で答える）
- DB: [db.md](db.md)（`sort_desc` 列の追加と、通常側の `sort_order` → `sort_desc` の置き換えのみ）、シーケンス: [sequence.md](sequence.md)
- 形式（HTTP 299 + `error_code`、`updated_resources`、全 POST）は `.claude/api.md`・`docs/api_resources.md`。login の既存項目は `docs/design/title-to-home/api.md`
- 凡例: 「読んだ」= コードを読んだだけ。実行（テスト・API 呼び出し）はしていない。サーバーは main `7cf4ddcb`

## 1. 結論（確定した名前）

| 項目 | 確定 |
| --- | --- |
| login 応答の新キー（通常） | **`login_bonus_calendar`**（オブジェクト or `null`） |
| login 応答の新キー（VIP） | **`vip_login_bonus_calendar`**（オブジェクト or `null`）。再受け取り可否 `can_re_receive` を含む |
| 新 API | `POST /login_bonus/list`、`POST /login_bonus/receive`（どちらも認証・冪等・プレイヤーロックのグループ） |
| 新しい Domain | `App\Domain\LoginBonus`（Login とは分離）。Controller は `LoginBonusController` |
| UseCase | `LoginBonus\UseCases\ListUseCase`・`ReceiveUseCase`（5 節） |
| エラーコード | 新ブロック **26000 番台**（6 節）。26001・26002・26003 |
| スキーマ | `mst_vip_login_bonus_content` に `sort_desc` を足し、通常側 `mst_login_bonus_content` の `sort_order` を `sort_desc` に置き換えるだけ（db.md。U-S2-8） |
| `login_bonus_list` | 形・順序とも変えない。ただし VIP の報酬行の並びを `sort_desc` 降順（同値は `id` 昇順）に固定する（db.md 3 節。順序が変わりうるのは「今は未指定」の場合だけ） |

## 2. 新キーの形（login・`POST /login_bonus/list`・再受け取りで共通）

「通常の段」と「VIP の段」は、どの API でも**同じ形のオブジェクト**（`null` もある）。状態は小さな読み取りに分けた `LoginBonusCalendarInterface`（5 節。スカラー・Model・パッケージの DTO だけを返す）から取り、組み合わせ（`is_granted_now` など）は UseCase、API の形（この 2 節）への整形は各 Response が行う。形は 3 つの API で同じ項目をテストして揃える（U-S2-7）。

### 2.1 通常の段 `login_bonus_calendar`

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| mst_login_bonus_id | string | 不可 | 有効な通常ログインボーナス（`type = daily`）の ID。配布と同じ引き方（`firstOpenDailyBonus`。イベント期間も見る）で 1 件 |
| last_received_day | int | **null 可** | `trx_login_bonus`（`type = daily`）の `day` をそのまま。まだ 1 度も配っていなければ null。クライアントは `is_granted_today` が真のときだけ「今日の日数」として使う（T2） |
| is_granted_today | bool | 不可 | 今日の分は配布済みか（受取日が今日のゲーム日。4 節）。今回配った場合も真 |
| is_granted_now | bool | 不可 | この応答でスタンプのアニメーションを再生する段か（4 節。login は「今回の login の自動配布が走った」、`list` は常に偽、再受け取りは VIP の段だけ真） |
| （全日の報酬） | — | — | **応答に含めない**（人の決定 U-S2-8）。クライアントが配信の SQLite のマスター（`mst_login_bonus_content`・`mst_vip_login_bonus_content`・`mst_item`）を、この段のマスター ID で引いて描く。`loop_days` もマスターから引く |

### 2.2 VIP の段 `vip_login_bonus_calendar`

通常の段の項目から `mst_login_bonus_id` を `mst_vip_login_bonus_id` に替え、次を足す。

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| mst_vip_login_bonus_id | string | 不可 | **今の VIP レベル**の有効な `mst_vip_login_bonus.id`（例 `vip_login_lv5`）。`POST /login_bonus/receive` の `mst_vip_login_bonus_id` にそのまま渡す値 |
| vip_level | int | 不可 | 今の VIP レベル（`sys_player.vip_point` から算出）。**受取済みの `trx_vip_login_bonus.vip_level` ではない**（Q6・C8b） |
| can_re_receive | bool | 不可 | 再受け取りが可能か（要件 R4 ①〜④ をすべて満たす）。ボタンを出す根拠（H4）。クライアントはレベルを比べない。通常の段には無い（D6） |
| last_received_day / is_granted_today / is_granted_now | 通常と同じ | | `trx_vip_login_bonus` の `day`・受取日から作る |

**VIP の段が `null` になる条件**（D3・T4）: 今のレベルに有効な `mst_vip_login_bonus` が無いときだけ。**通常の段が `null`** になる条件: 有効（かつイベント期間内）な通常ログインボーナスが無いときだけ。受け取り状況（未配布・今日でない）では `null` にしない。

### 2.3 全日の報酬は応答に含めない（人の決定。U-S2-8）

`day_reward_list`（`reward_list` の要素を含む）と `loop_days` は応答から外した。クライアントは `mst_login_bonus_content`（通常。`sort_desc` 降順）・`mst_vip_login_bonus_content`（VIP。`sort_desc` 降順＝大きい値が先）の行を段のマスター ID で引き、`mst_item` の `is_wallet`・type/id の読み替え（`content_type = wallet` は `content_mst_id` が type）はクライアントが汎用に行う。マスターが古いときの再取得もクライアントの責務。サーバーは**配った報酬**を従来どおり `login_bonus_list`（と receive 応答の `login_bonus_list`）で返す。

### 2.4 応答の例（VIP が 2 日目の途中でレベルが上がった状態）

```json
{
  "login_bonus_calendar": {
    "mst_login_bonus_id": "daily_login",
    "last_received_day": 2,
    "is_granted_today": true,
    "is_granted_now": false
  },
  "vip_login_bonus_calendar": {
    "mst_vip_login_bonus_id": "vip_login_lv5",
    "vip_level": 5,
    "last_received_day": 2,
    "is_granted_today": true,
    "is_granted_now": false,
    "can_re_receive": true
  }
}
```


## 3. `POST /auth/login`（変更）

- 応答の**末尾に 2 つのキーを追加**する（`login_bonus_list` の後ろ）: `login_bonus_calendar`・`vip_login_bonus_calendar`。**キーは常に出す**（マスターが無いときは値が `null`）。既存のキー・形・順序は変えない（D2・D6）。
- 追加は `HomeResponse` のコンストラクタ引数（段ごとのスカラー）と `toArray()` の 2 行。`HomeUseCase` が `LoginBonusCalendarInterface`（5 節）の読み取りを組み合わせ、`HomeResponse` が 2 節の形にする（Result・Formatter は作らない）。
- **DB に書かない**（読み取りだけ）。login は再受け取りを配らない（Q27）。
- **読む位置**: `executeAllMerged`（自動配布）と `updateLastLoginAt` の**後**、同じトランザクションの中で読む。配布前に読むと「最後に配った日数」が古くなる。Trx の読み取りは `queryOrMemory()` なので、同じリクエストで積んだ未保存の `upsertLatest` が見える（読んだ。`TrxLoginBonusRepository::selectByType`・`TrxVipLoginBonusRepository::selectLatestByPlayerId`）。
- **`is_granted_now` の元**: `HomeUseCase` が `executeAllMerged` の**前**に「日またぎ後の初回か」を 1 回判定し（`last_login_at` が null、または `!ClockUtility::isSameGameDay(今, last_login_at)`。`LoginBonusService::isEligible` と同じ式）、その真偽を 2 つの段の「`is_granted_now` を真にしてよいか」として渡す（4 節）。
- `sign_up`・`sign_in`・`refresh_token` は対象外。`#[DoesNotReturnUpdatedResources]` は既存のまま。

## 4. 判定（サーバーが段ごとに 1 回。overview 4 節の表の実装）

| 値 | 実装 |
| --- | --- |
| `is_granted_today` | 段の最新状態（通常: `trx_login_bonus` の `received_date`、VIP: `trx_vip_login_bonus` の `received_at`）が**ある**、かつ `ClockUtility::isSameGameDay(ClockUtility::nowToString(), 受取日)`。行が無ければ偽 |
| `is_granted_now` | `呼び出し側が渡す「真にしてよいか」 かつ is_granted_today`。login: 「日またぎ後の初回」を両段に渡す。`list`: 両段とも偽。再受け取り: VIP の段だけ真・通常の段は偽 |
| `can_re_receive` | 5.3 節の判定 `VipLoginBonusReReceiveService` がすべて通る（R4 ①〜④） |
| 段の `null` | 2.2 節 |

- **受取日の比較は `isSameGameDay` に寄せる**（宿題 2・**U-4 の答え**）。配布は受取日に `calcGameDayStart`（その時点の `DAY_START_TIME` での日の開始）を書く。`isSameGameDay` は両側を**今の** `DAY_START_TIME` で正規化するので、文字列の一致と違い、`DAY_START_TIME` を変えた直後でも「配布の判定（`last_login_at` の `isSameGameDay`）と同じ基準」で答えが出る。**配布の規則と食い違わない**ので、U-4 の「許容」は不要（残るのは `received_at` と `last_login_at` の時刻差が日の境界にまたがる極端な場合だけ。運営の設定変更の日以外は起きない。未確認の実測は不要と判断）。
- `is_granted_now` を「初回判定 かつ `is_granted_today`」で決めるのは、**初回の login で受取日が今日になっているのは、今の自動配布が書いたときだけ**（再受け取りは「今日すでに受け取り済み」が前提なので、初回の login より前にはあり得ない）だから。段ごとに配布が走ったかを Orchestrator から取らない（`executeAllMerged` は報酬を混ぜて返し、戦略ごとの結果は返さない。変えない）。
- **不変条件（`is_granted_now` の前提）**: `sys_player.last_login_at` を更新するのは `HomeUseCase`（`updateLastLoginAt`）**だけ**であること（読んだ範囲。他に呼ぶ場所があれば、初回判定が「今回の login で配った」とずれる）。また `isFirstLoginOfGameDay` は**配布の前**に 1 回だけ決める。S3 で `HomeUseCaseTest` に「同じ日の 2 回目の login で `is_granted_today` 真・`is_granted_now` 偽・`login_bonus_list` 空」のケースを足し、`updateLastLoginAt` の呼び出し元を grep で確かめる。
- 段のマスターの `mst_*_id` が、受け取り済みの行の `mst_*_id` と違う（運営がマスターを差し替えた）場合も、**受取日だけで判定する**（配布は `day` を ID に関係なく引き継ぐので揃う。仮置き。U-S2-4）。`last_received_day` がマスターの `loop_days` を超える場合もそのまま返し、クライアントが範囲外を扱う（体験 9）。

## 5. LoginBonus ドメインの構成（クラス名・置き場）

`api/app/Domain/LoginBonus/`。他のコンテキストへは `Contracts` だけで触る（`ModuleBoundaryTest`。ベースラインは空なので新しい違反は落ちる）。

| 種類 | クラス | 役割 |
| --- | --- | --- |
| Controller | `App\Http\Controllers\LoginBonusController`（`list`・`receive`） | `$this->execute(fn () => $useCase->handle(...))` だけ |
| Request | `Http\Requests\LoginBonus\ListRequest`（rules は空）・`ReceiveRequest` | `ReceiveRequest::getMstVipLoginBonusId()` |
| UseCase | `Domain\LoginBonus\UseCases\ListUseCase`・`ReceiveUseCase` | 5.1・5.2 |
| Response | `Domain\LoginBonus\Responses\ListResponse`・`ReceiveResponse` | 3 節以降。`ReceiveResponse` は `HasUpdatedResources` |
| Service | `LoginBonusCalendarService`（Contract を実装） | 2 節の 2 つの段の**状態**を作り、DTO で返す。mst・trx を読む。API の形（配列）にはしない |
| Service | `VipLoginBonusReReceiveService` | 再受け取りの判定（R4）と配布。5.3 |
| Contract | `Domain\LoginBonus\Contracts\LoginBonusCalendarInterface` | `Login\UseCases\HomeUseCase` が使う公開 API。戻り値はスカラー・Model（`TrxVipLoginBonus`）・パッケージの DTO（`LoginBonusHistory`）だけ（下の interface） |
| （置き場の注。U-S2-7） | カレンダー状態の Result DTO・Formatter は**作らない**（新規約「DTO/Result は最後の手段」。人の決定） | 他ドメインに出る型は Contracts の interface の戻り値（スカラー・Model・パッケージの DTO）だけなので、`ModuleBoundaryTest` は変えない。内部用の `VipLoginBonusReReceiveResult`（`judge` の戻り値。`TrxVipLoginBonus` を持つ）は `Domain\LoginBonus\Support\`。`ListResponse`・`ReceiveResponse` が共有する段の組み立ては `Support\BuildsCalendarStage` トレイト、login の `HomeResponse` は別コンテキストなので同じ形を自分で組み立てる |
| Constants | `Domain\LoginBonus\Constants\LoginBonusConst` | 再受け取りできない理由の定数 |
| パッケージ | `NexusLogin\Services\LoginBonusResourceConverter` | 行 → `LoginBonusContent` → `Resource` の変換（状態なし。U-2） |

```php
// Contract（形のみ。戻り値はスカラー・Model・パッケージの DTO だけ）
interface LoginBonusCalendarInterface
{
    public function findNormalBonusId(): ?string;                              // 有効な通常ボーナスの ID（無ければ null）
    public function findNormalHistory(int $sysPlayerId): ?LoginBonusHistory;  // 最新の受け取り状態（day・received_date）
    public function findVipBonusId(int $sysPlayerId): ?string;                 // 今のレベルの有効な VIP ボーナスの ID
    public function findVipLevel(int $sysPlayerId): int;                       // 今のレベル（vip_point から算出）
    public function findVipHistory(int $sysPlayerId): ?TrxVipLoginBonus;
    public function canVipReReceive(int $sysPlayerId): bool;                   // judge() と同じ判定（R4 ①〜④）
    public function isReceivedToday(?string $receivedAt): bool;                // isSameGameDay で今日か
}
```

**状態の持ち方（確定。U-S2-7。人の決定）**: カレンダー用の Result DTO と Formatter は作らず、上の interface の小さな読み取りを UseCase が組み合わせ、整形は Response に置く。Response のクラスは UseCase ごとに 1 つのまま（`ResponsePerActionTest`）。

**Login → LoginBonus の一方向**: `HomeUseCase`（Login）が `LoginBonusCalendarInterface` を使う。LoginBonus は Login の内部クラス（`VipLoginBonusService` など）を参照しない。LoginBonus が使うのは `App\Repositories\*`・パッケージ（`NexusLogin`・`NexusVip`・`Nexus\Core`）だけ（どれも `app/Domain` の外。読んだ）。バインドは `AppServiceProvider`。

**既存サービスを再利用する範囲**: 変換（`LoginBonusResourceConverter`）と履歴の書き込みだけ。配布済みの Login の `VipLoginBonusService` の `isEligible`・`calculateCurrentDay` は **変えない**（login は再受け取りを配らない）。履歴の書き込み（`upsertLatest` ＋ `LogVipLoginBonusReceiveRepository::insertReceive` の組）は、`VipLoginBonusService::recordHistory` と再受け取りで**同じ内容になる**ことが保証されないといけないので、LoginBonus の Service `VipLoginBonusReceiveRecordService::recordReceive`（trx の upsert + log の 1 報酬 1 行を必ず組で書く）に切り出して両方が呼ぶ（U-S2-5。人の決定で確定。Repository には業務メソッドを置かず、`TrxVipLoginBonusRepository`・`LogVipLoginBonusReceiveRepository` は単体のまま。Login へは `Contracts\VipLoginBonusReceiveRecorderInterface` で公開）。

### 5.1 `ListUseCase`（`POST /login_bonus/list`）

```php
public function handle(int $sysPlayerId): ListResponse
```

1. `validation()`: `SysPlayerRepository::selectById`（無ければ `SystemDataException::generic('sys_player', ...)`。`HomeUseCase` と同じ）
2. `LoginBonusCalendarInterface` の読み取り（`findNormalBonusId`・`findNormalHistory`・`findVipBonusId`・`findVipLevel`・`findVipHistory`・`canVipReReceive`・`isReceivedToday`）で各段の値を取る。`is_granted_now` は両段とも偽（`ListResponse` で固定）
3. 値を `ListResponse` の引数にして返す（`toArray()` が 2 節の形に整形）

**`executeWithTransaction` を使わない**（DB へ書かない。要件 H2a の保証）。書き込みが無いので `updated_resources` は返さない（`docs/api_resources.md` では「読み取り API」の欄に書く）。`#[DoesNotReturnUpdatedResources]` は要らない（trx を更新しないので例外にならない。読んだ `UseCaseTrait`）。

### 5.2 `ReceiveUseCase`（`POST /login_bonus/receive`）

```php
public function handle(int $sysPlayerId, string $mstVipLoginBonusId): ReceiveResponse
```

1. `validation()`: プレイヤーの存在確認。`VipLoginBonusReReceiveService::judge($sysPlayerId)`（レベルは**ロックの中**で読む）→ 6 節の順で、渡された ID が「今のレベルの有効なボーナス」と一致するか・R4 ①〜④ を検証し、満たさなければ例外（配らない）。判定の結果（今のレベル・マスター・受け取り状態）を返し、`handle()` で再取得しない
2. `executeWithTransaction(...)` の中で: 据え置いた日数 `day`（`trx_vip_login_bonus.day`）の新しいレベルの報酬行（`selectContentsByBonusIdAndDay`）を `LoginBonusResourceConverter` で `Resource` にし、`ResourceDeliveryService::addResources` → `deliver`（通常の配布と同じ経路。図鑑などの Handler も同じに反応）。続けて履歴: `upsertLatest(mst_vip_login_bonus_id = 新, day = 据え置き, vip_level = 今, received_at = 今日のゲーム日の開始)` と、`log_action_vip_login_bonus_receive` に 1 報酬 1 行
3. 履歴を書いた後の `trx_vip_login_bonus`（同じトランザクションの中なので積んだ更新が見える）と、今のレベル・据え置いた `day` を `ReceiveResponse` に渡す。VIP の段は `is_granted_today`・`is_granted_now` 真、`can_re_receive` 偽（受取時のレベルが今のレベルになったため）で固定する
4. `ReceiveResponse` を返す（コミット後に `updated_resources` が付く）

**`vip_level` の更新と配布が同じトランザクション**。これが二重受け取りを防ぐ主役（ロック内の再判定）。2 回目は ② が偽になる（受取時のレベルが今のレベルと同じ）ので 26001 で止まる。

### 5.3 `VipLoginBonusReReceiveService`（判定と配布）

`judge(int $sysPlayerId): VipLoginBonusReReceiveResult`（`Domain/LoginBonus/Support/`。`public readonly` のみ）。`calendar` と `receive` が**同じ関数**を使う（`can_re_receive` と API の再判定が食い違わない。overview 4 節）。

| 順 | 見るもの | 偽のとき |
| --- | --- | --- |
| ③ | 今のレベル（`VipLevelService::calculateLevel(sys_player.vip_point)`）の有効な `mst_vip_login_bonus`（`selectActiveByVipLevel`）があるか | 理由 `master_not_found` |
| （receive のみ） | 渡された ID が ③ の `id` と一致するか | 理由 `master_mismatch` |
| ① | `trx_vip_login_bonus` の行があり、受取日が今日（`isSameGameDay`）か。**行が無ければ偽**（R7: 初回配布は login の仕事） | 理由 `not_granted_today` |
| ② | `trx_vip_login_bonus.vip_level` < 今のレベル（**高いときだけ**。同じ・低いは偽） | 理由 `level_not_raised` |
| ④ | `day` ≤ ③ の `loop_days`、かつ ③ の `id` の `day` 日の報酬行が 1 件以上 | 理由 `reward_not_found` |

「行が無い」「今日でない」「レベルが同じ・下がった」を別の理由にするのは、エラーコードの出し分けとテストの切り分けのため（6 節）。`can_re_receive` は 5 つすべて通るときだけ真。

## 6. エラー（HTTP 299 + `{error_code, message}`）

`GameErrorCode` に **「ログインボーナス関連エラー (26000-26999)」** を足す（冒頭のコメントの割り当て表の更新は **S3 の項目**）。未使用の帯（25000 = 交換所の次。読んだ）。

| コード | 定数 | 例外の種類 | いつ | クライアントの対応 |
| --- | --- | --- | --- | --- |
| 26001 | `LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE` | `BusinessLogicException` | ① 今日の段でない（行なし・別の日）または ② 今のレベルが受取時のレベルより高くない（既に受け取り済み・同じ・下がった） | 状態を取り直して画面を更新（Q26・D1）。二重送信・日またぎ・既に受け取り済みはここに入る |
| 26002 | `LOGIN_BONUS_MASTER_NOT_FOUND` | `MasterDataException` | ③ 今のレベルの有効な VIP ボーナスが無い、または渡された ID がそれと一致しない（存在しない ID・別のレベルの ID を含む） | 同上 |
| 26003 | `LOGIN_BONUS_REWARD_NOT_FOUND` | `MasterDataException` | ④ 据え置いた日数が新レベルの `loop_days` を超える、または新レベルのその日の報酬行が無い。**配らない**（空の配布は履歴を書かず `vip_level` が更新されない。`_BaseLoginBonusService::grantBonus` が空なら何もしない。読んだ） | 同上（運営のマスターの設定ミス。再送しても同じ） |

- **26001 は 2 つの理由（① 今日の段でない・② レベルが上がっていない）を 1 つのコードに畳んでいる**。理由の違いは**ログとテストの切り分けのためだけ**で（`judge()` が理由の定数を返す。`LoginBonusConst`）、**クライアントの対応は同じ**（状態を取り直して画面を更新）。クライアントは理由で分岐しない。
- 判定の順は 5.3 節の表の順（③ → ID 一致 → ① → ② → ④）。`ReceiveRequest` のバリデーション失敗（`mst_vip_login_bonus_id` 無し・文字列でない・65 文字以上）は **422**（Laravel 標準。`rules()`: `required|string|max:64`。`mst_vip_login_bonus.id` は `string(64)`）。

### 6.1 クライアントへの約束（冪等キー。独立した項）

| 約束 | 内容 |
| --- | --- |
| エラー後の再試行 | `POST /login_bonus/receive` が 299（26001・26002・26003）で失敗したあとの再試行・再送は、**新しい `X-Unique-Request-Identifier`** を付ける。エラーのあとは `POST /login_bonus/list` で状態を取り直してから、ボタンが出たときだけ新しいキーで送る |
| 成功の再送 | 応答が届かなかったとき**だけ**、同じキーで再送してよい（成功なら保存済みの同じ応答が返り、配らない） |
| 理由 | `IdempotencyMiddleware` は `>= 200 && < 300` の応答を **24 時間**保存する（読んだ）。**業務エラーの HTTP 299 も 2xx の範囲に入るので保存される**（`.claude/api.md`「業務エラー（299）も同じキーで再送すると同じエラーが返る」と一致）。同じキーで再送すると、状態が変わっていても古い 299 が返り続ける |
| Nexus 側の記述 | overview 3 節・5 節・要件 R4 の「条件を満たさないエラーは再送でも毎回再判定される」は同じキーでは成り立たない。**Nexus 側は別の design-change PR で直す予定**（S2 では直さない。U-S2-1） |

- プレイヤーロックが取れない 409（40900）・Redis 不通 503（50300）は既存どおり（`docs/player_request_lock.md`）。

## 7. `POST /login_bonus/list`

| 項目 | 内容 |
| --- | --- |
| ミドルウェア | `maintenance` → `platform` → `auth.token` → `idempotency` → `player.lock`（認証後のグループ。`auth/` のグループではない） |
| Post | **なし**（空のボディ `{}`。プレイヤーは Bearer から決まる）。`X-Platform` は全 API 共通で必須 |
| 検証 | なし |
| 副作用 | **なし**（DB に書かない。H2a） |

### Response

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| login_bonus_calendar | object | **null 可** | 2.1 節。`is_granted_now` は常に偽 |
| vip_login_bonus_calendar | object | **null 可** | 2.2 節。`is_granted_now` は常に偽 |

- `updated_resources`・`login_bonus_list` は返さない。
- **クライアントへの約束**: 7.1 節（`list` はキーを付けないか毎回新規にする）。
- **冪等・排他**: POST なので `player.lock` の対象（読み取りが login・再受け取り・課金と直列になる）。`X-Unique-Request-Identifier` は**任意**（無ければ冪等は効かず、ロックだけ）。**付けるなら毎回新しい値にする**: 冪等キャッシュは 24 時間・キー＋パスで保存し、2xx（200）を**そのまま再生する**ので、同じキーを使い回すと**古い状態が返る**。ホームから開くたびに呼ぶ API なので、クライアントはキーを付けないか毎回新規にする（S4 に伝える）。
### 7.1 クライアントへの約束（`list` の冪等キー。独立した項）

| 約束 | 内容 |
| --- | --- |
| `X-Unique-Request-Identifier` | `POST /login_bonus/list` では**付けない、または呼ぶたびに新しい値**にする。同じキーを使い回さない |
| 理由 | 冪等ミドルウェアは 2xx の応答を **24 時間**、キー＋パスで保存して再生する（読んだ）。同じキーで呼ぶと、再受け取りや日またぎのあとでも**古い状態が返る**（`X-Idempotency-Cache: HIT`）。この API は「最新を取る」ためのものなので、再生は害になる。エラー（299）も同様に保存される（6.1 節） |
| 付けないとどうなるか | 冪等は効かず、プレイヤーロックだけが効く（読み取りは書き込みと直列）。副作用が無いので、二重に呼んでも害はない |

- 追加クエリ数: 通常 mst 1 本（ボーナス）＋ trx 0〜1（login 後は読み込み済み）、VIP は `mst_vip_level` ＋ `mst_vip_login_bonus` ＋ `can_re_receive` の判定用の報酬行 1 本の mst 3 本、trx 0〜1。全日の報酬は読まない（U-S2-8）。VIP の段の ID・レベル・`can_re_receive` は同じ `judge()` の結果から取るので `mst_vip_login_bonus` は 1 回だけ引く（`LoginBonusCalendarApiTest` の list のクエリログで固定）。

## 8. `POST /login_bonus/receive`

| 項目 | 内容 |
| --- | --- |
| ミドルウェア | 7 節と同じ |
| 副作用 | `trx_vip_login_bonus` の更新、報酬の反映（trx）、`log_action_vip_login_bonus_receive` の追加 |

### Post

| 項目 | 型 | 必須 | 検証 |
| --- | --- | --- | --- |
| mst_vip_login_bonus_id | string | 必須 | `required\|string\|max:64`。今のレベルの有効な `mst_vip_login_bonus.id` と一致すること（一致しなければ 26002）。`vip_login_bonus_calendar.mst_vip_login_bonus_id` をそのまま送る |

自分の `sys_player_id` は送らない（`ApiSession`）。

### Response

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| trx_vip_login_bonus | object | 不可 | 受け取った後の `trx_vip_login_bonus` の行（`TrxVipLoginBonus::toResponseArray()`）: `mst_vip_login_bonus_id`・`day`（据え置き）・`vip_level`（今のレベル）・`received_at`。`sys_player_id`・`created_at`・`updated_at` は除かれる。`received_at` の書式は **`Y-m-d H:i:s`（UTC）の文字列のまま**（S3 で実測して確定。`TrxVipLoginBonus` に日付の cast が無く `getDates()` が空なので ISO 8601 には変換されない。値はその日のゲーム日の開始。例: `DAY_START_TIME = 04:00`・2026-10-15 12:00 に受け取ると `2026-10-15 04:00:00`。`LoginBonusCalendarApiTest::received_atの書式を固定する` が固定）。キーは `mst_vip_login_bonus_id`・`day`・`vip_level`・`received_at` の 4 つだけ。クライアントは `received_at` を判定に使わない |
| login_bonus_list | array | 不可 | **今回この応答で配った報酬**（`Resource::toArray()` の 6 キー。login の `login_bonus_list` と同形）。R6: 「今日配られた報酬」の行に出す元データ。**Q26 の既定（`updated_resources` と `trx_vip_login_bonus` だけ）に足す**（`updated_resources` は絶対値で、何を配ったかが分からないため。**仮置き**。U-S2-6） |
| vip_login_bonus_calendar | object | 不可 | 2.2 節。`is_granted_now` = **真**、`can_re_receive` = **偽**、`is_granted_today` = 真、`last_received_day` = 据え置いた `day`（D6・R3・T5）。**仮置き**（Q26 の既定は 2 項目。D6「応答の中の VIP の段の今回配ったかは真」を文字どおり満たすために足す。U-S2-6） |
| updated_resources | object | 不可 | 更新された trx だけ（`HasUpdatedResources`）。報酬の種類による: `trx_item_list`（無償ダイヤは `free_diamond`）・`trx_wallet_list`・`trx_unit_list`・`trx_equipment_list`・`trx_stamina_list`・`sys_player` など。変わらなかったテーブルのキーは無い。**`trx_vip_login_bonus` は `ReturnedAsUpdatedResource` の対象（7 テーブル）ではないので入らない**（`UpdatedResourceModelsTest` の `EXPECTED` は変えない。読んだ）ので、`trx_vip_login_bonus` を別のキーで返す |

通常の段（`login_bonus_calendar`）は返さない（再受け取りで変わらない。R3）。

- **`updated_resources` に VIP の状態は含まれるか**（overview 8 節の宿題 7・U-2）: **含まれない**（上のとおり）。だから `trx_vip_login_bonus` を独立したキーで返す。
- **冪等・排他**: ロックの中で判定し、`vip_level` を同じトランザクションで更新するので、同時の 2 リクエスト・再送でも同じレベルで 2 回配られない（2 回目は 26001）。**同じ `X-Unique-Request-Identifier` の再送は、成功（200）なら保存済みの同じ応答が返る**（配らない）。キー無しの二重送信は 2 回目が 26001。課金（`in_app_purchase/buy`）とは同じロックで直列（課金が先なら新しいレベルで配られ、後なら次の判定に回る）。
- **`login_bonus_list` と二重に配られない確認**: 再受け取りの前に login していても、login は再受け取りを配らない（Q27）ので重ならない。別の日の login は通常の配布（日数を進める）で、再受け取りは重ねない（R5）。

## 9. overview の未解決点 U-1〜U-10 のうちサーバーに関わるもの

| # | 結論 |
| --- | --- |
| U-1 不正な行（**U-S2-8 で不要になった**） | サーバーはカレンダーに全日の報酬を載せないので、不正な `content_type` の行を除く処理・`Log::warning` は無い（クライアントが配信のマスターから描く）。**配布（`grantBonus`）の失敗は今のまま**（その日の行が不正なら配る日に失敗する。変えない） |
| U-2 変換の公開 | **パッケージに状態を持たない `NexusLogin\Services\LoginBonusResourceConverter` を作り、`_BaseLoginBonusService::toContents`・`convertToResource` が委譲する**。`protected` のままのメソッドは残す（サブクラスの拡張点。動作は変えない）。カレンダーも再受け取りもこのクラスを使うので、`type` の決め方（`wallet` → `content_mst_id`）は 1 つ。全日の変換は配布を伴わない（overview 7 節の j）。再受け取りの配布は `ResourceDeliveryService::deliver` 経由 |
| U-4 受取日の比較 | 4 節のとおり `isSameGameDay` に寄せる。許容は不要 |
| U-8（置き換え済み） | 再受け取り（5・8 節） |
| U-10（解決済み） | Q25〜Q31 は 5〜8 節に実装を書いた |
| U-3・U-5・U-6・U-9 | クライアント側（サーバーは関与しない） |
| U-7 録画 | db.md 6 節 |

**この設計で新しく残る未解決点**（既定案つき。人が決める）:

| # | 確認事項 | 既定案 |
| --- | --- | --- |
| U-S2-1 | overview・要件は「冪等ミドルウェアは 2xx だけを保存するので、条件を満たさないエラーは再送でも毎回再判定される」と書くが、`IdempotencyMiddleware` は `>= 200 && < 300` のときに保存し、**業務エラーは HTTP 299 なので保存される**（`.claude/api.md` も「業務エラー（299）も同じキーで再送すると同じエラーが返る」）。同じキーで再送すると、日またぎ後でも**古い 26001 が返る** | **サーバーは直さない**（全 API 共通の規約）。クライアントは「エラー後に状態を取り直す・再送するときは新しい `X-Unique-Request-Identifier`」とする（S4）。overview・要件の該当の記述（3 節・5 節・R4）は **Nexus 側を別の design-change PR で直す予定**。クライアントへの約束は 6.1・7.1 節 |
| U-S2-2（**人の決定で解消**） | VIP の報酬の並びを `mst_vip_login_bonus_content.id`（連番）に頼ると、解消待ちの string id 化で順序が変わる | **列 `sort_desc`（降順。大きい値が先。同値は `id` 昇順）を `mst_vip_login_bonus_content` に足した**（人が「`sort_order` だと昇順か降順か分からない」として命名）。シーダーは従来の `id` 順と同じ並びになるよう、その日の行数から 1 ずつ小さくなる値を振る。配る順・クライアントの表示順の鍵であり、string id 化の解消の影響を受けない（db.md 3 節）。通常側の `mst_login_bonus_content` の並び列も `sort_order`（昇順）から **`sort_desc`（降順）に置き換えた**（人の決定。リリース前なので後方互換なし。シーダーは行数から 1 ずつ小さい値で従来と同じ並びに） |
| U-S2-8（人の決定） | login・list・receive の応答から全日の報酬（`day_reward_list`）と `loop_days` を外す | **外した**。クライアントが配信の SQLite のマスターを ID で引いて描く（wallet は `mst_item.is_wallet`、type/id の読み替えは汎用）。応答に残るのは段のマスター ID・進捗（`last_received_day`・`is_granted_today`・`is_granted_now`）・VIP の `vip_level`・`can_re_receive`。`loop_days` を外しても R4 の判定（④ で `day ≤ loop_days` と報酬行を見る）とテストには影響しない（サーバーは判定でマスターの `loop_days` と報酬行を内部で読む）。マスターが古いときの再取得はクライアントの責務 |
| U-S2-3 | 再受け取りを履歴で区別する列（`is_re_receive`）が無い | **足さない**（同じ `received_at` で `vip_level` が違う行で見分ける。db.md 7 節）。集計要件が出たら design-change |
| U-S2-4 | 受け取り済みの行のマスター ID が今のマスターの ID と違うとき（差し替え） | 受取日だけで判定（4 節）。配布が日数を ID に関係なく引き継ぐのと揃う |
| U-S2-5（人の決定） | 再受け取りの履歴の書き込みを `VipLoginBonusService::recordHistory` と共有する形 | **Service に切り出して共有**（Repository の業務メソッドにはしない）: `LoginBonus\Services\VipLoginBonusReceiveRecordService`（`Contracts\VipLoginBonusReceiveRecorderInterface` を実装）。login の `VipLoginBonusService` は interface 経由、`ReceiveUseCase` も同じものを呼ぶ。コピーはしない |
| U-S2-7（人の決定） | カレンダー状態の Result DTO（`LoginBonusCalendarResult` など）と Formatter を作るか | **作らない**（新規約 server PR #9「DTO/Result は最後の手段」。スカラー・Model・パッケージの DTO で足りる）。他ドメインに出る型は Contracts の interface の戻り値だけなので、`ModuleBoundaryTest` は変えない（以前の「DataTransferObjects の参照を許す」は取り下げ）。整形は Response（`ListResponse`・`ReceiveResponse` は `Support\BuildsCalendarStage` を共有、`HomeResponse` は自前） |
| U-S2-6 | 再受け取りの応答に、Q26 の既定（`updated_resources`・`trx_vip_login_bonus`）に加えて `login_bonus_list`・`vip_login_bonus_calendar` を足した | 足す（R6 の「今日配られた報酬の行」と D6 の「VIP の段は真」のため）。不要なら S3 で外す |

## 10. 既存テスト・ドキュメントへの影響（宿題 5・8）

| 場所 | 影響 |
| --- | --- |
| `HomeUseCaseTest`・`WalkthroughTest`・`HomeTrxDiamondTest`・`HomeSnapshotTest`（`api/tests/Feature/...`） | login の応答のキーの**完全一致を検査している箇所は読んだ範囲で無い**（`WalkthroughTest` の `assertJsonStructure` は login ではなく unit のもの。`HomeSnapshotTest` は 7 テーブルのキーの検査で、新キーは `trx_*` でないので対象外のはず）。新キーの追加で落ちるテストは**見つからなかった**（未実行。S3 で `make test-quiet`） |
| `HomeUseCaseTest` | 新キーの検査を足す（キーが常にある・マスター無しで `null`・同じ日の 2 回目で `is_granted_today` 真・`is_granted_now` 偽） |
| VIP の並びを前提にするテスト | `VipLoginBonusServiceTest`・`LoginBonusIntegrationTest`・`VipLoginBonusSeederTest`・`LoginBonusSeedersTest`。`id` 昇順で入れているかは**未確認**（S3 で確かめる） |
| `LoginBonusRepositoryAdapterTest` | `selectContentsByLoginBonusId` の並びは `sort_desc` 降順に変えた（旧 `sort_order` 昇順。テストで固定）。1 日分の並びの固定（`sort_desc` 降順 → `content_type` → `content_mst_id`）のテストを足した。全日取得は U-S2-8 で不要になったので追加していない |
| Architecture（実際に走る検査。**S3 の確認項目**） | `PostOnlyRoutesTest`（新ルートは POST）、`ResponsePerActionTest`（`LoginBonus\Responses\ListResponse`・`ReceiveResponse` が UseCase ごとに 1 つ。Formatter は Response ではないので対象外のはずだが S3 で確認）、`UpdatedResourcesResponseTest`（`ReceiveResponse` が `HasUpdatedResources`・`ListResponse` は更新を返さない形で通るか）、`ModuleBoundaryTest`（`Login` → `LoginBonus\Contracts` だけを参照するか）。**S3 で `make check-quiet` を通して確定**。命名は設計時点でキーを `mst_login_bonus_id`・`mst_vip_login_bonus_id`・`*_list` にしているが、`ResponseKeyNamingTest` が走ってどう判定するかも S3 で確認する。`ConverterLocationTest` 対策として、`LoginBonusRepositoryAdapter` に変換メソッドを足すときは、private な `*toDto*`・`Convert*` メソッドを置かず、変換が要るなら `app/Adapters/Login/` に委譲する（設計では通ると断定しない） |
| S3 の実装結果（確定） | 上の Architecture 検査はすべて通った（`ModuleBoundaryTest` を含む。DTO・Formatter は作らず（U-S2-7。人の決定）、`ModuleBoundaryTest` は変更なし、`ResponseKeyNamingTest`・`ConverterLocationTest` も通過）。`updateLastLoginAt` の呼び出し元は `HomeUseCase` だけ（grep で確認）。既存の login 系テストは新キーで落ちなかった。U-S2-5 は `App\Domain\LoginBonus\Services\VipLoginBonusReceiveRecordService::recordReceive`（trx の upsert + log の 1 報酬 1 行。人の決定で Repository ではなく Service）に切り出して確定。`HomeUseCaseTest` に「再受け取り後の同日 2 回目の login で `is_granted_now` が偽」を足した。U-S2-6（receive 応答の `login_bonus_list`・`vip_login_bonus_calendar`）は**足したまま確定**（`vip_login_bonus_calendar` は `is_granted_now` 真・`can_re_receive` 偽・`last_received_day` は据え置いた `day`） |
| S3 の項目（その他） | `Common`・`architecture.md` の境界の節は変更なし（U-S2-7）。`GameErrorCode` の冒頭コメントの割り当て表に 26000 番台（ログインボーナス）を足す。`.claude/api.md`・`docs/api_resources.md`・`docs/design/title-to-home/api.md` の追記（下の行） |
| 新規テスト | `ListUseCase`（副作用なし・`is_granted_now` 常に偽・マスター無しで `null`）、`ReceiveUseCase`（正常・26001 の各ケース（行なし・別の日・同じレベル・下がった）・26002（別 ID）・26003（日数超過・報酬行なし）・二重送信・冪等の再送・`vip_level` の更新・`day` 据え置き・履歴 1 報酬 1 行）、課金（`in_app_purchase/buy`）の後の receive、`LoginBonusResourceConverter` の単体、VIP の `sort_desc` 降順の並び |
| `.claude/api.md`・`docs/api_resources.md`・`docs/design/title-to-home/api.md` | S3 で追記（login の新キー、`/login_bonus/list`・`/login_bonus/receive`、書き込み API の表に `login_bonus/receive`＝更新分、読み取り API の欄に `login_bonus/list`＝返さない）。`.claude/api.md` の分類（ログイン後の一覧）に `login_bonus` を足す。仕様はここへのリンクにとどめ、写さない |
