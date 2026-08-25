# nexus-core

Nexusフレームワークのコアパッケージ。
Eloquent Model基底クラス、Repository基底クラス、ユーティリティ、ValueObjectを提供します。

## 提供機能

### Models
- `_BaseModel` - 全Eloquentモデルの基底クラス（日付型最適化、レスポンス変換）
- `_BaseTrx` - トランザクションDB用モデル基底クラス
- `_BaseSys` - システムDB用モデル基底クラス
- `_BaseMst` - マスタDB用モデル基底クラス
- `_BaseLog` - ログDB用モデル基底クラス

### Repositories
- `_BaseRepository` - Repository基底クラス
- `_BaseTrxRepository` - トランザクションDB用Repository基底クラス
- `_BaseSysRepository` - システムDB用Repository基底クラス
- `_BaseMstRepository` - マスタDB用Repository基底クラス
- `_BaseLogRepository` - ログDB用Repository基底クラス

### Support
- `CustomCollection` - パフォーマンス最適化されたカスタムCollection

### Utilities
- `ClockUtility` - 時刻操作ユーティリティ（テスト時の時刻固定、ゲーム内日付対応）

### ValueObjects
- `ErrorResponse` - エラーレスポンスValueObject（HTTP 299対応、3階層エラーコード）

### Traits
- `JsonSerializableTrait` - JSON変換用Trait

## インストール

```bash
composer require nexus/core
```

## 使用例

### ErrorResponse（ValueObject）

```php
use Nexus\Core\ValueObjects\ErrorResponse;
use App\Exceptions\InfraErrorCode;

// ビジネスエラー（HTTP 299）
return ErrorResponse::businessError(1001, 'コインが不足しています')
    ->toJsonResponse();

// システムエラー（HTTP 500）
return ErrorResponse::systemError(InfraErrorCode::UNKNOWN_ERROR, 'システムエラー')
    ->toJsonResponse();

// カスタムHTTPステータス
return ErrorResponse::businessError(1001, 'エラー')
    ->withStatus(400)
    ->toJsonResponse();
```

### ClockUtility

```php
use Nexus\Core\Utilities\ClockUtility;

// 現在時刻取得
$now = ClockUtility::now(); // CarbonImmutable

// 比較（開始判定）
if (!ClockUtility::greaterThanOrEqual($event->start_at)) {
    throw new GameException('イベントはまだ開始していません');
}

// 比較（終了判定）
if (!ClockUtility::lessThanOrEqual($event->end_at)) {
    throw new GameException('イベントは終了しました');
}

// 時間差分
$elapsedMinutes = ClockUtility::diffInMinutes($stamina->last_recovery_at);
```

### CustomCollection

```php
use Nexus\Core\Support\CustomCollection;

// CRITICAL: Illuminate\Support\Collectionは使用禁止
// ✅ Good
$collection = new CustomCollection($items);

// ❌ Bad
$collection = collect($items); // Illuminate\Support\Collection
```

### _BaseModel

```php
use Nexus\Core\Models\_BaseModel;

class TrxPlayer extends _BaseModel
{
    // 日付操作が必要な場合のみCarbonに変換
    public function getLastLoginAt(): ?CarbonImmutable
    {
        return $this->getDateAttribute('last_login_at');
    }

    // レスポンス配列に変換（created_at/updated_atは自動的にISO8601形式）
    public function toResponseArray(): array
    {
        return [
            'player_id' => $this->player_id,
            'name' => $this->name,
            // created_at/updated_atは自動的にISO8601形式で含まれる
        ];
    }
}
```

## パッケージ構成

```
nexus-core/
├── src/
│   ├── Models/          # Eloquent Model基底クラス
│   ├── Repositories/    # Repository基底クラス
│   ├── Support/         # CustomCollection等
│   ├── Utilities/       # ClockUtility
│   ├── ValueObjects/    # ErrorResponse等のValueObject
│   ├── Traits/          # 共通Trait
│   └── CoreServiceProvider.php
├── tests/
└── composer.json
```

## 名前空間

```
Nexus\Core\Models\
Nexus\Core\Repositories\
Nexus\Core\Support\
Nexus\Core\Utilities\
Nexus\Core\ValueObjects\
Nexus\Core\Traits\
```

## 旧パッケージからの移行

このパッケージは以下の2つのパッケージを統合したものです：

- `nexus/core-persistence` → `Nexus\Core\Models`, `Nexus\Core\Repositories`, `Nexus\Core\Support`
- `nexus/core-utilities` → `Nexus\Core\Utilities`, `Nexus\Core\ValueObjects`, `Nexus\Core\Traits`

### 名前空間変更

```php
// 旧
use NexusPersistence\Models\_BaseModel;
use NexusPersistence\Support\CustomCollection;
use NexusUtilities\Utilities\ClockUtility;
use NexusUtilities\Responses\ErrorResponse;

// 新
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use Nexus\Core\ValueObjects\ErrorResponse;
```

## License

MIT
