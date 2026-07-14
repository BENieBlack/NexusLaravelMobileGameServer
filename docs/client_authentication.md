# Client Authentication Guide for Sign-Up Endpoint

## 概要

`/auth/sign_up` エンドポイントは、不正利用を防ぐために以下のセキュリティ機能を実装しています：

1. **HMAC署名検証** - クライアントが正規のアプリであることを確認
2. **リプレイアタック対策** - タイムスタンプとノンスで重複リクエストを防止
3. **レート制限** - IPアドレスとデバイスIDごとに試行回数を制限

---

## 必要なHTTPヘッダー

以下のヘッダーをリクエストに含める必要があります：

| ヘッダー名 | 説明 | 例 |
|-----------|------|-----|
| `X-Client-Timestamp` | Unixタイムスタンプ（秒） | `1714800000` |
| `X-Client-Nonce` | ランダムな一意文字列（32文字以上推奨） | `a1b2c3d4e5f6...` |
| `X-Client-Signature` | HMAC-SHA256署名（16進数） | `3a4b5c6d7e8f...` |

---

## 署名の生成方法

### 1. 秘密鍵の取得

アプリケーションに埋め込まれた `CLIENT_SECRET` を使用します。

```
CLIENT_SECRET = "your-secret-key-here"
```

**⚠️ セキュリティ注意事項:**
- 秘密鍵はソースコードに直接書かず、難読化・暗号化すること
- リリースビルドとデバッグビルドで異なる鍵を使用すること
- 定期的に鍵をローテーションすること

### 2. タイムスタンプの生成

現在時刻のUnixタイムスタンプ（秒単位）を取得します。

```javascript
const timestamp = Math.floor(Date.now() / 1000);
```

### 3. ノンスの生成

ランダムな一意文字列を生成します（32文字以上推奨）。

```javascript
function generateNonce(length = 32) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let nonce = '';
  for (let i = 0; i < length; i++) {
    nonce += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return nonce;
}

const nonce = generateNonce();
```

### 4. HMAC署名の計算

メッセージ形式: `{timestamp}:{nonce}:{request_body}`

```javascript
const crypto = require('crypto');

// リクエストボディをJSON文字列化
const requestBody = JSON.stringify({
  device_id: "your-device-id",
  device_info: {
    os: "iOS",
    os_version: "17.0",
    model: "iPhone 15",
    app_version: "1.0.0"
  }
});

// 署名メッセージを構築
const message = `${timestamp}:${nonce}:${requestBody}`;

// HMAC-SHA256署名を生成
const signature = crypto
  .createHmac('sha256', CLIENT_SECRET)
  .update(message)
  .digest('hex');
```

### 5. リクエスト送信

```javascript
const axios = require('axios');

const response = await axios.post('https://api.example.com/auth/sign_up', 
  {
    device_id: "your-device-id",
    device_info: {
      os: "iOS",
      os_version: "17.0",
      model: "iPhone 15",
      app_version: "1.0.0"
    }
  },
  {
    headers: {
      'Content-Type': 'application/json',
      'X-Client-Timestamp': timestamp.toString(),
      'X-Client-Nonce': nonce,
      'X-Client-Signature': signature
    }
  }
);
```

---

## Unity (C#) サンプルコード

```csharp
using System;
using System.Security.Cryptography;
using System.Text;
using UnityEngine;
using UnityEngine.Networking;

public class SignUpClient : MonoBehaviour
{
    private const string CLIENT_SECRET = "your-secret-key-here";
    private const string API_URL = "https://api.example.com/auth/sign_up";

    public async void SignUp(string deviceId)
    {
        // 1. タイムスタンプ生成
        long timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds();

        // 2. ノンス生成
        string nonce = GenerateNonce(32);

        // 3. リクエストボディ作成
        var requestData = new SignUpRequest
        {
            device_id = deviceId,
            device_info = new DeviceInfo
            {
                os = SystemInfo.operatingSystem,
                os_version = SystemInfo.operatingSystemFamily.ToString(),
                model = SystemInfo.deviceModel,
                app_version = Application.version
            }
        };
        string requestBody = JsonUtility.ToJson(requestData);

        // 4. HMAC署名生成
        string signature = GenerateHmacSignature(timestamp, nonce, requestBody);

        // 5. リクエスト送信
        using (UnityWebRequest request = new UnityWebRequest(API_URL, "POST"))
        {
            byte[] bodyRaw = Encoding.UTF8.GetBytes(requestBody);
            request.uploadHandler = new UploadHandlerRaw(bodyRaw);
            request.downloadHandler = new DownloadHandlerBuffer();
            
            request.SetRequestHeader("Content-Type", "application/json");
            request.SetRequestHeader("X-Client-Timestamp", timestamp.ToString());
            request.SetRequestHeader("X-Client-Nonce", nonce);
            request.SetRequestHeader("X-Client-Signature", signature);

            await request.SendWebRequest();

            if (request.result == UnityWebRequest.Result.Success)
            {
                Debug.Log("Sign up successful: " + request.downloadHandler.text);
            }
            else
            {
                Debug.LogError("Sign up failed: " + request.error);
            }
        }
    }

    private string GenerateNonce(int length)
    {
        const string chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        StringBuilder result = new StringBuilder(length);
        System.Random random = new System.Random();
        
        for (int i = 0; i < length; i++)
        {
            result.Append(chars[random.Next(chars.Length)]);
        }
        
        return result.ToString();
    }

    private string GenerateHmacSignature(long timestamp, string nonce, string body)
    {
        string message = $"{timestamp}:{nonce}:{body}";
        
        using (var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(CLIENT_SECRET)))
        {
            byte[] hashBytes = hmac.ComputeHash(Encoding.UTF8.GetBytes(message));
            return BitConverter.ToString(hashBytes).Replace("-", "").ToLower();
        }
    }

    [Serializable]
    public class SignUpRequest
    {
        public string device_id;
        public DeviceInfo device_info;
    }

    [Serializable]
    public class DeviceInfo
    {
        public string os;
        public string os_version;
        public string model;
        public string app_version;
    }
}
```

---

## エラーレスポンス

### 1. ヘッダー不足

```json
{
  "error": "INVALID_CLIENT_REQUEST",
  "message": "Missing required client authentication headers"
}
```
HTTP Status: `401 Unauthorized`

### 2. タイムスタンプ期限切れ

```json
{
  "error": "REQUEST_EXPIRED",
  "message": "Request timestamp is too old or too far in the future"
}
```
HTTP Status: `401 Unauthorized`

**原因**: リクエストが5分以上前、または未来の時刻

### 3. ノンス重複

```json
{
  "error": "DUPLICATE_REQUEST",
  "message": "Request nonce has already been used"
}
```
HTTP Status: `401 Unauthorized`

**原因**: 同じノンスで複数回リクエストを送信（リプレイアタック）

### 4. 署名検証失敗

```json
{
  "error": "INVALID_SIGNATURE",
  "message": "Client signature verification failed"
}
```
HTTP Status: `401 Unauthorized`

**原因**: 
- 秘密鍵が間違っている
- 署名の計算方法が間違っている
- リクエストボディが改ざんされている

### 5. レート制限超過

```json
{
  "error": "TOO_MANY_REQUESTS",
  "message": "Too many sign up attempts from this IP address. Please try again later.",
  "retry_after": 3600
}
```
HTTP Status: `429 Too Many Requests`

**制限値:**
- IPアドレスごと: 1時間に10回まで
- デバイスIDごと: 1時間に3回まで

---

## トラブルシューティング

### タイムスタンプのズレ

クライアント端末の時刻がずれている場合、リクエストが拒否されます。

**対策:**
1. NTPサーバーと同期
2. サーバーから現在時刻を取得するエンドポイントを用意
3. タイムスタンプのズレを許容する時間を調整（サーバー側で `TIMESTAMP_TOLERANCE` を変更）

### 署名の不一致

署名計算で最も多いミス：

1. **文字エンコーディング**: UTF-8を使用すること
2. **JSON文字列化**: スペースや改行の有無に注意
3. **ハッシュ形式**: 16進数（hex）で出力すること
4. **大文字小文字**: 署名は小文字で送信

### デバッグ方法

サーバー側で期待される署名を確認：

```bash
# コマンドラインで署名を計算
echo -n "1714800000:abc123:{'device_id':'test'}" | \
  openssl dgst -sha256 -hmac "your-secret-key-here"
```

---

## セキュリティベストプラクティス

1. **秘密鍵の保護**
   - ソースコードに平文で書かない
   - 難読化ツールを使用
   - ネイティブコード（C/C++）に埋め込む
   - 鍵をサーバーから動的に取得しない（Man-in-the-Middle攻撃のリスク）

2. **ノンスの品質**
   - 暗号学的に安全な乱数生成器を使用
   - 十分な長さ（32文字以上）
   - 予測不可能であること

3. **タイムスタンプの同期**
   - デバイスの時刻が正確であることを確認
   - NTPサーバーとの同期を推奨

4. **エラーハンドリング**
   - 署名エラーを詳細にログに記録しない（攻撃者に情報を与えない）
   - ユーザーには一般的なエラーメッセージを表示

5. **鍵のローテーション**
   - 定期的に秘密鍵を変更
   - 新旧両方の鍵を一定期間サポート
   - アプリのバージョンごとに異なる鍵を使用

---

## テスト用curlコマンド

```bash
#!/bin/bash

CLIENT_SECRET="your-secret-key-here"
TIMESTAMP=$(date +%s)
NONCE=$(openssl rand -hex 16)
REQUEST_BODY='{"device_id":"test-device-123","device_info":{"os":"iOS","os_version":"17.0","model":"iPhone 15","app_version":"1.0.0"}}'

MESSAGE="${TIMESTAMP}:${NONCE}:${REQUEST_BODY}"
SIGNATURE=$(echo -n "$MESSAGE" | openssl dgst -sha256 -hmac "$CLIENT_SECRET" | cut -d' ' -f2)

curl -X POST https://api.example.com/auth/sign_up \
  -H "Content-Type: application/json" \
  -H "X-Client-Timestamp: $TIMESTAMP" \
  -H "X-Client-Nonce: $NONCE" \
  -H "X-Client-Signature: $SIGNATURE" \
  -d "$REQUEST_BODY"
```

---

## よくある質問

**Q: sign_in エンドポイントにも同じ認証が必要ですか？**

A: 現在は sign_up のみですが、必要に応じて sign_in にも適用できます。

**Q: タイムスタンプの有効期限（5分）を変更できますか？**

A: はい。サーバー側の `VerifyClientSignature::TIMESTAMP_TOLERANCE` を変更してください。

**Q: レート制限の回数を変更できますか？**

A: はい。`ThrottleSignUp::MAX_ATTEMPTS_PER_IP` と `MAX_ATTEMPTS_PER_DEVICE` を変更してください。

**Q: 署名の計算にリクエストボディ全体が必要ですか？**

A: はい。リクエストボディの改ざんを検出するため、全体を署名に含めます。
