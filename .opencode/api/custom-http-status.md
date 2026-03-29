# カスタムHTTPステータスコード体系 / Custom HTTP Status Code System

このドキュメントでは、ゲーム固有の600番台HTTPステータスコードの実装ルールを定義します。

## 目次

- [概要](#概要)
- [600番台ステータスコード定義](#600番台ステータスコード定義)
- [Symfonyの制限とCustomJsonResponse](#symfonyの制限とcustomjsonresponse)
- [実装例](#実装例)

---

## 概要

### なぜ600番台を使用するのか？

**理由:**
1. **標準HTTPステータスコード（100-599）との重複を避ける**
2. **ゲーム固有のエラーを明確に区別**
3. **クライアント側でのエラーハンドリングを簡潔に**

**使い分け:**
- **200-599**: 標準的なHTTPエラー（404 Not Found, 500 Internal Server Errorなど）
- **600-605**: ゲーム固有のビジネスロジックエラー

**例:**
- アイテムが不足している → `600` (GameException)
- メンテナンス中 → `601`
- 強制アップデート必要 → `602`

---

## 600番台ステータスコード定義

### HttpStatusCode.php

```php
namespace App\Exceptions;

/**
 * カスタムHTTPステータスコード定義
 * 
 * 標準的なHTTPステータスコード（100-599）に重複しない独自エラーコードを定義。
 * ゲーム固有のエラーを明確に区別し、クライアント側でのハンドリングを容易にする。
 */
class HttpStatusCode
{
    /**
     * 600 - アプリケーション/ビジネスロジックエラー
     * 
     * 用途:
     * - ゲーム内のビジネスルール違反（GameException）
     * - リソース不足（アイテム不足、通貨不足など）
     * - データ不整合（存在しないデータへのアクセスなど）
     * 
     * 例:
     * - "所持アイテムが不足しています"
     * - "該当するユニットが存在しません"
     * - "レベルアップの条件を満たしていません"
     */
    public const APPLICATION_ERROR = 600;

    /**
     * 601 - メンテナンス中
     * 
     * 用途:
     * - 計画的なメンテナンス作業中
     * - 緊急メンテナンス中
     * 
     * クライアント側の挙動:
     * - メンテナンス画面を表示
     * - 終了予定時刻を表示（APIレスポンスに含める）
     */
    public const MAINTENANCE = 601;

    /**
     * 602 - 強制アップデート必要
     * 
     * 用途:
     * - クライアントバージョンが古すぎる
     * - 重大な不具合修正のため、即座の更新が必要
     * 
     * クライアント側の挙動:
     * - ストアへのリンクを表示
     * - ゲームプレイを不可にする
     */
    public const FORCE_UPDATE = 602;

    /**
     * 603 - アカウント利用停止
     * 
     * 用途:
     * - 利用規約違反によるBANアカウント
     * - 不正行為検知による一時停止
     * 
     * クライアント側の挙動:
     * - 利用停止の理由を表示
     * - サポートへの問い合わせリンクを表示
     */
    public const ACCOUNT_SUSPENDED = 603;

    /**
     * 604 - サーバー過負荷/同時接続制限
     * 
     * 用途:
     * - サーバーの同時接続数が上限に達した
     * - リソース不足により一時的にアクセスを制限
     * 
     * クライアント側の挙動:
     * - "混雑しています。しばらくしてから再度お試しください"を表示
     * - 自動リトライロジック
     */
    public const SERVER_OVERLOAD = 604;

    /**
     * 605 - 不正検知
     * 
     * 用途:
     * - チート行為の疑いがある
     * - 異常なリクエストパターンを検知
     * - セキュリティ上の理由でアクセスを拒否
     * 
     * クライアント側の挙動:
     * - アクセスを即座にブロック
     * - ログを記録してサーバーに送信
     */
    public const FRAUD_DETECTED = 605;
}
```

### ステータスコード一覧表

| コード | 定数名 | 用途 | クライアント挙動 |
|-------|-------|------|-----------------|
| 600 | APPLICATION_ERROR | ビジネスロジックエラー | エラーメッセージ表示 |
| 601 | MAINTENANCE | メンテナンス中 | メンテナンス画面表示 |
| 602 | FORCE_UPDATE | 強制アップデート必要 | ストアリンク表示 |
| 603 | ACCOUNT_SUSPENDED | アカウント利用停止 | BAN画面表示 |
| 604 | SERVER_OVERLOAD | サーバー過負荷 | リトライ促進 |
| 605 | FRAUD_DETECTED | 不正検知 | アクセスブロック |

---

## Symfonyの制限とCustomJsonResponse

### 問題: SymfonyのResponseクラスは600番台を拒否する

Symfonyの`Response`クラスは、HTTPステータスコードの範囲を**100-599**に制限しています。

**エラー例:**
```php
// ❌ これは失敗する
return response()->json(['error' => 'Application Error'], 600);

// Symfony\Component\HttpFoundation\Exception\InvalidArgumentException:
// The HTTP status code "600" is not valid.
```

### 解決策: CustomJsonResponseでReflectionを使用

`CustomJsonResponse`クラスを作成し、Reflectionを使ってprotectedプロパティに直接アクセスすることでSymfonyの検証を回避します。

#### CustomJsonResponse.php

```php
namespace App\Http;

use Illuminate\Http\JsonResponse;

/**
 * 600番台のHTTPステータスコードをサポートするカスタムJsonResponse
 * 
 * Symfonyの標準Responseクラスは100-599の範囲しか受け付けないため、
 * Reflectionを使用してprotectedプロパティに直接アクセスし、
 * 600番台のステータスコードを設定する。
 */
class CustomJsonResponse extends JsonResponse
{
    /**
     * 600番台のカスタムステータステキスト
     */
    private const CUSTOM_STATUS_TEXTS = [
        600 => 'Application Error',
        601 => 'Maintenance',
        602 => 'Force Update Required',
        603 => 'Account Suspended',
        604 => 'Server Overload',
        605 => 'Fraud Detected',
    ];

    /**
     * 600番台のステータスコードをサポートするJsonResponseを作成
     *
     * @param mixed $data レスポンスデータ
     * @param int $status HTTPステータスコード（600-605をサポート）
     * @param array $headers 追加ヘッダー
     * @param int $options JSONエンコードオプション
     * @return self
     */
    public static function create($data = null, int $status = 200, array $headers = [], int $options = 0): self
    {
        $response = new self($data, 200, $headers, $options);

        if ($status >= 600 && $status <= 605) {
            // Reflectionを使用してprotectedプロパティに直接アクセス
            try {
                $reflection = new \ReflectionClass($response);
                
                // statusCodeプロパティを設定
                $statusCodeProperty = $reflection->getProperty('statusCode');
                $statusCodeProperty->setAccessible(true);
                $statusCodeProperty->setValue($response, $status);
                
                // statusTextプロパティを設定
                $statusTextProperty = $reflection->getProperty('statusText');
                $statusTextProperty->setAccessible(true);
                $statusTextProperty->setValue($response, self::CUSTOM_STATUS_TEXTS[$status] ?? 'Unknown Status');
            } catch (\ReflectionException $e) {
                // Reflectionが失敗した場合は通常の200を返す
                return $response;
            }
        } else {
            // 標準的なステータスコードはsetStatusCode()を使用
            $response->setStatusCode($status);
        }

        return $response;
    }
}
```

**重要なポイント:**
1. **Reflectionでprotectedプロパティにアクセス** - Symfonyの検証を回避
2. **カスタムステータステキストを定義** - 600番台専用のテキスト
3. **100-599は通常通り処理** - 標準ステータスコードとの互換性を保つ
4. **Reflection失敗時は200を返す** - エラー時のフォールバック

---

## 実装例

### _BaseControllerでの使用

```php
namespace App\Http\Controllers;

use App\Exceptions\GameException;
use App\Exceptions\HttpStatusCode;
use App\Http\CustomJsonResponse;
use Illuminate\Http\JsonResponse;

abstract class _BaseController extends Controller
{
    /**
     * エラーハンドリング
     */
    protected function handleException(\Exception $e): JsonResponse
    {
        if ($e instanceof GameException) {
            // ✅ CustomJsonResponseを使用して600を返す
            return CustomJsonResponse::create([
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], HttpStatusCode::APPLICATION_ERROR);
        }

        // その他のエラーは500を返す
        return response()->json([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
```

### GameExceptionクラス

```php
namespace App\Exceptions;

use Exception;

/**
 * ゲーム内のビジネスロジックエラー
 * 
 * HTTPステータスコード: 600
 */
class GameException extends Exception
{
    public function __construct(
        private readonly int $errorCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
```

### GameErrorCodeクラス

```php
namespace App\Exceptions;

/**
 * ゲーム内エラーコード定義
 * 
 * HTTPステータスコード: 600（APPLICATION_ERROR）
 * この中のエラーコードは、クライアント側で個別にハンドリングされる
 */
class GameErrorCode
{
    // パラメータエラー
    public const INVALID_PARAMETER = 1001;
    public const MISSING_PARAMETER = 1002;
    
    // リソース不足
    public const ITEM_NOT_ENOUGH = 2001;
    public const CURRENCY_NOT_ENOUGH = 2002;
    public const STAMINA_NOT_ENOUGH = 2003;
    
    // データ不整合
    public const UNIT_NOT_FOUND = 3001;
    public const ITEM_NOT_FOUND = 3002;
    public const MASTER_DATA_NOT_FOUND = 3003;
    
    // ビジネスルール違反
    public const LEVEL_REQUIREMENT_NOT_MET = 4001;
    public const QUEST_NOT_CLEARED = 4002;
    public const PURCHASE_LIMIT_EXCEEDED = 4003;
}
```

### 実際のAPI実装例

```php
namespace App\Domain\Unit\Services;

use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;

class UnitLevelUpService
{
    public function levelUp(int $unitId, string $itemId, int $useCount): array
    {
        $unit = TrxUnit::find($unitId);
        if (!$unit) {
            // ✅ GameExceptionをthrow → 600エラーになる
            throw new GameException(
                GameErrorCode::UNIT_NOT_FOUND,
                "Unit not found: {$unitId}"
            );
        }

        $currentAmount = $this->itemRepository->getAmount($itemId);
        if ($currentAmount < $useCount) {
            // ✅ GameExceptionをthrow → 600エラーになる
            throw new GameException(
                GameErrorCode::ITEM_NOT_ENOUGH,
                "Item not enough: required={$useCount}, current={$currentAmount}"
            );
        }

        // ビジネスロジック...
        return ['level' => $unit->level];
    }
}
```

### クライアント側での処理例（疑似コード）

```typescript
// TypeScript/UnityのHTTPクライアント例
async function callApi(endpoint: string, data: any): Promise<any> {
    const response = await fetch(endpoint, {
        method: 'POST',
        body: JSON.stringify(data),
    });

    switch (response.status) {
        case 200:
            return await response.json();
        
        case 600:  // Application Error
            const error = await response.json();
            showErrorDialog(error.message);
            break;
        
        case 601:  // Maintenance
            showMaintenanceScreen();
            break;
        
        case 602:  // Force Update
            showForceUpdateDialog();
            break;
        
        case 603:  // Account Suspended
            showBanScreen();
            break;
        
        case 604:  // Server Overload
            showRetryDialog();
            break;
        
        case 605:  // Fraud Detected
            blockAccess();
            break;
        
        default:
            showGenericError();
    }
}
```

---

## まとめ

### 600番台ステータスコード実装ルール

1. **HttpStatusCodeクラスで定数定義** - 600-605の用途を明確に文書化
2. **CustomJsonResponseを使用** - Symfonyの制限を回避
3. **Reflectionで直接設定** - protectedプロパティにアクセス
4. **GameExceptionは600を返す** - ビジネスロジックエラー
5. **クライアント側で個別処理** - ステータスコードごとに異なる挙動

### チェックリスト

**HttpStatusCode.php:**
- [ ] 600-605の定数を定義
- [ ] 各コードの用途をコメントで明記

**CustomJsonResponse.php:**
- [ ] Reflectionで`statusCode`と`statusText`を設定
- [ ] 600-605のカスタムステータステキストを定義
- [ ] 標準ステータスコード（100-599）との互換性を保つ

**_BaseController.php:**
- [ ] GameExceptionの場合にCustomJsonResponseを使用
- [ ] HttpStatusCode::APPLICATION_ERROR（600）を返す

**GameException/GameErrorCode:**
- [ ] コメントにHTTPステータスコード600を明記
- [ ] エラーコードを適切に分類

---

## 関連ドキュメント

- [トランザクション管理](../database/transaction-management.md) - sysデータベースのトランザクション管理
- [API設計](../api.md) - APIエンドポイントの設計
- [例外処理](../coding-standards.md) - エラーハンドリングの実装ルール
