# API呼び出しフロー

## 推奨されるAPI呼び出し順序

モバイルゲームアプリケーションでは、以下の順序でAPIを呼び出すことを推奨します。

### 1. version API（バージョンチェック） ← **最初に呼ぶ**
### 2. sign_up または sign_in（認証）
### 3. その他の認証が必要なAPI

---

## 共通HTTPヘッダー

すべてのAPIリクエストで使用可能な共通ヘッダー：

### X-Unique-Request-Identifier（推奨）

リクエストの冪等性を保証するためのユニークな識別子。

```
X-Unique-Request-Identifier: <UUID v4 string>
```

**用途:**
- 同じリクエストが重複送信された場合、サーバーはキャッシュされたレスポンスを返す
- ネットワークエラー時のリトライを安全に実行できる
- 課金処理など、重複実行されると問題のある操作を保護

**仕様:**
- 形式: UUID v4（例: `550e8400-e29b-41d4-a716-446655440000`）
- キャッシュ期間: 24時間（設定変更可能）
- キャッシュキー: `{sys_player_id}:{unique_request_id}:{api_path}`
- レスポンスヘッダー: `X-Idempotency-Cache: HIT` または `MISS`

**注意事項:**
- GETリクエストでは無視される（GETは元々冪等）
- 認証が必要なエンドポイントでのみ有効
- sign_up/sign_inでは無効（プレイヤーID取得前のため）

**クライアント実装例:**
```javascript
// リクエストごとに新しいUUIDを生成
const uniqueRequestId = uuidv4();

// リトライ時は同じIDを使用
fetch('/api/unit/level_up', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer <token>',
    'X-Unique-Request-Identifier': uniqueRequestId,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({...})
});
```

---

## なぜこの順序が推奨されるのか？

### version APIを最初に呼ぶ理由

1. **メンテナンス状態の確認**
   - サーバーがメンテナンス中かどうかを最初に確認
   - メンテナンス中の場合、ユーザーに適切なメッセージを表示

2. **クライアントバージョンの検証**
   - クライアントのバージョンが古すぎる場合、アプリストアへのアップデート誘導が可能
   - 必須アップデートの場合、以降のAPIを呼ばせない

3. **認証前の確認**
   - 認証処理の前に、ゲームがプレイ可能な状態かを確認
   - リソースの節約（無駄な認証処理を避ける）

4. **マスターデータ・アセットの更新確認**
   - 必要に応じてマスターデータやアセットをダウンロード

---

## API仕様

### 1. GET /auth/version

#### 概要
アプリケーションのバージョンチェックとメンテナンス情報を取得します。
**認証不要**で呼び出せるため、最初に呼び出すべきAPIです。

#### リクエストヘッダー
```
Deploy-Version: <integer|null>  # クライアントが保持しているデプロイID（初回起動時はnull）
Client-Version: <string>         # クライアントのバージョン（例: "1.0.0"）
```

#### レスポンス例

##### ケース1: 更新不要（メンテナンスなし）
```json
{
  "needs_update": false
}
```

##### ケース2: 更新不要（メンテナンスあり）
```json
{
  "needs_update": false,
  "maintenance": {
    "title": "定期メンテナンス",
    "message": "現在メンテナンス中です。しばらくお待ちください。",
    "start_at": "2026-02-22T15:00:00+09:00",
    "end_at": "2026-02-22T17:00:00+09:00"
  }
}
```

##### ケース3: 更新必要（マスター・アセット）
```json
{
  "needs_update": true,
  "latest_deploy_id": 123,
  "latest_deploy_key": 202602221,
  "master": {
    "deploy_master_id": 45,
    "hash": "abc123def456"
  },
  "asset": {
    "deploy_asset_id": 67,
    "hash": "xyz789uvw012"
  },
  "maintenance": null
}
```

#### クライアント側の処理フロー
```
1. version APIを呼び出す
   ↓
2. maintenance が null でない場合
   → メンテナンス画面を表示して処理終了
   ↓
3. needs_update が true の場合
   → マスターデータ・アセットをダウンロード
   → ダウンロード完了後、latest_deploy_id を保存
   ↓
4. 認証APIへ進む
```

---

### 2. POST /auth/sign_up

#### 概要
新規プレイヤーを作成し、アクセストークンとリフレッシュトークンを発行します。
既に登録済みのデバイスIDの場合は、新しいトークンを再発行します。

#### リクエストボディ
```json
{
  "device_id": "unique-device-identifier",
  "device_info": {
    "os": "iOS",
    "os_version": "17.2",
    "model": "iPhone 15 Pro"
  }
}
```

#### レスポンス例
```json
{
  "my_id": "player123456",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200a1b2c3d4e5f6...",
  "expires_in": 3600
}
```

#### 使用タイミング
- アプリ初回起動時
- デバイスにリフレッシュトークンが保存されていない場合

---

### 3. POST /auth/sign_in

#### 概要
リフレッシュトークンからアクセストークンを再発行します（トークンリフレッシュ）。
古いリフレッシュトークンは無効化され、新しいリフレッシュトークンが発行されます。

#### リクエストボディ
```json
{
  "refresh_token": "def50200a1b2c3d4e5f6..."
}
```

#### レスポンス例
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200x9y8z7w6v5u4...",
  "expires_in": 3600
}
```

#### 使用タイミング
- アプリ2回目以降の起動時
- デバイスにリフレッシュトークンが保存されている場合
- アクセストークンの有効期限が切れた場合

---

## 完全なフロー図

```
┌─────────────┐
│ アプリ起動  │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ GET /auth/version   │ ← 最初に必ず呼ぶ
└──────┬──────────────┘
       │
       ▼
   メンテナンス中？
       │
       ├─ YES → メンテナンス画面表示 → 終了
       │
       ├─ バージョンNG？ → アップデート誘導 → 終了
       │
       └─ OK
           │
           ▼
       needs_update?
           │
           ├─ YES → マスターデータ・アセットダウンロード
           │         deploy_id を保存
           └─ NO
               │
               ▼
       リフレッシュトークン保存済み？
           │
           ├─ YES → POST /auth/sign_in
           │         │
           │         ├─ 成功 → トークン保存
           │         │         │
           │         └─ 失敗 → POST /auth/sign_up
           │                   （再登録）
           │
           └─ NO → POST /auth/sign_up
                   │
                   └─ トークン保存
                       │
                       ▼
                 ┌─────────────┐
                 │ ゲーム開始  │
                 └─────────────┘
```

---

## セキュリティ上の注意点

1. **リフレッシュトークンの保管**
   - デバイスのセキュアストレージに保存する
   - 絶対にログに出力しない

2. **アクセストークンの使用**
   - 認証が必要なAPIは、HTTPヘッダー `Authorization: Bearer <token>` にアクセストークンを付与
   - アクセストークンの有効期限は1時間（expires_in: 3600秒）

3. **トークンのローテーション**
   - sign_in API を呼ぶたびに、古いリフレッシュトークンは無効化される
   - 新しいリフレッシュトークンを必ず保存し直すこと

---

## エラーハンドリング

### version API
- メンテナンス情報がある場合、`maintenance` オブジェクトが返される
- クライアント側で適切なUI表示を行う

### sign_up API
- 既存デバイスIDの場合でも、新しいトークンを発行して成功する

### sign_in API
- リフレッシュトークンが無効な場合、HTTP 401 エラー
  → sign_up APIで再登録する

---

## まとめ

**推奨フロー:**
1. `GET /auth/version` でメンテナンス・バージョンチェック
2. `POST /auth/sign_up` または `POST /auth/sign_in` で認証
3. その他の認証が必要なAPIを呼び出す

この順序を守ることで、ユーザーエクスペリエンスを向上させ、無駄なAPI呼び出しを削減できます。
