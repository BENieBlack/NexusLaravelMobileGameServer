# VIPログインボーナスシステム設計

## 概要

VIPログインボーナスは、プレイヤーのVIPレベルに応じて異なる報酬を毎日配布する機能です。

## 設計方針

### 1. 既存システムとの関係
- **通常ログインボーナス**: 全プレイヤー共通の毎日ボーナス（`LoginBonusService`）
- **VIPログインボーナス**: VIPレベル別の毎日ボーナス（`VipLoginBonusService`）
- **カムバックボーナス**: 休眠プレイヤーの復帰促進（`ComeBackLoginBonusService`）
- **優先度制御**: カムバック > VIP > 通常の順で判定（同じ日に複数受け取り可能）

### 2. モダンな設計パターン

#### Strategy Pattern（戦略パターン）
- `LoginBonusStrategyInterface`: ログインボーナス配布の共通インターフェース
- `LoginBonusService`: 通常の毎日ログインボーナス
- `VipLoginBonusService`: VIPレベル別ログインボーナス
- `ComeBackLoginBonusService`: カムバックログインボーナス
- `LoginBonusOrchestrator`: 複数の戦略を統合管理（優先度順に実行）

#### Template Method Pattern（テンプレートメソッドパターン）
- `_BaseLoginBonusService`: 基底クラス（共通処理をデフォルト実装）
- `LoginBonusService`: 通常ログインボーナス（必須メソッドのみ実装）
- `VipLoginBonusService`: VIPログインボーナス（VIPレベル別データ取得ロジック追加）
- `ComeBackLoginBonusService`: カムバックボーナス（差分のみオーバーライド）

### 3. VIPレベル管理との連携

VIPシステム（NexusVipパッケージ）と連携してVIPレベルを取得：
- `sys_player.vip_point`: VIPポイント累計値
- `mst_vip_level`: VIPレベル定義（VIP0〜VIP10）
- VIPレベルはVIPポイントから動的計算

## データベース設計

### mst_vip_login_bonus（VIPログインボーナス設定）

VIPレベルごとのログインボーナス設定を管理

```sql
CREATE TABLE mst_vip_login_bonus (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    vip_level INT UNSIGNED NOT NULL COMMENT 'VIPレベル（0〜10）',
    loop_days INT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'ループ日数',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '有効フラグ',
    start_at DATETIME NULL COMMENT '開始日時',
    end_at DATETIME NULL COMMENT '終了日時',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_vip_level (vip_level),
    INDEX idx_active (is_active),
    INDEX idx_period (start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**フィールド説明:**
- `vip_level`: 対象VIPレベル（0〜10）
- `loop_days`: ボーナスのループ日数（例: 7日で1週間サイクル）
- `is_active`: 有効/無効フラグ
- `start_at`, `end_at`: 期間限定設定（NULLの場合は常時有効）

### mst_vip_login_bonus_content（VIPログインボーナス報酬内容）

VIPログインボーナスの日別報酬内容を管理

```sql
CREATE TABLE mst_vip_login_bonus_content (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    mst_vip_login_bonus_id CHAR(26) NOT NULL COMMENT 'VIPログインボーナスID',
    day INT UNSIGNED NOT NULL COMMENT 'ログイン日数（1〜N）',
    content_type VARCHAR(50) NOT NULL COMMENT 'コンテンツタイプ（item, unit, equipment, diamond, currencyなど）',
    content_mst_id VARCHAR(100) NOT NULL COMMENT 'コンテンツID',
    content_option JSON NULL COMMENT 'コンテンツオプション（JSON形式）',
    content_quantity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'コンテンツ数量（基本単位）',
    amount INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '倍率（実際の配布量 = content_quantity × amount）',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (mst_vip_login_bonus_id) REFERENCES mst_vip_login_bonus(id) ON DELETE CASCADE,
    INDEX idx_bonus_day (mst_vip_login_bonus_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**フィールド説明:**
- `mst_vip_login_bonus_id`: VIPログインボーナス設定への外部キー
- `day`: ログイン日数（1〜loop_days）
- `content_type`, `content_mst_id`, `content_option`, `content_quantity`, `amount`: 統一コンテンツ構造

### trx_vip_login_bonus_history（VIPログインボーナス受取履歴）

プレイヤーのVIPログインボーナス受取履歴を記録（シャーディング対応）

```sql
CREATE TABLE trx_vip_login_bonus_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sys_player_id BIGINT UNSIGNED NOT NULL COMMENT 'プレイヤーID',
    mst_vip_login_bonus_id CHAR(26) NOT NULL COMMENT 'VIPログインボーナスID',
    day INT UNSIGNED NOT NULL COMMENT '受け取った日数',
    vip_level INT UNSIGNED NOT NULL COMMENT '受け取り時のVIPレベル',
    received_at DATETIME NOT NULL COMMENT '受け取り日時',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_player_received (sys_player_id, received_at),
    INDEX idx_player_bonus (sys_player_id, mst_vip_login_bonus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**シャーディング対応:**
- プレイヤーIDベースでシャーディング
- `received_at`を使って日跨ぎ判定と最新履歴取得

## クラス設計

### 1. Package層（nexus-login）

基本的な機能を提供する基底クラス

```php
namespace NexusLogin\Services;

/**
 * _BaseLoginBonusService
 * 
 * ログインボーナス全般の基本処理を提供
 * Template Method Patternで拡張可能な設計
 * 通常ログインボーナスの動作をデフォルト実装
 */
abstract class _BaseLoginBonusService implements LoginBonusStrategyInterface
{
    // 必須実装メソッド（各サービスで実装）
    abstract public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool;
    abstract protected function getLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array;
    abstract protected function getBonusContents(array $bonusData, int $currentDay): CustomCollection;
    abstract protected function recordHistory(...): void;
    abstract protected function getLastReceivedDay(int $sysPlayerId, string $connectionName): ?int;
    abstract protected function getLoopDays(int $sysPlayerId): ?int;
    
    // デフォルト実装（通常ログインボーナスの動作）
    protected function shouldLoop(): bool { return true; }
    protected function shouldSkipOnAbsence(): bool { return false; }
    
    // オーバーライド可能なフック
    protected function beforeGrant(...): bool { return true; }
    protected function afterGrant(...): void {}
    protected function convertToResource(object $content): Resource { /* 標準実装 */ }
}
```

### 2. Domain層（api/app/Domain/Login/Services）

VIPログインボーナスの具体的実装

```php
namespace App\Domain\Login\Services;

use NexusLogin\Services\_BaseLoginBonusService;

/**
 * VipLoginBonusService
 * 
 * VIPレベル別ログインボーナスの配布処理
 * 
 * 特性:
 * - 毎日日跨ぎ後にもらえる（通常ボーナスと同じ）
 * - VIPレベルに応じて報酬が異なる
 * - 設定日数でループする（shouldLoop() = true、継承元のデフォルト）
 * - 1日ログインしなくてもスキップしない（shouldSkipOnAbsence() = false、継承元のデフォルト）
 */
class VipLoginBonusService extends _BaseLoginBonusService
{
    // VIPレベル別データ取得ロジックのみ実装
    // ループ・スキップ動作は基底クラスのデフォルトを継承
}
```

### 3. Repository層

#### MstVipLoginBonusRepository

```php
namespace App\Repositories\Mst;

interface VipLoginBonusRepositoryInterface
{
    public function findActiveByVipLevel(int $vipLevel): ?array;
    public function findContentsByBonusIdAndDay(string $vipLoginBonusId, int $day): CustomCollection;
}
```

#### TrxVipLoginBonusHistoryRepository

```php
namespace App\Repositories\Trx;

interface VipLoginBonusHistoryRepositoryInterface
{
    public function create(array $data, string $connectionName): array;
    public function findLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;
    public function findByPlayerAndBonusAndDate(...): ?array;
}
```

## 処理フロー

### VIPログインボーナス配布フロー

```
1. ログイン時に LoginBonusOrchestrator::grant() が実行される
   ↓
2. 優先度順（200 > 150 > 100）に各戦略を実行
   - ComeBackLoginBonusService (優先度: 200)
   - VipLoginBonusService (優先度: 150) ← ここ
   - LoginBonusService (優先度: 100)
   ↓
3. VipLoginBonusService::isEligible() で対象判定
   - 今日初回ログインか？（日跨ぎチェック）
   - プレイヤーのVIPレベルに対応するボーナス設定があるか？
   ↓
4. VipLoginBonusService::grant() でボーナス配布
   - プレイヤーのVIPレベルを取得
   - VIPレベルに対応するボーナス設定を取得
   - 前回受取日から次の日数を計算（ループ処理）
   - 該当日の報酬を取得
   - ResourceDeliveryServiceで報酬配布
   - 履歴テーブルに記録
   ↓
5. 次の戦略へ（複数ボーナス並行受け取り可能）
```

### VIPレベル取得フロー

```
1. SysPlayerRepository::findVipInfoById() でプレイヤー情報取得
   ↓
2. sys_player.vip_point を取得
   ↓
3. VipService::calculateVipLevel() でVIPレベルを計算
   - mst_vip_level テーブルから閾値を取得
   - vip_point が閾値以上の最高レベルを返す
   ↓
4. VIPレベルに対応する mst_vip_login_bonus を取得
```

## 設定例

### VIP0プレイヤー用（7日ループ）

```php
// mst_vip_login_bonus
[
    'id' => '01HXXX...',
    'vip_level' => 0,
    'loop_days' => 7,
    'is_active' => 1,
]

// mst_vip_login_bonus_content
[
    ['day' => 1, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 1000],
    ['day' => 2, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 1000],
    ['day' => 3, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 1000],
    ['day' => 4, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 1500],
    ['day' => 5, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 1500],
    ['day' => 6, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 2000],
    ['day' => 7, 'content_type' => 'diamond', 'content_mst_id' => 'paid_diamond', 'content_quantity' => 10],
]
```

### VIP5プレイヤー用（7日ループ、報酬増量）

```php
// mst_vip_login_bonus
[
    'id' => '01HYYY...',
    'vip_level' => 5,
    'loop_days' => 7,
    'is_active' => 1,
]

// mst_vip_login_bonus_content（VIP0より豪華）
[
    ['day' => 1, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 2000],
    ['day' => 2, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 2000],
    ['day' => 3, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 2000],
    ['day' => 4, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 3000],
    ['day' => 5, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 3000],
    ['day' => 6, 'content_type' => 'currency', 'content_mst_id' => 'gold', 'content_quantity' => 4000],
    ['day' => 7, 'content_type' => 'diamond', 'content_mst_id' => 'paid_diamond', 'content_quantity' => 50],
]
```

## VIPレベル変動時の挙動

### ケース1: ログインボーナス途中でVIPレベルアップ

```
例: VIP0で3日目まで受け取り → 課金してVIP1になる → 次の日にログイン

動作:
1. 次回ログイン時、現在のVIPレベル（VIP1）に対応するボーナスを取得
2. VIP1の設定で4日目の報酬を配布
3. 履歴に vip_level=1 で記録

理由: VIPレベルは動的に計算されるため、常に最新のVIPレベルで判定
```

### ケース2: VIPレベルダウン（通常は発生しない）

```
例: VIP5で受け取り中 → 何らかの理由でVIP4になる

動作:
1. 次回ログイン時、現在のVIPレベル（VIP4）に対応するボーナスを取得
2. VIP4の設定がない場合は受け取れない
3. VIP4の設定がある場合は、VIP4の報酬を配布

注意: VIPレベルは通常減少しない（vip_pointは累計）
```

## ServiceProvider登録

```php
// api/app/Providers/AppServiceProvider.php

public function register(): void
{
    // VIPログインボーナス用Repository
    $this->app->bind(VipLoginBonusRepositoryInterface::class, MstVipLoginBonusRepository::class);
    $this->app->bind(VipLoginBonusHistoryRepositoryInterface::class, TrxVipLoginBonusHistoryRepository::class);
}

public function boot(): void
{
    // LoginBonusOrchestratorに戦略を登録
    $this->app->afterResolving(\NexusLogin\Services\LoginBonusOrchestrator::class, function ($orchestrator, $app) {
        // 通常ログインボーナス戦略（優先度: 100）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\LoginBonusService::class),
            100
        );
        
        // VIPログインボーナス戦略（優先度: 150）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\VipLoginBonusService::class),
            150
        );
        
        // カムバックログインボーナス戦略（優先度: 200、最優先）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\ComeBackLoginBonusService::class),
            200
        );
    });
}
```

## テスト観点

### 単体テスト

1. **VipLoginBonusServiceTest**
   - VIPレベル別のボーナス取得
   - ループ処理の動作確認
   - 日跨ぎ判定
   - 履歴記録

2. **MstVipLoginBonusRepositoryTest**
   - VIPレベル別設定取得
   - 報酬内容取得
   - 有効期間判定

3. **TrxVipLoginBonusHistoryRepositoryTest**
   - 履歴作成
   - 最新履歴取得
   - シャーディング動作

### 統合テスト

1. **VIPレベル別ボーナス配布**
   - VIP0〜VIP10それぞれでボーナス取得
   - 報酬内容がVIPレベルに応じて変わることを確認

2. **VIPレベル変動**
   - ボーナス受取中にVIPレベルアップ
   - 次回は新しいVIPレベルのボーナスが配布されることを確認

3. **複数ボーナス並行受取**
   - 同じ日に通常ボーナスとVIPボーナスを両方受け取れることを確認

4. **ループ処理**
   - loop_days経過後、1日目に戻ることを確認

## 関連ドキュメント

- [カムバックログインボーナス設計](./comeback_login_bonus_design.md)
- [ログインボーナス使用ガイド](./comeback_login_bonus_usage.md)
- [ログインボーナス拡張ガイド](./comeback_login_bonus_extension.md)
- VIPシステム設計（別途作成予定）
