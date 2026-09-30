# Laravel Wallet

仮想通貨ウォレット管理パッケージ。Gold、EventCoin、PvPPoint等の仮想通貨の増減・残高管理をFIFO方式でサポート。

## 機能

- ✅ **仮想通貨管理** - 無償/有償通貨を個別管理
- ✅ **FIFO消費** - 有効期限を考慮した先入先出法
- ✅ **有償優先消費** - 有償通貨を優先的に消費
- ✅ **有効期限管理** - 通貨ごとに有効期限を設定
- ✅ **統一インターフェース** - 全ての通貨を同じAPIで操作
- ✅ **型安全** - DTOによる厳密な型定義

## 要件

- PHP 8.1以上
- Laravel 10.x / 11.x / 12.x

## インストール

```bash
composer require laravel-mobile-rpg/wallet
```

## 基本的な使い方

### 1. インターフェースの実装

パッケージの`WalletManagerInterface`を実装したサービスクラスを作成します。

```php
use NexusWallet\Contracts\WalletManagerInterface;
use NexusWallet\DataTransferObjects\CurrencyBalance;
use NexusWallet\DataTransferObjects\CurrencyOperationResult;
use Carbon\CarbonImmutable;

class WalletService implements WalletManagerInterface
{
    public function addCurrency(
        int $playerId,
        string $currencyId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?CarbonImmutable $expireAt = null
    ): CurrencyOperationResult {
        // 実装
    }

    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResult {
        // 実装
    }

    public function findBalance(int $playerId, string $currencyId): CurrencyBalance {
        // 実装
    }

    public function removeExpiredCurrency(int $playerId, string $currencyId): int {
        // 実装
    }

    public function getBulkBalances(int $playerId, array $currencyIds): array {
        // 実装
    }
}
```

### 2. サービスプロバイダーでバインド

```php
use NexusWallet\Contracts\WalletManagerInterface;
use App\Domain\Wallet\Services\WalletService;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(WalletManagerInterface::class, WalletService::class);
    }
}
```

### 3. 通貨の追加

```php
use NexusWallet\Contracts\WalletManagerInterface;
use Carbon\CarbonImmutable;

$walletManager = app(WalletManagerInterface::class);

// 無償Goldを1000追加（有効期限30日）
$result = $walletManager->addCurrency(
    playerId: $playerId,
    currencyId: 'gold',
    freeAmount: 1000,
    expireAt: CarbonImmutable::now()->addDays(30)
);

echo "追加: {$result->totalAmount}";
echo "現在残高: {$result->currentBalance}";
```

### 4. 通貨の消費

```php
// Goldを500消費（FIFO、有償優先）
$result = $walletManager->consumeCurrency(
    playerId: $playerId,
    currencyId: 'gold',
    amount: 500
);

echo "消費（有償）: {$result->paidAmount}";
echo "消費（無償）: {$result->freeAmount}";
echo "残高: {$result->currentBalance}";
```

### 5. 残高確認

```php
$balance = $walletManager->findBalance($playerId, 'gold');

echo "無償: {$balance->freeAmount}";
echo "有償: {$balance->paidAmount}";
echo "合計: {$balance->totalAmount}";
```

### 6. 複数通貨の一括取得

```php
$balances = $walletManager->getBulkBalances(
    $playerId,
    ['gold', 'event_coin', 'pvp_point']
);

foreach ($balances as $currencyId => $balance) {
    echo "{$currencyId}: {$balance->totalAmount}\n";
}
```

### 7. 有効期限切れ通貨の削除

```php
$expired = $walletManager->removeExpiredCurrency($playerId, 'event_coin');
echo "削除された数量: {$expired}";
```

## 例外処理

```php
use NexusWallet\Exceptions\InsufficientBalanceException;
use NexusWallet\Exceptions\InvalidCurrencyException;

try {
    $result = $walletManager->consumeCurrency($playerId, 'gold', 10000);
} catch (InsufficientBalanceException $e) {
    // 残高不足
    Log::warning('残高不足: ' . $e->getMessage());
} catch (InvalidCurrencyException $e) {
    // 無効な通貨ID
    Log::error('無効な通貨: ' . $e->getMessage());
}
```

## DTOリファレンス

### CurrencyBalance

残高情報を保持するDTO

```php
readonly class CurrencyBalance {
    public int $freeAmount;                // 無償通貨数
    public int $paidAmount;                // 有償通貨数
    public int $totalAmount;               // 合計通貨数
    public ?CarbonImmutable $expireAt;     // 有効期限（最短のもの）
}
```

### CurrencyOperationResult

操作結果を保持するDTO

```php
readonly class CurrencyOperationResult {
    public int $freeAmount;      // 操作した無償通貨数
    public int $paidAmount;      // 操作した有償通貨数
    public int $totalAmount;     // 操作した合計通貨数
    public int $currentBalance;  // 操作後の残高
}
```

## 設定

`config/wallet.php`で以下の設定が可能です。

```php
return [
    // デフォルトの通貨有効期限（日数）
    'default_expiration_days' => null,  // null = 無期限
    
    // FIFO消費時の有償通貨優先設定
    'paid_currency_priority' => true,
    
    // 有効期限切れ通貨の自動削除
    'auto_remove_expired' => true,
];
```

## アーキテクチャ

このパッケージは**インターフェースベース**の設計を採用しています：

- パッケージは抽象的な`WalletManagerInterface`を提供
- 具体的な実装（Model/Repository）はアプリケーション側で定義
- データベース構造に依存しない柔軟な設計

### 実装例（FIFO消費ロジック）

```php
public function consumeCurrency(int $playerId, string $currencyId, int $amount): CurrencyOperationResult
{
    // 1. 残高チェック
    $wallet = $this->getWalletOrFail($playerId, $currencyId);
    if ($wallet->total_amount < $amount) {
        throw new InsufficientBalanceException($currencyId, $amount, $wallet->total_amount);
    }
    
    // 2. FIFO順で取得（有償優先 → 有効期限近い順）
    $balances = $this->getBalancesInFifoOrder($playerId, $currencyId);
    
    // 3. FIFO消費
    $remaining = $amount;
    $consumedFree = 0;
    $consumedPaid = 0;
    
    foreach ($balances as $balance) {
        $consume = min($balance->current_amount, $remaining);
        $balance->current_amount -= $consume;
        $balance->save();
        
        if ($balance->is_paid) {
            $consumedPaid += $consume;
        } else {
            $consumedFree += $consume;
        }
        
        $remaining -= $consume;
        if ($remaining <= 0) break;
    }
    
    // 4. 現在値を更新
    $wallet->free_amount -= $consumedFree;
    $wallet->paid_amount -= $consumedPaid;
    $wallet->save();
    
    return new CurrencyOperationResult(
        freeAmount: $consumedFree,
        paidAmount: $consumedPaid,
        totalAmount: $amount,
        currentBalance: $wallet->total_amount
    );
}
```

## ライセンス

MIT License
