# タイトル〜ホーム API 設計

既に実装済みの機能の設計書。**実装が正**で、コードから確かめた事実だけを書く。確かめられなかったものは「未確認」と書き、末尾の「未解決点」に集める。Response の JSON 例は、コード（Response クラス・Model の `toResponseArray()`）から組み立てた例で、実行出力ではない。

- DB: [db.md](db.md)、シーケンス: [sequence.md](sequence.md)
- 概要設計: `Nexus/docs/design/title-to-home/overview.md`

## 1. 共通

### ルーティングとミドルウェアの順序

定義は `api/routes/api.php`。URL は `/api/...`。

| 範囲 | ミドルウェア（外側から） |
| --- | --- |
| 全 API | `ResolveLanguage` → `CheckMasterHash` |
| GET /auth/version | なし（メンテナンス除外ルート `auth/version`） |
| POST /auth/sign_up | `maintenance` → `client.signature` → `throttle.signup` |
| POST /auth/sign_in | `maintenance` → `throttle.auth:sign_in` |
| POST /auth/refresh_token | `maintenance` → `throttle.auth:refresh_token` |
| POST /auth/login、POST /auth/version、GET /agreement/pending、POST /agreement/consent | `maintenance` → `auth.token` → `idempotency` → `player.lock` |

### 共通ヘッダー

| ヘッダー | 対象 | 内容 |
| --- | --- | --- |
| `Authorization: Bearer {access_token}` | 認証付き API | JWT（HS256、1 時間）。署名と `exp` だけ検証（DB 参照なし） |
| `X-Unique-Request-Identifier` | 認証付き API の POST | 冪等キー（下記） |
| `X-Client-Timestamp` / `X-Client-Nonce` / `X-Client-Signature` | sign_up | `HMAC-SHA256("{timestamp}:{nonce}:{body}", CLIENT_SECRET)`（hex）。timestamp は UNIX 秒 |
| `X-Master-Hash` | 全 API（任意） | クライアントが持つマスターハッシュ |
| `Accept-Language` | 全 API | 言語（既定 `ja`） |
| `Deploy-Version` | /auth/version | body / query の `deploy_version` が無いときの代替 |

`Accept: application/json` が無いときの 422 の挙動は**未確認**（Laravel 標準のリダイレクトになる可能性）。

### CheckMasterHash（X-Master-Hash / X-Master-Update-Required）— 概要設計 U3 のサーバー側の答え

`api` グループの先頭に付く全 API 共通の後処理ミドルウェア（`App\Http\Middleware\CheckMasterHash`）。

1. 先に API 本体を処理する（ボディは一切変えない）。
2. `X-Master-Hash` が無い、または**応答が HTTP 200 でない**（299・4xx・5xx を含む）なら何もしない。
3. 最新の配信可能な `sys_deploy`（`is_active` かつ `start_at <= 今`、`deploy_key` 最大。キャッシュあり）の `deployMaster.hash` と `hash_equals` で比較。最新が無い、または一致なら何もしない。
4. 不一致なら応答に `X-Master-Update-Required: true` を付ける。判定中の例外は握りつぶす（API 応答を壊さない）。

つまり**サーバーはどの API でも、200 で返るときに付けられる**。クライアントが実際にどの API へ `X-Master-Hash` を付けているかはサーバーからは分からず、**未確認**（S4 で確認）。更新要求ヘッダーは「更新が必要」を知らせるだけで、内容は `GET /auth/version` で取る。

### 冪等（X-Unique-Request-Identifier）

`IdempotencyMiddleware`。キーは `idempotency:{player_id}:{X-Unique-Request-Identifier}:{path}`。

| 条件 | 挙動 |
| --- | --- |
| ヘッダーが**無い** | 冪等チェックを**素通し**（エラーにならず、毎回処理される） |
| GET | 素通し（GET /agreement/pending は対象外） |
| 認証前（`player_id` 無し）の API | 素通し（sign_up / sign_in / refresh） |
| 同じキー・同じパスが完了済み | 保存済みレスポンスを返す。`X-Idempotency-Cache: HIT`。保存は 24 時間 |
| 同じキーが処理中 | 409 `{"error_code": 40900, "message": "Request is being processed. Please retry later."}`（`X-Idempotency-Cache: PROCESSING` または `CONFLICT`）。処理中の印は最大 5 分 |
| 初回 | 処理後、HTTP 2xx なら保存（299 も保存される）。2xx 以外は保存せず印を消す。`X-Idempotency-Cache: MISS` |

- キーが無いと二重送信の保護は**プレイヤーロックだけ**になる。login は 2 回目に `last_login_at` が更新済みで、ログインボーナスは再付与されない（返す `login_bonus_list` は空になる）。consent は 2 回目に 24002（同意済み）が返る。どちらも「保存済みレスポンスを返す」挙動にはならない。
- 業務エラー（299）も保存されるため、同じキーで再送すると同じエラーが返る。再試行するときは**新しいキー**を使う。
- Redis（Cache）に繋がらないときの冪等ミドルウェアの挙動は**未確認**（例外処理が無い）。

### プレイヤーロック（player.lock）

`LockPlayerRequest`。`Cache::lock("player_request:{player_id}", 30秒)` を 5 秒まで待って取る。

- **GET は対象外**（GET /agreement/pending はロックされない）。POST の login / consent / POST version がロック対象。
- 5 秒待って取れない → HTTP 409 `{"error_code": 40900, "message": "Another request is being processed. Please retry later."}`。
- ロックの置き場（Redis）に繋がらない → HTTP 503 `{"error_code": 50300, "message": "Service temporarily unavailable. Please retry later."}`。
- ロックは `finally` で解放。落ちても 30 秒で自動的に外れる。
- 冪等ミドルウェアはロックより外側にあるため、冪等 HIT の再送はロックを待たずに返る。

### エラーの形（種類）

| 種類 | HTTP | ボディ | 出どころ |
| --- | --- | --- | --- |
| 業務エラー（`GameException`） | **299** | `{"error_code": int, "message": string}` | `_BaseController::execute()` |
| 入力不正 | 422 | `{"message", "errors": {項目: [..]}}`（Laravel 標準） | FormRequest |
| アクセストークン不正・欠落 | 401 | `{"error": "Authorization header missing or invalid"}` または `{"error": "Invalid or expired access token"}`（`error_code` 無し） | `auth.token` |
| 署名不正（sign_up） | 401 | `{"error": "INVALID_CLIENT_REQUEST" \| "REQUEST_EXPIRED" \| "DUPLICATE_REQUEST" \| "INVALID_SIGNATURE", "message"}` | `client.signature` |
| レート制限 | 429 | `{"error": "TOO_MANY_REQUESTS", "message", "retry_after"}`（`error_code` 無し） | `throttle.*` |
| メンテナンス | 503 | `{"error": "Service Unavailable", "message", "title", "start_at", "end_at"}`（`error_code` 無し） | `maintenance` |
| 冪等・ロックの競合 | 409 | `{"error_code": 40900, "message"}` | `idempotency` / `player.lock` |
| ロック基盤の不達 | 503 | `{"error_code": 50300, "message"}` | `player.lock` |
| 想定外の例外（execute 内） | 例外コードが 100〜599 ならその値、他は 500 | `{"error_code", "message"}` | `_BaseController::handleException` |

- 本番（`APP_ENV=production`）では 299 などの `message` が固定文言にマスクされる。
- 401・429・503（メンテ）は `error_code` を持たないため、**クライアントは HTTP ステータスで分類する必要がある**。
- 299 は「業務エラー」で HTTP 2xx 扱い。`CheckMasterHash` は 299 に `X-Master-Update-Required` を付けない。

### エラーコード（この機能に出るもの）

| コード | 定数 | 意味 |
| --- | --- | --- |
| 10002 | PLAYER_NOT_FOUND | sign_in で device 未登録／プレイヤー無し、refresh でプレイヤー／端末無し |
| 10003 | INVALID_TOKEN | refresh_token が無効・期限切れ |
| 10004 | DEVICE_ALREADY_EXISTS | sign_up で device が登録済み |
| 24001 | AGREEMENT_NOT_FOUND | consent の対象が存在しない（公開済みの必須規約でない） |
| 24002 | AGREEMENT_ALREADY_CONSENTED | 同意済み |
| 99001 | INVALID_PARAMETER | （consent の翻訳先の既定。現行の分岐では出ない） |
| 99999 | INTERNAL_ERROR | login でプレイヤー無し（`SystemDataException`）、version でデプロイ無し |
| 40900 / 50300 | - | 上記の競合・基盤不達（`GameErrorCode` の定数ではなくミドルウェアが直接返す） |

## 2. GET /api/auth/version

- ミドルウェア: なし。メンテナンス中も通る。認証不要。冪等・ロックなし。
- 入力: query `deploy_version`（任意）、または `Deploy-Version` ヘッダー。

| 項目 | 型 | 必須 | 検証 |
| --- | --- | --- | --- |
| deploy_version | integer | 任意（null 可） | `nullable|integer`。body → ヘッダーの順に採用 |

- 判定: `deploy_version` が最新の `sys_deploy.id` と**厳密に等しければ** `needs_update: false`。違う・省略なら最新を返す。
- Response（`needs_update: true`）:

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| needs_update | bool | 不可 | |
| maintenance | object | キーごと省略可 | メンテナンス情報があるとき（`SysMaintenance::toArray()`: id、title、message、start_at、end_at、is_active、created_at、updated_at。日時の書式は**未確認**） |
| sys_deploy_id | int | 不可 | クライアントが次回 `deploy_version` に送る値 |
| latest_deploy_key | int | 不可 | |
| master | object | キーごと省略可 | `deployMaster` があるとき |
| master.sys_deploy_master_id | int | 不可 | |
| master.hash | string | 不可 | `X-Master-Hash` の比較対象 |
| master.tables | object（テーブル名 → 情報） | 不可 | 各 `{hash: string, url: string, file_size: int}` |
| asset | object | キーごと省略可 | `{sys_deploy_asset_id, hash}`。アセット配信の実体は無い |

`needs_update: false` のときは `needs_update` と（あれば）`maintenance` だけ。

```json
{
  "needs_update": true,
  "sys_deploy_id": 12,
  "latest_deploy_key": 2026101301,
  "master": {
    "sys_deploy_master_id": 8,
    "hash": "0f3c...",
    "tables": {
      "mst_item": { "hash": "ab12...", "url": "/masterdata/ab12.../mst_item_ab12....sqlite", "file_size": 12345 }
    }
  },
  "asset": { "sys_deploy_asset_id": 8, "hash": "9d0e..." }
}
```

- `url` は **`/masterdata/{hash}/{file}` の相対パス**（`MasterDeployService` が登録）。**配信ホストはレスポンスにもサーバー設定（api の config）にも無い**。
- エラー: 配信可能な `sys_deploy` が無いと 299 / 99999（`SystemDataException::deploy()`）。`deploy_version` が整数でないと 422。
- 更新リソース: なし。DB 書き込みなし。

## 3. POST /api/auth/sign_up

- ミドルウェア: `maintenance`、`client.signature`、`throttle.signup`。冪等・ロックなし（認証前）。
- トランザクション: `executeWithTransaction`（sys と全 trx シャードに開始。実際に書くのは sys のみ）。

### Post

| 項目 | 型 | 必須 | 検証・最大長 |
| --- | --- | --- | --- |
| device_id | string | 必須 | `required|string|max:255` |
| device_info | object | 任意（null 可） | `nullable|array`。省略時は空配列で保存 |
| device_info.os | string | 任意 | `nullable|string|max:50` |
| device_info.os_version | string | 任意 | `nullable|string|max:50` |
| device_info.model | string | 任意 | `nullable|string|max:100` |
| device_info.app_version | string | 任意 | `nullable|string|max:50`（**sign_in は max:20**） |

### 署名・レート制限

- 署名: timestamp が現在時刻と 300 秒以内、nonce が 600 秒以内に未使用（Cache `client_nonce:{nonce}`）、HMAC が一致。検証成功時だけ nonce を記録する。`CLIENT_SECRET` が空、またはローカル以外でプレースホルダ・32 文字未満だと例外（500）。ローカルでの省略は `CLIENT_SIGNATURE_SKIP_IN_LOCAL=true` のときだけ。
- レート: IP あたり 1 時間 10 回、`device_id` あたり 1 時間 3 回。**処理の成否にかかわらずカウントする**（上限判定は処理の前）。超過は 429（`retry_after` は秒）。

### Response

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| sys_player | object | 不可 | 作成直後のモデルの属性のみ。`my_id`（string 8 文字）、`name`（string、ランダム 8 文字）。`id` `uuid` `created_at` `updated_at` `sys_player_id` は除外。DB 既定値の `level` 等は再取得しないため**含まれない見込み（未確認）** |
| sys_player_device | object | 不可 | `sys_player_device_id`（int）、`device_info`（object）、`last_login_at`（書式は**未確認**。Carbon のまま JSON 化されると ISO 8601） |
| sys_player_token | object | 不可 | `sys_player_token_id`（int）、`sys_player_device_id`（int）、`expires_at`（`Y-m-d H:i:s`、30 日後）。`refresh_token_hash` は**返さない** |
| token | object | 不可 | `access_token`（string、JWT）、`refresh_token`（string、64 桁 hex）、`expires_in`（int、3600） |

```json
{
  "sys_player": { "my_id": "aB3dE9xZ", "name": "kQ8wL2mN" },
  "sys_player_device": { "sys_player_device_id": 101, "device_info": { "os": "iOS", "os_version": "17.0", "model": "iPhone 15 Pro", "app_version": "1.0.0" }, "last_login_at": "2026-11-12T01:23:45+00:00" },
  "sys_player_token": { "sys_player_token_id": 555, "sys_player_device_id": 101, "expires_at": "2026-12-12 01:23:45" },
  "token": { "access_token": "eyJ...", "refresh_token": "9f2c...(64桁)", "expires_in": 3600 }
}
```

### エラー

| HTTP | error_code | 条件 |
| --- | --- | --- |
| 299 | 10004 | `sys_player_device.uuid` が既に存在 |
| 422 | - | 検証違反 |
| 401 | - | 署名ヘッダー欠落・時刻ずれ・nonce 重複・署名不一致 |
| 429 | - | レート超過 |
| 503 | - | メンテナンス |
| 500 | 99999 等 | `my_id` の unique 違反（再試行なし）、同じ `device_id` の同時 sign_up の unique 違反。HTTP は例外コード次第（**未確認**。既存チェックは排他なしで、unique 制約が最後の砦） |

### 更新リソース・冪等

- 書く: `sys_player`、`sys_sharding_node_player`、`sys_sharding_node.current_player_count`、`sys_player_device`、`sys_player_token`。**trx には書かない。初期付与なし。**
- 更新リソース（`updated_resources`）は返さない（`#[DoesNotReturnUpdatedResources]`）。必要な現在値は直後の login で取る。
- 冪等キーは無効（認証前）。二重送信は 10004。プレイヤーロックなし。

## 4. POST /api/auth/sign_in

- ミドルウェア: `maintenance`、`throttle.auth:sign_in`。冪等・ロックなし。
- トランザクション: `executeWithTransaction`。

### Post

| 項目 | 型 | 必須 | 検証・最大長 |
| --- | --- | --- | --- |
| device_id | string | 必須 | `required|string|max:255` |
| device_info | object | **必須** | `required|array` |
| device_info.os | string | 必須 | `required|string|max:50` |
| device_info.os_version | string | 必須 | `required|string|max:50` |
| device_info.model | string | 必須 | `required|string|max:100` |
| device_info.app_version | string | 必須 | `required|string|max:20` |

`device_info` は検証するだけで**保存しない**。

### Response

sign_up と同じ 4 項目。違い:

- `sys_player` は DB から読むため `my_id` `name` `level` `level_exp` `vip_point` `total_paid_amount`（decimal:2 のため文字列 `"0.00"`）`last_login_at`（`Y-m-d H:i:s` の文字列または null）。
- `sys_player_device.last_login_at` は更新前の値の可能性がある（更新は別インスタンスに対して行うため。**未確認**）。
- `sys_player_device` / `sys_player_token` は新しいトークン行の内容。

### エラー

| HTTP | error_code | 条件 |
| --- | --- | --- |
| 299 | 10002 | `device_id` が未登録、またはデバイスのプレイヤーが無い |
| 422 | - | 検証違反 |
| 429 | - | IP あたり 15 分 30 回 / `device_id` あたり 15 分 10 回を超過（試行は処理前に数える） |
| 503 | - | メンテナンス |

### 更新リソース・冪等

- 書く: `sys_player_token`（そのプレイヤーの旧行を**全件物理削除** → 新行を INSERT）、`sys_player_device.last_login_at`。**`sys_player.last_login_at` は更新しない。**
- 旧リフレッシュトークンは無効になるが、旧アクセストークン（JWT）は `exp` まで有効。
- `updated_resources` は返さない。冪等・ロックなし。

## 5. POST /api/auth/refresh_token

- ミドルウェア: `maintenance`、`throttle.auth:refresh_token`。冪等・ロックなし。この API 自体は 401 を返さない（アクセストークンを見ない）。

### Post

| 項目 | 型 | 必須 | 検証 |
| --- | --- | --- | --- |
| refresh_token | string | 必須 | `required|string|size:64` |

### Response

```json
{ "token": { "access_token": "eyJ...", "refresh_token": "7ab1...(64桁)", "expires_in": 3600 } }
```

`token` の 3 項目はすべて非 null。`sys_player` などは返さない。

### エラー

| HTTP | error_code | 条件 |
| --- | --- | --- |
| 299 | 10003 | ハッシュが無い／`revoked_at` あり／期限切れ（`Invalid or expired refresh token`） |
| 299 | 10002 | トークンのプレイヤーまたは端末が無い |
| 422 | - | 64 文字でない |
| 429 | - | IP あたり 15 分 30 回 / トークン（ハッシュ）あたり 15 分 10 回 |
| 503 | - | メンテナンス |

### 更新リソース

書く: 使った `sys_player_token` 行を物理削除 → 新行 INSERT、`sys_player_device.last_login_at`。ローテーションのため旧リフレッシュトークンは 1 回しか使えない。同じトークンでの同時 2 本の送信は、片方が 10003 になりうる。

## 6. POST /api/auth/login

- ミドルウェア: `maintenance`、`auth.token`、`idempotency`、`player.lock`。
- 入力: ボディなし（`rules()` は空）。プレイヤー ID はトークンから取る。
- トランザクション: `HomeUseCase::handle()`。プレイヤーの存在確認（読み）はトランザクションの外、ログインボーナス判定から Response 組み立てまでを `executeWithTransaction` の中で行い、コミットは末尾で 1 回（sys と全 trx シャード）。
- **順序**: ログインボーナスの判定が、更新前の `sys_player.last_login_at` を使う。その後で `last_login_at` を現在時刻（`ApiSession::getNow()`）に更新する。

### ログインボーナスの判定

3 つの戦略を**すべて**判定し、該当したものをすべて付与する（最初の 1 つで止まらない）。実行順は優先度の降順: カムバック（200）→ VIP（150）→ 通常（100）。返却順は「VIP → 通常 → カムバック」（VIP と通常は実行順に並び、カムバックは最後に連結される）。クライアントは並び順に依存せず `type` で扱うこと。

| 種類 | 該当条件 | 日数 |
| --- | --- | --- |
| 通常 | `last_login_at` が null、またはゲーム内日付（`DAY_START_TIME`、既定 00:00:00）が今日と違う。有効な `daily` の 1 件がある | 前回 `day` + 1、`loop_days` 超過で 1 に戻る（初回は 1） |
| VIP | 上と同じ日付条件。`vip_point` から算出した VIP レベルに有効な `mst_vip_login_bonus` がある | 通常と同様（`trx_vip_login_bonus`） |
| カムバック | 休眠日数（前回ログインからの日数）が `required_absent_days` 以上の有効な `comeback` がある、または受取済みの期間内。今日（ゲーム内日付）まだ受け取っていない | 期間の何日目か。`valid_days` 超過で終了 |

- 受取は `trx_login_bonus` / `trx_vip_login_bonus` を更新し、`log_action_login_bonus_receive` / `log_action_vip_login_bonus_receive` に 1 報酬 1 行を書き、報酬を `ResourceDeliveryService` で所持データに反映する。
- **付与しなかった日は `login_bonus_list` が空配列**。要件の「優先度順に判定して返す」は、実装では「全種類を判定し、該当をすべて返す」。

### Response

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| sys_player | object | 不可 | `my_id`（string）、`name`（string。登録時は非 null だが DB は null 可）、`level`（int）、`level_exp`（int）、`vip_point`（int）、`total_paid_amount`（string `"0.00"`）、`last_login_at`（今回の更新後の値。書式は**未確認**） |
| trx_unit_list | array | 不可（空は `[]`） | 要素: `trx_unit_id`、`mst_unit_id`、`grade`、`level`、`level_exp`、`is_delete` |
| trx_item_list | array | 同上 | `mst_item_id`、`free_amount`、`paid_amount`、`is_delete` |
| trx_wallet_list | array | 同上 | `mst_item_id`、`free_amount`、`paid_amount`、`is_delete` |
| trx_diamond_list | array | 同上 | `platform`、`paid_amount`、`free_amount`、`is_delete` |
| trx_equipment_list | array | 同上 | `trx_equipment_id`、`mst_equipment_id`、`grade`、`level`、`level_exp`、`is_delete` |
| trx_stamina_list | array | 同上 | `type`、`current_stamina`、`recovery_rate_multiplier`（string `"1.00"`）、`last_recovery_at`、`is_delete`。自然回復を加算した現在値（DB は更新しない） |
| login_bonus_list | array | 不可（無ければ `[]`） | 下表 |

各 trx の行から `sys_player_id` `uuid` `created_at` `updated_at` は除かれる。`is_delete` の JSON 上の型（bool か 0/1）と日時列（`last_recovery_at`）の書式は**未確認**。

#### login_bonus_list の各項目（概要設計 U1 の答え）

`Resource::toArray()` の 6 キーが**常に**すべて出る（キーの欠落はない）。

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| unique_id | string | 不可 | 配布のたびに作る UUID v4 |
| type | string | 不可 | `ResourceType` の値（`item` `unit` `equipment` `diamond` `stamina` ほか。マスターの `content_type = wallet` は `content_mst_id`（`gold` `coin` 等）が type になる） |
| id | string | 不可 | `content_mst_id` |
| amount | int | 不可 | `content_quantity × amount` |
| expire_at | string | **常に null** | ログインボーナスは期限を設定しない（`convertToResource` が渡さない） |
| metadata | object | **null 可** | `is_paid` が真なら `{"is_paid": true}`、`content_option` があれば展開してマージ。どちらも無ければ null |

- `login_bonus_list` はクライアントが表示するための**受取結果**であり、所持データの正は上の `trx_*_list`。
- `updated_resources` は使わない（`#[DoesNotReturnUpdatedResources]`。現在値の全量を返すため）。

```json
{
  "sys_player": { "my_id": "aB3dE9xZ", "name": "kQ8wL2mN", "level": 1, "level_exp": 0, "vip_point": 0, "total_paid_amount": "0.00", "last_login_at": "2026-11-12T01:23:46.000000Z" },
  "trx_unit_list": [],
  "trx_item_list": [ { "mst_item_id": "potion", "free_amount": 3, "paid_amount": 0, "is_delete": false } ],
  "trx_wallet_list": [ { "mst_item_id": "gold", "free_amount": 5000, "paid_amount": 0, "is_delete": false } ],
  "trx_diamond_list": [],
  "trx_equipment_list": [],
  "trx_stamina_list": [],
  "login_bonus_list": [
    { "unique_id": "5f1c2d9e-...", "type": "gold", "id": "gold", "amount": 5000, "expire_at": null, "metadata": null }
  ]
}
```

新規プレイヤーの初回 login は、`trx_*_list` が空（初期付与なし）のまま、`login_bonus_list` に 1 日目の報酬が入る（付与された分は対応する `trx_*_list` にも反映される）。

### エラー

| HTTP | error_code | 条件 |
| --- | --- | --- |
| 401 | - | トークン欠落・不正・期限切れ |
| 299 | 99999 | `sys_player` が無い（`SystemDataException::generic`。PLAYER_NOT_FOUND ではない） |
| 299 | 各種 | 報酬配布・マスターの不整合など（未確認。個別コードは配布先の実装次第） |
| 409 | 40900 | 冪等キーが処理中、またはプレイヤーロックを 5 秒待って取れない |
| 503 | 50300 | ロック基盤（Redis）不達 |
| 503 | - | メンテナンス |

### 更新リソース・冪等・排他

- 書く: `sys_player.last_login_at`、`trx_login_bonus`、`trx_vip_login_bonus`、`log_action_*_login_bonus_receive`、報酬の反映先 trx。
- 冪等: `X-Unique-Request-Identifier` ありで有効（24 時間）。**無いと素通し**（1 節）。排他: あり。

## 7. GET /api/agreement/pending

- ミドルウェア: `maintenance`、`auth.token`、`idempotency`（GET のため素通し）、`player.lock`（GET のため素通し）。**冪等・ロックとも実質なし**。
- トランザクションなし。入力なし。

### Response

| 項目 | 型 | null | 説明 |
| --- | --- | --- | --- |
| agreement_list | array | 不可（無ければ `[]`） | 公開済み（`published_at <= 今`）の必須規約のうち、`trx_agreement` に無いもの |
| agreement_list[].sys_agreement_id | int | 不可 | |
| agreement_list[].version | string | 不可 | |
| agreement_list[].title | string | 不可 | |
| agreement_list[].url | string | 不可 | 規約本文の URL |
| agreement_list[].published_at | string | 不可 | `Y-m-d H:i:s`（テストで固定） |

```json
{ "agreement_list": [ { "sys_agreement_id": 1, "version": "1.0.0", "title": "利用規約", "url": "https://example.com/terms/1.0.0", "published_at": "2026-01-01 00:00:00" } ] }
```

- エラー: 401（トークン不正）、503（メンテナンス）。業務エラーは無い。
- 読む: `sys_agreement`、`trx_agreement`。更新なし。

## 8. POST /api/agreement/consent

- ミドルウェア: `maintenance`、`auth.token`、`idempotency`、`player.lock`。
- トランザクション: `executeWithTransaction`（書くのは trx のプレイヤーのシャード）。

### Post

| 項目 | 型 | 必須 | 検証 |
| --- | --- | --- | --- |
| sys_agreement_id | integer | 必須 | `required|integer|min:1` |

### Response

空の JSON オブジェクト（テストでは `[]` と比較され、ボディは `[]` または `{}` のどちらかで返る。**厳密な書式は未確認**）。

### エラー

| HTTP | error_code | 条件 |
| --- | --- | --- |
| 299 | 24001 | 規約が存在しない、公開前、または `is_required = false` |
| 299 | 24002 | 同意済み |
| 422 | - | 検証違反 |
| 401 / 409 / 503 | - / 40900 / 50300 | トークン、冪等・ロック競合、基盤不達 |

### 更新リソース・冪等

- 書く: `trx_agreement` に 1 行 INSERT（`consented_at` はリクエスト時刻）。`updated_resources` は返さない（対象外）。
- 規約が複数あれば 1 件ずつ送る（1 リクエスト 1 規約）。
- 冪等キーあり: 再送は保存済みの 200 を返す。**キー無し: 再送は 24002 になる**。

## 9. POST /api/auth/version

- ミドルウェア: `maintenance`（`auth/version` は `api/config/maintenance.php` の `excluded_routes` に含まれ、メンテ中も通る）、`auth.token`、`idempotency`、`player.lock`。
- 入力・Response・エラーは GET と同じ（`deploy_version` は body 優先、`Deploy-Version` ヘッダーも可）。
- ロックと冪等の対象。クライアントは現状 GET を使う（概要設計のとおり）。GET との違いは、トークンが必要なこと、冪等・ロックの対象になること（競合 409(40900)、ロック基盤の不達 503(50300)）。

## 10. 概要設計 U1〜U3 の回答まとめ

| # | 回答 |
| --- | --- |
| U1 | `login_bonus_list` の各項目は 6 キーが常にあり、`expire_at` は常に null、`metadata` は null 可（`is_paid` / `content_option` が無いとき）、他は非 null。詳細は 6 節 |
| U2 | `trx_agreement` は PK(sys_player_id, sys_agreement_id)、`consented_at` / `created_at`、`is_delete`・`updated_at` なし。[db.md](db.md) 4 節 |
| U3 | サーバーは**全 API の 200 応答**で `X-Master-Hash` を比較する（1 節）。クライアントがどの API に付けているかはサーバー側からは分からず、未確認 |

## 11. S3（API 実装）で必要なサーバー変更

結論: **機能追加・仕様変更の必須はなし。現行実装のまま、テスト追加（と下の判断 1 点）のみ。** 概要設計の食い違い表（D1〜D9。表に載っているのは D2〜D9）への判断は次のとおり。

| # | サーバー変更 | 理由 |
| --- | --- | --- |
| D2 | 不要 | login は `sys_player`・trx 6 種・`login_bonus_list` を常に返す（`HomeSnapshotTest` が固定）。新規は空配列 |
| D3 | 不要 | 一覧は空なら `[]`、`sys_player` は非 null。ただし `login_bonus_list[].expire_at` と `metadata` は null になりうるので、クライアントは null 許容にする |
| D4 | 不要 | login 失敗は 299 / 401 / 409 / 503 を返す。クライアント側で進ませない |
| D5 | 不要 | 503 / 429 / 409(40900) を返す。ただし 401・429・503(メンテ) は `error_code` を持たない。HTTP ステータスで分類する前提を設計に明記する |
| D6 | 不要 | キーが無いと素通しは現行仕様。クライアントが毎回キーを付ける。サーバーでキー必須化はしない（する場合は別機能） |
| D7 | **要判断** | `url` は `/masterdata/{hash}/{file}` の**相対パス**のみで、配信ホストがサーバーから返らない。「サーバーが返す URL を使う」は現状のままでは成立しない。既定案: サーバーは変更せず、クライアントが配信ホストを設定値として持つ（D7 の方針文を直す）。サーバーに寄せるなら `version` に基準 URL を足す加算的な変更（既存クライアントを壊さない）になる |
| D8 | 不要 | version は常に応答する。配信可能なデプロイが無いときだけ 299 / 99999 |
| D9 | 不要 | refresh の 10003 / 10002 は 299。無効トークンの 401 は返さない |

追加で S3 のテストに入れるもの（実装は変えない）: login の初回／同日 2 回目／日跨ぎ、キー無し再送の挙動（login は空、consent は 24002）、sign_up の 10004、sign_in の 10002、refresh の 10003 と旧トークンの再利用、`X-Master-Update-Required` の付与（200 のみ・299 に付かない）、POST /auth/version のロック。

その他、設計に反映が必要な事実:

- F10（旧トークンの無効化）はリフレッシュトークンのみ。旧アクセストークンは最長 1 時間有効。
- sign_in は `sys_player.last_login_at` を更新しない（login が更新する）。ログインボーナスの判定は login のみで起きる。
- 概要設計の API 一覧にある「GET /agreement/pending のプレイヤーロック」は、実装では GET が対象外。
- sign_up と sign_in で `device_info.app_version` の最大長が異なる（50 / 20）。sign_in の `device_info` は必須だが保存されない。

## 12. 未解決点

- Accept ヘッダーが無いときの 422 の挙動。
- `X-Master-Hash` をクライアントがどの API に付けているか。
- sign_up Response の `sys_player` の実際の項目、`last_login_at` ほか日時の書式、`is_delete` の型（実行で確認する）。
- consent の空ボディの厳密な形。
- 冪等ミドルウェア（Redis 不達時）の挙動。
- 同じ `device_id` の同時 sign_up、`my_id` 衝突のときの HTTP ステータス。
- login の報酬配布で起きうる業務エラーのコード一覧。
- D7 の判断（配信ホストの出どころ）。
