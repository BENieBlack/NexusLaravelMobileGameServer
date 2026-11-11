# タイトル〜ホーム シーケンス

既に実装済みの機能のシーケンス。実装が正。各図の注記に、トランザクション境界・書くテーブル・読むテーブルを書く。項目の詳細は [api.md](api.md)、テーブルは [db.md](db.md)。

共通の前提:
- `executeWithTransaction` は sys と全 trx シャードで同時にトランザクションを開始し、末尾でまとめてコミットする（途中で例外ならすべてロールバック）。
- 冪等・ロック・nonce・レート制限のカウンタは Redis（Cache）。DB テーブルではない。
- `CheckMasterHash` は全 API の応答後処理。`X-Master-Hash` があり、応答が HTTP 200 で、ハッシュが不一致のときだけ `X-Master-Update-Required: true` を付ける（図では省略）。

## 1. 新規（version → sign_up → login → agreement）

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー
    participant SYS as sys DB
    participant TRX as trx シャード
    participant R as Redis

    C->>S: GET /auth/version（deploy_version 任意）
    Note over S: メンテ除外・認証なし・トランザクションなし
    S->>SYS: 読む: sys_deploy, sys_deploy_master(_table), sys_deploy_asset, sys_maintenance
    S-->>C: 200 needs_update, master(hash, tables[url 相対パス]), maintenance?

    C->>S: POST /auth/sign_up（署名ヘッダー, device_id, device_info）
    S->>R: 署名検証（nonce 重複）/ レート（IP 10/h, device 3/h）
    rect rgb(235, 245, 255)
    Note over S,SYS: executeWithTransaction 開始（書くのは sys のみ）
    S->>SYS: 読む: sys_player_device（uuid）
    S->>SYS: 書く: sys_player（my_id, uuid, name を乱数で INSERT）
    S->>SYS: 読む: sys_sharding, sys_sharding_node, sys_sharding_node_player
    S->>SYS: 書く: sys_sharding_node_player, sys_sharding_node.current_player_count+1
    S->>SYS: 書く: sys_player_device, sys_player_token（ハッシュ）
    Note over S,SYS: コミット（trx には書かない。初期付与なし）
    end
    S-->>C: 200 sys_player, sys_player_device, sys_player_token, token

    C->>S: POST /auth/login（Bearer, X-Unique-Request-Identifier）
    S->>R: 冪等キーに処理中の印を付ける → プレイヤーロック取得（30 秒、待ち 5 秒）
    S->>SYS: 読む: sys_player
    rect rgb(235, 245, 255)
    Note over S,TRX: executeWithTransaction 開始
    S->>SYS: 読む: mst_login_bonus*, mst_vip_*（mst DB）, trx_login_bonus, trx_vip_login_bonus
    Note over S: last_login_at=null → 通常ボーナス 1 日目。更新前の last_login_at で判定
    S->>TRX: 書く: trx_login_bonus, log_action_login_bonus_receive, 報酬の反映先 trx_*
    S->>SYS: 書く: sys_player.last_login_at（判定の後）
    S->>TRX: 読む: trx_unit/item/wallet/diamond/equipment/stamina
    Note over S,TRX: コミット
    end
    S->>R: 冪等レスポンスを 24 時間保存 → ロック解放
    S-->>C: 200 sys_player, trx_*_list（6 種）, login_bonus_list

    C->>S: GET /agreement/pending（Bearer）
    Note over S: トランザクションなし。冪等・ロックは GET のため素通し
    S->>SYS: 読む: sys_agreement（is_required かつ published_at<=今）
    S->>TRX: 読む: trx_agreement
    S-->>C: 200 agreement_list（無ければ []）

    loop 未同意の規約ごと
        C->>S: POST /agreement/consent（sys_agreement_id, 冪等キー）
        S->>R: 冪等キーに処理中の印を付ける → プレイヤーロック
        rect rgb(235, 245, 255)
        Note over S,TRX: executeWithTransaction
        S->>SYS: 読む: sys_agreement
        S->>TRX: 読む: trx_agreement / 書く: trx_agreement（INSERT 1 行）
        end
        S-->>C: 200 空オブジェクト
    end
```

## 2. 既存（sign_in → login）

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー
    participant SYS as sys DB
    participant TRX as trx シャード
    participant R as Redis

    C->>S: GET /auth/version
    S-->>C: 200（図 1 と同じ）

    C->>S: POST /auth/sign_in（device_id, device_info）
    S->>R: レート確認（IP 30/15分, device 10/15分。処理前にカウント）
    rect rgb(235, 245, 255)
    Note over S,SYS: executeWithTransaction（書くのは sys のみ）
    S->>SYS: 読む: sys_player_device, sys_player
    S->>SYS: 書く: sys_player_token（そのプレイヤーの旧行を物理削除 → 新行 INSERT）
    S->>SYS: 書く: sys_player_device.last_login_at（sys_player.last_login_at は更新しない）
    Note over S,SYS: コミット
    end
    S-->>C: 200 sys_player, sys_player_device, sys_player_token, token

    C->>S: POST /auth/login（Bearer, 冪等キー）
    S->>R: 冪等キーに処理中の印を付ける → プレイヤーロック
    rect rgb(235, 245, 255)
    Note over S,TRX: executeWithTransaction
    S->>SYS: 読む: sys_player（更新前の last_login_at で日跨ぎ判定）
    alt 今日初回（ゲーム内日付が変わった）
        S->>TRX: 書く: trx_login_bonus / trx_vip_login_bonus, log_action_*_login_bonus_receive, 報酬の反映先 trx_*
    else 今日 2 回目以降
        Note over S: 通常・VIP は付与なし。カムバック期間中なら付与
    end
    S->>SYS: 書く: sys_player.last_login_at
    S->>TRX: 読む: trx 6 種（スタミナは自然回復を加算して返すが DB は更新しない）
    end
    S-->>C: 200 全量 + login_bonus_list（付与なしなら []）
```

## 3. sign_in で未登録（10002）→ sign_up 切り替え

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー
    participant SYS as sys DB

    C->>S: POST /auth/sign_in（device_id）
    rect rgb(235, 245, 255)
    Note over S,SYS: executeWithTransaction
    S->>SYS: 読む: sys_player_device（uuid）→ 該当なし
    Note over S,SYS: 例外のためロールバック（書き込みなし）
    end
    S-->>C: 299 {error_code: 10002, message}
    Note over C: 10002 を契機に sign_up へ切り替える
    C->>S: POST /auth/sign_up（署名つき）
    S-->>C: 200（図 1 の sign_up と同じ。sys に 5 テーブル書く）
    Note over C,S: sign_in の試行は device 単位の 15 分 10 回に数えられ、sign_up は device 単位の 1 時間 3 回に数えられる（成否によらず）
```

同じ `device_id` で既に登録済みの端末が sign_up すると、299 / 10004 が返る（sys_player_device に既存行があるため。書き込みなしでロールバック）。

## 4. トークン期限切れ（401 → refresh_token → 再送）

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー
    participant SYS as sys DB
    participant R as Redis

    C->>S: POST /agreement/consent など（期限切れのアクセストークン）
    Note over S: auth.token で JWT の exp を検証（DB は見ない）
    S-->>C: 401 {error: "Invalid or expired access token"}（error_code なし）
    C->>S: POST /auth/refresh_token（refresh_token 64 桁）
    S->>R: レート確認（IP 30/15分, トークンのハッシュ 10/15分）
    rect rgb(235, 245, 255)
    Note over S,SYS: executeWithTransaction（書くのは sys のみ）
    S->>SYS: 読む: sys_player_token（ハッシュ一致・revoked_at null・期限内）, sys_player, sys_player_device
    S->>SYS: 書く: 使った sys_player_token 行を物理削除 → 新行 INSERT, sys_player_device.last_login_at
    Note over S,SYS: コミット
    end
    S-->>C: 200 token（新しい access_token, refresh_token, expires_in=3600）
    C->>S: 元のリクエストを再送（新しいアクセストークン）
    Note over C,S: 冪等キーは、401 で処理されていなければ同じキーでよい（401 は保存されない）
    S-->>C: 200

    alt リフレッシュトークンが無効・期限切れ・使用済み
        S-->>C: 299 {error_code: 10003}
    else プレイヤーまたは端末が無い
        S-->>C: 299 {error_code: 10002}
    end
```

401 は冪等ミドルウェアより手前（`auth.token`）で返るので、冪等キーは消費されない。

## 5. 異常系（メンテナンス 503・レート超過 429・排他 409）

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー
    participant R as Redis

    Note over C,S: (a) メンテナンス
    C->>S: POST /auth/login（メンテ中）
    Note over S: maintenance ミドルウェアが先に判定。DB は書かない。トランザクションなし
    S-->>C: 503 {error: "Service Unavailable", message, title, start_at, end_at}
    C->>S: GET /auth/version（メンテ除外ルート）
    S-->>C: 200 needs_update + maintenance

    Note over C,S: (b) レート超過
    C->>S: POST /auth/sign_in（上限超過）
    S->>R: カウンタ確認 → 上限
    S-->>C: 429 {error: "TOO_MANY_REQUESTS", message, retry_after}（Retry-After ヘッダー付き）。DB は触らない

    Note over C,S: (c) 排他・冪等の競合
    C->>S: POST /auth/login（キー K, 1 回目）
    S->>R: 冪等キー K に処理中の印を付ける → プレイヤーロック取得 → 処理中
    C->>S: POST /auth/login（同じキー K を再送）
    S-->>C: 409 {error_code: 40900}（処理中。X-Idempotency-Cache: PROCESSING）
    C->>S: POST /agreement/consent（別のキー, 同じプレイヤー）
    S->>R: プレイヤーロックを最大 5 秒待つ
    S-->>C: 409 {error_code: 40900}（取れなかったとき。DB は触らない）
    Note over S,R: 1 回目の完了後、同じキー K の再送は保存済み 200 を返す（HIT）

    Note over C,S: (d) ロック基盤（Redis）の不達
    C->>S: POST /auth/login
    S-->>C: 503 {error_code: 50300}
```

注記:
- (a) の 503 は `error_code` を持たない。メンテ除外ルートは `auth/version` `maintenance/status` `admin/maintenance/*` `up`。
- (c) の 409 は、冪等キーの処理中とプレイヤーロックの取得失敗のどちらも `error_code: 40900`。メッセージで区別でき、再試行すれば通る。
- 業務エラー（299）は冪等キーで保存されるため、再試行するときは新しいキーを使う（api.md 1 節）。
- (b) の sign_up の 429 は、署名検証の後に評価される（署名エラーは 401 で、カウントされない）。
