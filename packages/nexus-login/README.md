# Nexus Login

ログインボーナス管理パッケージ。モバイルゲーム向けの日次報酬サイクル機能を提供します。

## Features

- ✅ ログインボーナスの自動配布
- ✅ 連続ログイン日数のカウント
- ✅ 周期的なボーナスサイクル管理（7日周期など）
- ✅ ゲーム内日付対応（DAY_START_TIME考慮）
- ✅ 複数報酬の同時配布
- ✅ シャーディング対応
- ✅ 有償/無償アイテム区別

## Installation

```bash
composer require nexus/login
```

## Dependencies

- `nexus/resource`: リソース表現
- `nexus/resource-delivery`: リソース配布
- `nexus/utilities`: 時刻管理（ClockUtility）

## Usage

### 基本的な使い方

```php
use NexusLogin\Services\LoginBonusService;
use NexusResourceDelivery\Services\ResourceDeliveryService;

$loginBonusService = new LoginBonusService($resourceDeliveryService);

// ログインボーナスをチェックして配布
$rewards = $loginBonusService->checkAndGrantLoginBonus(
    sysPlayerId: 12345,
    lastLoginAt: '2024-07-16 10:00:00',
    connectionName: 'trx1'
);

// 配布された報酬を確認
foreach ($rewards as $resource) {
    echo "Type: {$resource->getType()}, ID: {$resource->getId()}, Amount: {$resource->getAmount()}\n";
}
```

### テスト用の時刻指定

```php
use Carbon\CarbonImmutable;

$now = CarbonImmutable::parse('2024-07-17 00:00:00');
$rewards = $loginBonusService->checkAndGrantLoginBonus(
    sysPlayerId: 12345,
    lastLoginAt: '2024-07-16 10:00:00',
    connectionName: 'trx1',
    now: $now
);
```

## Database Schema

### mst_login_bonus

```sql
CREATE TABLE mst_login_bonus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    day INT NOT NULL,
    loop_days INT NOT NULL DEFAULT 7,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### mst_login_bonus_content

```sql
CREATE TABLE mst_login_bonus_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mst_login_bonus_id INT NOT NULL,
    content_type VARCHAR(50) NOT NULL,
    content_mst_id VARCHAR(255) NOT NULL,
    amount INT NOT NULL,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### trx_login_bonus_history

```sql
CREATE TABLE trx_login_bonus_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sys_player_id INT NOT NULL,
    mst_login_bonus_id INT NOT NULL,
    received_date DATETIME NOT NULL,
    reward_type VARCHAR(50) NOT NULL,
    reward_mst_id VARCHAR(255) NOT NULL,
    reward_amount INT NOT NULL,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_player_date (sys_player_id, received_date)
);
```

## How It Works

### ログインボーナスのサイクル

1. **初回ログイン**: 連続ログイン日数 = 1
2. **連続ログイン**: 前日にログインしていれば日数+1
3. **途切れた場合**: 連続ログイン日数をリセット（1に戻る）
4. **周期**: `loop_days`（例: 7日）で1周し、8日目は1日目に戻る

### ゲーム内日付の計算

```php
// DAY_START_TIME = 4（午前4時）の場合
// 2024-07-17 03:59:59 → ゲーム内日付: 2024-07-16
// 2024-07-17 04:00:00 → ゲーム内日付: 2024-07-17
```

### 連続ログイン判定

- 過去7日間の履歴を確認
- ゲーム内日付ベースで判定
- 同じゲーム内日付での複数ログインは1回とカウント

## Configuration

設定ファイルは不要です。全ての設定はマスタデータ（`mst_login_bonus`）で管理されます。

## Testing

```bash
cd packages/nexus-login
../../vendor/bin/phpunit
```

## License

MIT
