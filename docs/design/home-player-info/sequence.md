# ホーム＋プレイヤー情報 シーケンス設計（S2）

機能 `home-player-info` のサーバーの通信シーケンス。対象は (1) login でのスタミナ回復と MstConfig の読み出し、(2) MstConfig に値が無いとき、(3) マスター更新の前後で値が違うとき、(4) サインアップ時の初期データ（Q9。人の追加依頼）、(5) マスターのデプロイ（U1）。

- DB: [db.md](db.md)、API: [api.md](api.md)
- login・sign_up・sign_in の基本の流れ: [title-to-home の sequence.md](../title-to-home/sequence.md)（複製せず、差分だけ書く）
- 「読んだ」= コードを読んだだけ。実行で確かめていない部分は各節に書く。

## 1. 正常系: login でスタミナの回復を適用して返す

login の流れは title-to-home のとおり。ここは**スタミナ回復の部分と MstConfig の読み出し**だけを書く。

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー（HomeUseCase）
    participant ST as StaminaService（nexus-stamina）
    participant CFG as 回復間隔の読み出し口（アダプタ・MstConfigService）
    participant R as Redis（mst キャッシュ）
    participant MST as mst DB
    participant TRX as trx シャード

    C->>S: POST /auth/login（Bearer, 冪等キー）
    Note over S: ミドルウェア: maintenance → auth.token → idempotency → player.lock
    S->>S: プレイヤーの存在確認（トランザクションの外）
    rect rgb(235, 245, 255)
    Note over S,TRX: executeWithTransaction 開始（sys・全 trx シャード・log をまとめて開始）
    S->>S: ログインボーナス判定・付与、last_login_at 更新（title-to-home）
    S->>TRX: 読む: trx_unit / item / wallet / diamond（全行）/ equipment
    S->>S: trx_diamond は全行のうち platform が ApiSession::getPlatform()（X-Platform）の 1 行を選ぶ（無ければ null。D5。api.md 11.2）
    S->>ST: findAllStaminas(sysPlayerId)
    ST->>TRX: 読む: trx_stamina（全タイプ）
    ST->>R: 読む: mst_player_level（最大スタミナ。既存）
    ST->>CFG: findRecoveryIntervalSeconds()（findAllStaminas につき 1 回）
    CFG->>R: GET mst:mst_config:all（TTL 3600 秒）
    alt キャッシュなし
        CFG->>MST: SELECT * FROM mst_config
        CFG->>R: 保存
    end
    CFG-->>ST: 回復間隔（秒。整数 1 以上。不正・欠落は既定値: 3 節）
    loop スタミナの各行（最大 4 種）
        Note over ST: 最大値以上なら回復しない。それ以外は<br/>回復点数 = floor(経過秒 × 倍率 ÷ 回復間隔)<br/>current_stamina = min(現在値 + 回復点数, 最大値)<br/>last_recovery_at を floor(点数 × 間隔 ÷ 倍率) 秒進める
    end
    ST-->>S: 回復反映済みの Stamina 一覧（DB には保存しない）
    S->>S: 応答用に写しを作る（キャッシュ上のモデルは書き換えない）
    S->>TRX: 書く: ログインボーナス分のみ（スタミナは書かない）。無償ダイヤは DiamondDeliveryHandler の既定 platform = X-Platform の行へ（D5。api.md 11.3）
    Note over S,TRX: 末尾で flush → 全接続をコミット
    end
    S-->>C: 200 sys_player + trx 6 種（trx_diamond は X-Platform の 1 行のオブジェクト・無ければ null。trx_stamina_list は回復反映済み）+ login_bonus_list
```

- **D5（2026-11-14）**: login のダイヤは `trx_diamond_list`（配列）ではなく `trx_diamond`（`X-Platform` の 1 行）を返す。ログインボーナスの無償ダイヤの付与先も、従来の `Apple` 固定から `X-Platform` の行に変わる。付与の既定 platform は、`DiamondDeliveryHandler` が `DefaultPlatformProviderInterface`（API 側の実装が `ApiSession::hasPlatform()` を見て、無ければ `null` → `Apple` にフォールバック）から得る。HTTP 以外の経路（ジョブ・CLI）は `Apple`。api.md 11 節。無償ダイヤの最終形は `trx_item` の `free_diamond`（platform 共通）で、移行前に別 platform の `free_amount` に残る分が見えないのは暫定（D6）。
- MstConfig の読み出しは、スタミナの行の数に関わらず**回復間隔の読み出しは `findAllStaminas` につき 1 回**（行ごとに読み直さない）。テーブルは Redis に 1 キーで持つので、リクエスト内の実際の読み出しは Redis の GET が 1 回以下で、ミス時だけ mst DB に SELECT が 1 回（db.md 2 節の 4）。
- mst は読み取りなので、トランザクションの対象外（mst の接続は `executeWithTransaction` が開始する接続に含まれない。読んだ）。
- 同じ回復間隔が、消費（`consumeStamina`）・回復アイテム（`recoverStaminaByItem`）・満タンまでの時間・レベルアップのリスナーでも使われる。これらは書き込みを伴い、**回復を反映した値を保存する**（db.md 2 節）。値の出どころはすべて同じ読み出し口。

### トランザクション境界（login）

| 範囲 | 内容 |
| --- | --- |
| トランザクションの外 | プレイヤーの存在確認、MstConfig・`mst_player_level` の読み出し（mst は読み取りで、トランザクションに入らない。キャッシュ越し） |
| トランザクションの中 | ログインボーナスの付与、`last_login_at` の更新、trx の読み取り、スタミナの回復計算（保存しない）、応答の組み立て |
| コミット | 末尾で 1 回（sys・全 trx シャード・log）。スタミナ回復は保存しないので、本機能で login の書き込みは増えない |

## 2. 異常系: MstConfig に値が無い（P5）

「無い」= 行が無い、`value_type` が `int` でない、値が整数に解釈できない、1 未満。

```mermaid
sequenceDiagram
    autonumber
    participant ST as StaminaService
    participant CFG as MstConfigService
    participant R as Redis
    participant MST as mst DB
    participant LOG as ログ

    ST->>CFG: findRecoveryIntervalSeconds()
    CFG->>R: GET mst:mst_config:all
    alt キャッシュなし
        CFG->>MST: SELECT * FROM mst_config
        MST-->>CFG: 行なし（truncate 後にシートに行が無い、未投入など）
    end
    CFG->>CFG: stamina_recovery_interval_seconds の行を探す
    Note over CFG: 行なし / value_type が int でない / 整数でない / 1 未満
    CFG->>LOG: warning（設定 ID・理由。1 リクエストにつき 1 回）
    CFG-->>ST: 既定値（MstConfigKey の既定値 300）
    Note over ST: 通常どおり回復を計算する。login・消費は止まらない
```

- 例外にしない。login のエラーコードは増えない（api.md 7 節）。
- 既定値の置き場所は `MstConfigKey` の 1 か所だけ（db.md 3.3）。
- 欠落中に消費・回復アイテム・レベルアップが走ると、既定値で計算した値が保存される。設定値が既定値（300）のままなら一致する。設定値を変えた後に行が消えたときだけ食い違う（db.md 4 節。警告ログで気づく）。
- Redis が使えないとき（`Cache::store('redis')` が例外）は、login はプレイヤーロックの時点で 503(50300) になる（title-to-home）ので、この節の対象外。ロックを使わない経路（`/player/me` など）は回復を計算しない。

## 3. 異常系: マスター更新の前後で値が違う（U5）

```mermaid
sequenceDiagram
    autonumber
    participant OP as 運営（運営ツール）
    participant T as 運営ツール
    participant MST as mst DB
    participant R as Redis（mst キャッシュ）
    participant C as クライアント
    participant S as サーバー

    Note over C: 配信済みの回復間隔 V1（旧）を持つ
    OP->>T: mst_config を更新（V1 → V2）してエクスポート
    T->>MST: truncate + insert（V2）
    T->>T: SQLite 出力・sys_deploy 登録（新しい hash）
    Note over R: Redis の mst キャッシュは自動では消えない（TTL 最大 3600 秒）
    C->>S: 任意の API（X-Master-Hash = 旧）
    S-->>C: 200 + X-Master-Update-Required: true
    C->>S: GET /auth/version → 差分取得
    Note over C: マスター更新後は V2
    C->>S: POST /auth/login
    alt Redis が V1 のまま（最大 1 時間）
        S->>S: V1 で回復を計算・last_recovery_at を前進
        S-->>C: 回復点数は V1 基準
        Note over C: V2 で計算 → 回復 1 点ぶん未満のタイミングのずれ（二重加算はしない）
    else Redis が V2
        S->>S: V2 で計算
        S-->>C: V2 基準
    end
    Note over C,S: 次の login・消費・回復 API でサーバーの値に置き換わる
```

- サーバーは**計算の時点の自分の `mst_config`（キャッシュ込み）**を使い、クライアントの値は受け取らない。ずれは表示だけで、保存される値の正はサーバー。
- 「常に最新」ではない: 運営ツールの反映後、サーバーの Redis が古い間は旧値で計算する（db.md 5 節。キャッシュを消す運用は未解決点 U-B）。
- 変更の運用は通常しない（overview の仮置き U5）。変えるときは、キャッシュの更新を忘れない（db.md 5 節の手順）。

## 4. サインアップ時の初期データ（Q9・Q15〜Q19 は 2026-11-14 に決定。platform は Q16）

現行の sign_up は sys だけに書く（title-to-home の sequence.md 1 節）。**同じ `executeWithTransaction` の中に trx の書き込みを足す。** 全ゲーム API 共通の `X-Platform` ヘッダーの検証（`ResolvePlatform`）が、`maintenance` の後・署名検証の前に入る（api.md 10 節）。

```mermaid
sequenceDiagram
    autonumber
    participant C as クライアント
    participant S as サーバー（SignUpUseCase）
    participant G as 初期データの付与（新しい Service。名前は S3）
    participant RES as CurrencyGranter（nexus-wallet）/ DiamondService / ItemService / StaminaService（直接呼び）
    participant SYS as sys DB
    participant TRX as trx シャード（割り当て先）
    participant R as Redis

    C->>S: POST /auth/sign_up（X-Platform, 署名ヘッダー, device_id, device_info）
    S->>S: maintenance（メンテ中は 503）
    S->>S: ResolvePlatform（X-Platform が Apple / Google か検証し、ApiSession に載せる）
    alt 欠落・不正値
        S-->>C: 299 / 99003（何も書かない。署名検証・レート制限の前なのでカウントされない。api.md 10.3）
    end
    S->>R: 署名検証（nonce 重複）・レート制限（IP 10/h, device 3/h）
    rect rgb(235, 245, 255)
    Note over S,TRX: executeWithTransaction 開始（sys・全 trx シャード・log を開始）
    S->>SYS: 読む: sys_player_device（device_id）
    alt 既に登録済みの端末
        S-->>C: 299 / 10004（書き込みなしでロールバック。初期データは付与されない）
    end
    S->>SYS: 書く: sys_player（採番）
    S->>SYS: 書く: sys_sharding_node_player、sys_sharding_node の人数 +1（ShardAssignmentService::assign。sys へ即時 INSERT）
    S->>S: ApiSession::setSysPlayerId(新しいプレイヤー ID)（trx の書き込み先の解決に必要。U-D）
    S->>SYS: 書く: sys_player_device、sys_player_token
    S->>G: 初期データを付与（sysPlayerId）
    Note over G: 値はコードの定数（InitialGrantConst: coin 1000、free_diamond 50。MstConfig には持たない。Q15）
    G->>RES: Coin: CurrencyGranterInterface（coin, free 1000）→ trx_wallet（と取得単位 trx_wallet_balance）
    G->>RES: DiamondService::addDiamond（ApiSession::getPlatform(), 0, isPaid = true）→ trx_diamond（platform の 1 行、paid 0。free_amount に依存しない。Q16・D4）
    G->>RES: パッケージ版 ItemService::addItem（free_diamond, free 50）→ trx_item（is_wallet の振り分けを通さない。Q17。mst_item に free_diamond の行が要る）
    G->>RES: MstPlayerLevelRepository::findMaxStaminaForLevel(1)（null なら例外で sign_up を失敗させる）→ StaminaService::initializeStamina → trx_stamina（type = normal, current = max, multiplier 1.00, last_recovery_at = 作成時刻）
    S->>S: flushQueue（QueryManager->flush() でキュー全体を実行。assign は sys へ即時 INSERT 済み。trx の INSERT・付与の失敗の例外はここで出る）
    Note over S,TRX: 末尾で flush → sys → trx シャード → log の順にコミット
    end
    S-->>C: 200 sys_player + sys_player_device + sys_player_token + token（初期データは応答に含まない）
    C->>S: POST /auth/login（X-Platform を付ける）
    S-->>C: 200（trx_*_list に初期データが入る。api.md 4 節）
```

- **platform の出どころ**: ミドルウェアが `ApiSession` に載せた値（api.md 10.4）。sign_up の入力（`device_info.os`）は使わない。
- **platform ヘッダーの検証は `POST /auth/version` にも掛かる**（認証済みの POST は `maintenance` のグループ内。除外は `GET /auth/version` だけ。api.md 10.2）。
- **無償ダイヤ 50 は消費できない暫定**: `trx_item` の `free_diamond` は、`DiamondService::consumeDiamond`（`trx_diamond` だけを見る）から見えない。別機能の完了まで、表示だけ（db.md 8.6 節）。

### トランザクション境界・失敗時

| 事象 | 結果 |
| --- | --- |
| 端末が既に登録済み | 10004。書き込みなしでロールバック。初期データは付与されない（二重付与なし） |
| 付与の途中で例外（Coin・ダイヤ・アイテム・スタミナの直接呼びが投げる。**level 1 の `mst_player_level` が無いときも例外**） | 例外は `executeWithTransaction` まで届き、全接続をロールバック。`sys_player` を含め何も残らない。HTTP ステータスは `_BaseController::determineStatusCode` による: 例外の `getCode()` が 100〜599 ならその値、0 や文字列は 500（読んだ。未確認: 実行）。テストは「200 でないこと」の検査に留める。クライアントは sign_up をやり直せる（device_id の行が残らないため。端末ごとのレート制限 1 時間 3 回に数えられる）。level 1 のマスターが無い（配信前・未投入）ときは、既定値を使わず sign_up を失敗させ、デプロイの不備として検出する |
| （採らない案）付与を `ResourceDeliveryService` で行う | `execDeliveryIteration` が例外を `catch (\Throwable)` でログに出すだけで続行する（読んだ）ので、**付与が失敗しても sign_up は 200 で成功し、初期データの無いプレイヤーが残る**。このため直接呼びを採る |
| flush 中に trx への INSERT が失敗 | `executeWithTransaction` が全接続をロールバック。何も残らない |
| コミットの途中で失敗（`sys` のコミット後に trx のコミットが失敗） | **窓が残る**: プレイヤーと端末は登録済みだが初期データが無い。2PC ではなく、接続を順にコミットする既存の性質。再 sign_up は 10004、sign_in は成功するので、`trx_*_list` が空のプレイヤーになる。許容する（Q18 の login 補填案を採らない場合）。発生頻度は低い想定で、頻度は未確認 |
| 同じ `device_id` の同時 sign_up | 既存の未解決点（title-to-home の api.md 12 節）。初期データの付与は各プレイヤーに 1 回ずつで、これで悪化はしない |

- **sign_up の冪等**: sign_up は冪等ミドルウェアの対象外（未認証）。再送は 2 回目が 10004 になるだけで、初期データは 1 回だけ。クライアントは 10004 を受けたら sign_in に切り替える（title-to-home の方針）。
- **sign_in → sign_up の切り替え**（sign_in が 10002 を返し、クライアントが sign_up する流れ。title-to-home 3 節）: sign_in は何も書かないので初期データに影響しない。sign_up は上の図のとおり。
- **レート制限**: sign_up は IP 10/h・device 3/h（title-to-home）。初期データを足しても変わらない。429 のとき署名の検証後なので、初期データは書かれない。
- **TiDB モード**（`TidbMode::isEnabled()`）では `assign` を呼ばず、trx の接続は `trx1` 固定。付与は同じ呼び出しで `trx1` に入る見込み（未確認）。
- **書き込み先のシャード**: 割り当て（`assign`）が `sys_sharding_node_player` に書いた行を、同じトランザクションの `sys` 接続で読んで trx の接続名を決める。Redis のシャードキャッシュ（`shard:player:{id}`）は未設定のためミスして DB を引く（読んだ。実行はしていない）。
- **ログ**: 付与は `log_*`（PITR 変更ログ・ウォレットの履歴など）を同じトランザクションで書く（既存の付与経路の性質。読んだ）。

## 5. マスターのデプロイ（U1。運用の手順）

```mermaid
sequenceDiagram
    autonumber
    participant OP as 運営
    participant T as 運営ツール（tool）
    participant MST as mst DB
    participant FS as tool/public/masterdata
    participant SYS as sys DB

    OP->>T: マスターインポート（必要なら）: POST /master-import/execute
    T->>MST: truncate + insert（シート名 = テーブル名。mst_config・mst_player_level など）
    OP->>T: エクスポート: POST /master-import/export
    T->>MST: SHOW TABLES LIKE 'mst_%'（除外なし）
    T->>FS: グループごとに SQLite を出力（mst_player_level_{hash}.sqlite, mst_config_{hash}.sqlite ...）
    T->>SYS: sys_deploy_master + sys_deploy_master_table（全グループ）+ ダミーの sys_deploy_asset
    T->>SYS: 既存の sys_deploy を is_active = false にし、新しい sys_deploy を is_active = true・start_at = 現在で追加
    T-->>OP: deploy_key・hash・テーブル一覧
```

- 開発環境は現在、`sys_deploy_master_table` が 0 行・配信ファイルなし（api.md 6.2）。この手順（エクスポート）で配信される。サーバーのコード変更は要らない。
- 同じ内容（ハッシュ）が登録済みなら新規には作らず、既存の deploy_key を返す。`mst_config` を足すとグループが 1 つ増えるので、マニフェストのハッシュが変わり、新しいデプロイになる。
- エクスポートは**サーバーの mst の Redis キャッシュを消さない**（db.md 5 節）。

## 6. 未解決点

| # | 内容 |
| --- | --- |
| U-B | 運営ツールの反映後、サーバーの mst キャッシュ（Redis）をどう更新するか。人の判断（db.md 5 節） |
| U-D | sign_up での `ApiSession::setSysPlayerId` の呼び出しと、trx の書き込み先の解決を、S3 で実行して確かめる（4 節。読んだだけ）。`assign` は sys へ即時 INSERT なので同じ接続で読めるはずだが、`flushQueue()` との順序は実行で確認 |
| 実行未確認 | 4 節の「付与の途中で例外」時の HTTP ステータス、コミットの途中で失敗する頻度。1〜2 節の図は、コードを読んで書いたもので、実行ログではない |
| 確認事項 | なし。Q8・Q15〜Q26・D4・D5・D6・D7 は決定済み（[db.md 8.3・8.4 節](db.md)、[api.md 9・10・11 節](api.md)）。D5 の確認事項は api.md の U-7・U-9。旧版クライアントは未配布で互換不要（D7。Q22 の配布状況の確認は解消済み） |
