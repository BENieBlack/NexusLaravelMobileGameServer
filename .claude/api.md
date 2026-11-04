# HTTP API の規約

エンドポイントを追加・変更するとき、レスポンスの形やエラーの返し方を決めるとき、バージョンチェックとマスターデータ配信の流れを追うときに読む。
クラスごとの書き方（Controller / Request / Response の実装）は [coding-standards.md](./coding-standards.md)、例外とエラーコード体系は [architecture.md](./architecture.md) を参照。

## ルーティング

- 定義は `api/routes/api.php` だけ。Laravel の api プレフィックスにより URL は `/api/...` になる。バージョンのプレフィックス（`/v1`）は無い
- パスは `/{リソース}/{動作}`。リソースは単数形、動作はスネークケースの動詞（`/player/me`, `/friend/list`, `/unit/level_up`, `/mailbox/receive_all`）。サブリソースは間に挟む（`/friend/apply/send`, `/chat/group/invite`）
- 参照は GET、状態を変えるものは POST。PUT / PATCH / DELETE とパスパラメータ（`/{id}`）は使っていない。対象の ID はクエリかボディで渡す

### ミドルウェアの構成

エイリアスは `api/bootstrap/app.php` で登録している。

| 適用範囲 | ミドルウェア | 役割 |
|---|---|---|
| 全API（先頭に追加） | `ResolveLanguage` | `Accept-Language` から言語を決め `ApiSession::getLanguage()` に載せる。対応言語と既定（`ja`）は `api/config/language.php` |
| 全API（先頭に追加） | `CheckMasterHash` | 後述。`X-Master-Hash` が古ければレスポンスヘッダーで知らせる |
| メンテ除外以外のすべて | `maintenance` | メンテ中は 503。除外ルート・除外IPは `api/config/maintenance.php` |
| ログイン後のAPI | `auth.token` + `idempotency` | アクセストークン検証と二重送信防止 |
| `/auth/sign_up` | `client.signature` + `throttle.signup` | クライアント署名検証とレート制限 |
| `/auth/sign_in`, `/auth/refresh_token`, `/account/transfer_code/transfer`, `/account/external/transfer` | `throttle.auth:{名前}` | 認証試行のレート制限（引き継ぎコードはコードごとにも数える） |
| ギルドの参照系（認証不要） | `throttle.public:guild_read` | IP単位のレート制限 |
| `/admin/maintenance/*` | `auth.admin` | `ADMIN_TOKEN` の Bearer 認証と任意のIP制限 |

新しいエンドポイントは、プレイヤーのデータを扱うなら `maintenance` → `auth.token` + `idempotency` のグループの中に置く。

### 認証（auth.token）

- `Authorization: Bearer {access_token}`。トークンは `/auth/sign_up` か `/auth/sign_in` で発行され、`/auth/refresh_token` で更新する（`token: {access_token, refresh_token, expires_in}`）
- 検証は `NexusSecurity\Middleware\VerifyAccessToken`。成功するとプレイヤーIDを `ApiSession` に載せ、Controller は `ApiSession::getSysPlayerId()` で取り出す
- 自分の `sys_player_id` をボディやクエリで受け取らない。他プレイヤーを指すときは `my_id` を使う（例: `/friend/apply/send` の `my_id`）

### リクエストヘッダー

| ヘッダー | 対象 | 内容 |
|---|---|---|
| `Authorization` | ログイン後のAPI | `Bearer {access_token}` |
| `X-Unique-Request-Identifier` | ログイン後の POST | 冪等キー。再送には保存済みのレスポンスを返し、処理中の重複は 409。既定24時間 |
| `X-Client-Timestamp` / `X-Client-Nonce` / `X-Client-Signature` | `/auth/sign_up` | `HMAC-SHA256("{timestamp}:{nonce}:{body}", CLIENT_SECRET)`。テストでは `Tests\TestCase` が無効化している |
| `Accept-Language` | 全API | 言語の選択 |
| `X-Master-Hash` | 全API（任意） | クライアントが持つマスターデータのハッシュ |
| `Deploy-Version` | `/auth/version` | ボディの `deploy_version` が無いときに使う |

冪等キャッシュは HTTP 2xx を保存するため、業務エラー（299）も同じキーで再送すると同じエラーが返る。

## リクエストの規約

- キーはスネークケース。Request クラスは `api/app/Http/Requests/{リソース}/` に置き、`rules()` と型付きの getter を持つ
- `*_id` のキーは参照先のテーブルが分かる名前にする（`mst_item_id`, `trx_mailbox_id`）。多相参照でマスターを指すものは `*_mst_id`。`tests/Architecture/ResponseKeyNamingTest.php` が Requests と Responses を検査し、例外は同テストの `ALLOWED` に列挙している
- 列挙値はスネークケースの文字列（`billing_platform` の `app_store`, `google_play` など）
- 例: 課金 `POST /api/in_app_purchase/buy`（`BuyRequest`）は `mst_in_app_purchase_id`, `platform`, `billing_platform`, `receipt`, `transaction_id`（任意）, `product_id`

## レスポンスの規約

- 成功は HTTP 200 で、Response クラスの `toArray()` をそのまま返す。`data` や `success` で包まない
- キーはスネークケース
- モデルのデータはテーブル名をキーにする（`sys_player`, `trx_unit_list`）。モデルの配列化は `toResponseArray()` を使う（[coding-standards.md](./coding-standards.md) 参照）
- 配列のキーには `_list` を付ける（`trx_item_list`, `login_bonus_list`, `owned_line_id_list`）。`api/app/Domain/Login/Responses/HomeResponse.php` が見本
- 成否を表す `success` は返さない。失敗は `GameException`（HTTP 299）で伝える。`_list` と `success` は `api/tests/Architecture/ResponseKeyNamingTest.php` が検査する。キーで引く連想配列（`tables` など）は同テストの `OBJECT_KEYS` に足す
- `CheckMasterHash` は 200 のレスポンスにだけ `X-Master-Update-Required: true` を付ける。ボディは変えない

## エラーの形式

| 種類 | HTTP | ボディ | 出どころ |
|---|---|---|---|
| 業務エラー（`GameException` 系） | 299 | `{"error_code": int, "message": string}` | `_BaseController::execute()`。execute の外で投げた場合は `bootstrap/app.php` の例外ハンドラ |
| その他の例外（execute 内） | 例外コードが100〜599ならその値、それ以外は500 | 同上 | `_BaseController::handleException()` |
| バリデーション失敗 | 422 | Laravel 標準（`message`, `errors`） | FormRequest（上書きしていない） |
| トークン不正 | 401 | `{"error": string}` | `auth.token` |
| 署名不正 | 401 | `{"error": string, "message": string}` | `client.signature` |
| レート制限 | 429 | `{"error": "TOO_MANY_REQUESTS", "message", "retry_after"}` など | `throttle.*` |
| 冪等キーが処理中 | 409 | `{"error_code": 40900, "message"}` | `idempotency` |
| メンテナンス中 | 503 | `{"error", "message", "title", "start_at", "end_at"}` | `maintenance` |

- アプリが返すエラーは 299 + `error_code`（int）。クライアントはステータス 299 で業務エラーと判定し、`error_code` で種別を見分ける
- 本番（`APP_ENV=production`）では `message` が固定文言にマスクされる（`Nexus\Core\ValueObjects\ErrorResponse::maskForProduction()`）
- エラーコードの割り当ては [architecture.md](./architecture.md) を参照

## バージョンチェックとマスター/アセット配信

### クライアントから見た流れ

1. 起動時に `GET /api/auth/version`（認証不要・メンテ中も通る）か、ログイン後に `POST /api/auth/version` を呼ぶ。手元の `sys_deploy.id` を `deploy_version`（ボディ）か `Deploy-Version` ヘッダーで送る
2. サーバーは `is_active = true` かつ `start_at` を過ぎた `sys_deploy` のうち `deploy_key` が最大のものを最新とする（`App\Repositories\Sys\SysDeployRepository::selectLatestDownloadable()`、キャッシュあり）
3. 送られた ID が最新と同じなら `needs_update: false`。違えば最新のデプロイ情報を返す
4. クライアントは `master.tables` の各ファイルのうちハッシュが変わったものを取り直す
5. 通常のAPIには `X-Master-Hash` に `master.hash` を付けて送る。古ければ `X-Master-Update-Required: true` が返るので、再度 `/auth/version` を呼ぶ

ダウンロード可能なデプロイが1件も無いときは `SystemDataException::deploy()` の 299 になる。

### VersionResponse

`api/app/Domain/Version/Responses/CheckResponse.php`。

```json
{
  "needs_update": true,
  "maintenance": { "...": "メンテ中のときだけ" },
  "sys_deploy_id": 12,
  "latest_deploy_key": 2026101301,
  "master": {
    "sys_deploy_master_id": 8,
    "hash": "マニフェストのハッシュ",
    "tables": {
      "mst_item": { "hash": "...", "url": "/masterdata/{hash}/mst_item_{hash}.sqlite", "file_size": 12345 }
    }
  },
  "asset": { "sys_deploy_asset_id": 8, "hash": "..." }
}
```

- `needs_update: false` のときは `needs_update` と（あれば）`maintenance` だけ
- `master.tables` のキーはテーブルグループ名。`mst_xxx` と `mst_xxx__l10n` は同じ SQLite ファイルに入り、キーは親テーブル名になる
- `master.hash` は各ファイルのハッシュから作るマニフェストのハッシュで、`X-Master-Hash` の比較対象
- `asset` は ID とハッシュだけ。運営ツールのマスターデプロイはダミーのアセットレコードを作っており、アセット配信の実体は無い

### 配信ファイルの作られ方

マスターデータは運営ツールが mst DB からテーブルグループ単位の SQLite を書き出し、`sys_deploy_master` / `sys_deploy_master_table` / `sys_deploy` に登録する（`tool/app/Services/MasterDataExporter.php`, `MasterDeployService.php`）。手順は [tool.md](./tool.md) を参照。`url` は tool の `public/masterdata/` 配下を指す相対パス。

## データ引き継ぎ

機種変更で同じアカウントを新端末に移す。本人確認の方法は2つで、どちらも成功すると `/auth/sign_in` と同じ形（`sys_player`, `sys_player_device`, `sys_player_token`, `token`）を返す。

| 方法 | 旧端末（ログイン後） | 新端末（認証不要） |
|---|---|---|
| 引き継ぎコード | `POST /account/transfer_code/issue`（`password`）→ `transfer_code`, `expires_at` | `POST /account/transfer_code/transfer`（`transfer_code`, `password`, `device_id`, `device_info`） |
| 外部アカウント | `POST /account/external/link`（`provider`, `id_token`）。一覧は `GET /account/external/list`、解除は `POST /account/external/unlink` | `POST /account/external/transfer`（`provider`, `id_token`, `device_id`, `device_info`） |

- 引き継ぐと、そのプレイヤーの他の端末と発行済みトークンは消える（旧端末は sign_in できなくなる）。新端末で別のプレイヤーが遊んでいたら、端末はこのプレイヤーに付け替わる
- 引き継ぎコードはプレイヤーごとに1つ。発行し直すと前のコードは使えず、使ったコードも消える。有効期限・ロックの回数と時間は `api/config/account_transfer.php`
- `provider` は `apple` / `google`。IDトークンは `NexusAuth\Services\JwksIdTokenVerifier` が公開鍵セット（JWKS）で検証する。発行先（`APPLE_CLIENT_IDS` / `GOOGLE_CLIENT_IDS`）が空の種類は使えない（`EXTERNAL_PROVIDER_UNAVAILABLE`）

## 交換所

ダイヤ・コイン・アイテムを消費して、アイテム・ユニット・装備などと交換する（`packages/nexus-trade`、`App\Domain\Trade`）。課金（`in_app_purchase`）で現金をダイヤに替え、ダイヤをガチャや交換所で使う。

- `GET /trade/list` … 開催中の交換所（`mst_trade`）にある、交換期間内の商品（`mst_trade_product`）を `trade_product_list` で返す。各商品は消費種別・消費個数・上限・今の周期での交換回数・残り回数・手に入るもの
- `POST /trade/exchange`（`mst_trade_product_id`, `quantity`）… 消費個数 × `quantity` を消費し、手に入るもの × `quantity` を配る。結果は `trade_product`
- 消費種別（`cost_type`）: `diamond`（無償→有償の順）/ `paid_diamond`（有償のみ）/ `item` / `wallet`（コイン・ゴールドなど。`cost_mst_id` に `mst_item.id`）。足りなければ `DIAMOND_NOT_ENOUGH` / `ITEM_NOT_ENOUGH`
- 交換回数の上限（`trade_limit`）は `reset_type`（`none` / `daily` / `weekly` / `monthly`）の周期で数え直す。境界はゲーム内日付（`day_start_time`）、週は ISO 8601

## エンドポイントの分類

一覧は `api/routes/api.php` を見る。分類は次のとおり。

- 認証不要: `auth`（sign_up / sign_in / refresh_token / version）、`account` の引き継ぎ（transfer_code/transfer / external/transfer）、`maintenance/status`、`guild` の参照系
- ログイン後: `auth`（version / login）、`account`（引き継ぎコードの発行、外部アカウントの一覧・紐付け・解除）、`player`、`in_app_purchase`、`item`、`unit`、`equipment`、`friend`、`album`、`guild`、`mailbox`、`gacha`、`notification`、`chat`、`reward_track`、`mission`、`trade`
- 管理用: `admin/maintenance/*`（`auth.admin`）
