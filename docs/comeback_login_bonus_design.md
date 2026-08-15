# カムバックログインボーナスシステム設計

## 概要

カムバックログインボーナスは、一定期間ログインしていなかったプレイヤーが復帰した際に特別な報酬を付与する機能です。

## 設計方針

### 1. 既存システムとの関係
- **通常ログインボーナス**: 毎日のログイン継続を促進（`LoginBonusService`）
- **カムバックボーナス**: 休眠プレイヤーの復帰を促進（`ComeBackLoginBonusService`）
- **両立**: 同じ日に両方のボーナスを受け取ることが可能

### 2. モダンな設計パターン

#### Strategy Pattern（戦略パターン）
- `LoginBonusStrategyInterface`: ログインボーナス配布の共通インターフェース
- `DailyLoginBonusStrategy`: 通常の毎日ログインボーナス
- `ComeBackLoginBonusStrategy`: カムバックログインボーナス
- `LoginBonusOrchestrator`: 複数の戦略を統合管理

#### Template Method Pattern（テンプレートメソッドパターン）
- `_BaseLoginBonusService`: 基底クラス（共通処理を定義）
- `LoginBonusService`: 通常ログインボーナス実装
- `ComeBackLoginBonusService`: カムバックボーナス実装

#### Specification Pattern（仕様パターン）
- `ComeBackEligibilitySpecification`: カムバック対象判定ロジック

## データベース設計

### mst_login_bonus（既存テーブル拡張）
既存のログインボーナステーブルに`type`カラムと`required_absent_days`カラムを追加

```sql
ALTER TABLE mst_login_bonus
ADD COLUMN type ENUM('daily', 'comeback') DEFAULT 'daily' COMMENT 'ログインボーナスタイプ' AFTER id,
ADD COLUMN required_absent_days INT UNSIGNED NULL COMMENT '必要休眠日数（カムバック用、nullの場合は通常）' AFTER loop_days,
ADD COLUMN valid_days INT UNSIGNED NULL COMMENT 'ボーナス有効期間（カムバック用）' AFTER required_absent_days,
ADD COLUMN priority INT UNSIGNED DEFAULT 0 COMMENT '優先度（カムバック用、複数条件該当時の優先順位）' AFTER valid_days,
ADD COLUMN start_at DATETIME NULL COMMENT '開始日時（期間限定用）' AFTER is_active,
ADD COLUMN end_at DATETIME NULL COMMENT '終了日時（期間限定用）' AFTER start_at,
ADD INDEX idx_type (type),
ADD INDEX idx_absent_days (required_absent_days),
ADD INDEX idx_priority (priority);
```

**テーブル構造（拡張後）:**
- `type`: 'daily'（通常ログインボーナス） or 'comeback'（カムバックボーナス）
- **通常ログインボーナス（type='daily'）の場合:**
  - `day`: ログイン日数（1〜N）
  - `loop_days`: ループ日数（7日、30日など）
  - `required_absent_days`: NULL
  - `valid_days`: NULL
  - `priority`: 0（未使用）
  
- **カムバックボーナス（type='comeback'）の場合:**
  - `day`: 0（未使用）
  - `loop_days`: 0（未使用）
  - `required_absent_days`: 必要休眠日数（例: 7, 30）
  - `valid_days`: ボーナス有効期間（例: 7, 14）
  - `priority`: 優先度（大きいほど優先、30日休眠 > 7日休眠）

### mst_login_bonus_content（既存テーブル・変更なし）
既存の報酬テーブルをそのまま使用。`mst_login_bonus_id`で通常・カムバック両方の報酬を管理

### trx_login_bonus_history（既存テーブル拡張）
カムバック情報を記録するカラムを追加

```sql
ALTER TABLE trx_login_bonus_history
ADD COLUMN absent_days INT UNSIGNED NULL COMMENT '休眠日数（カムバックボーナスの場合のみ）' AFTER mst_login_bonus_id,
ADD INDEX idx_absent_days (absent_days);
```

## クラス設計

### 1. Package層（nexus-login）

基本的な機能を提供する抽象クラス群

```php
namespace NexusLogin\Services;

/**
 * _BaseLoginBonusService
 * 
 * ログインボーナス全般の基本処理を提供
 * Template Method Patternで拡張可能な設計
 */
abstract class _BaseLoginBonusService implements LoginBonusStrategyInterface
{
    // 必須実装メソッド
    abstract public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool;
    abstract protected function getLoginBonusData(int $sysPlayerId, ?string $lastLoginAt): ?array;
    
    // オーバーライド可能なフック
    protected function beforeGrant(...): bool;
    protected function afterGrant(...): void;
    protected function convertToResource(object $content): Resource;
    
    // 共通処理（final）
    final public function process(...): array;
    protected function grantBonus(...): array;
    protected function recordHistory(...): void;
}
```

### 2. Domain層（app/Domain/Login/Services）

アプリケーション固有のロジックを実装

```php
namespace App\Domain\Login\Services;

/**
 * LoginBonusService
 * 
 * 通常ログインボーナス（_BaseLoginBonusServiceを継承）
 */
class LoginBonusService extends _BaseLoginBonusService
{
    public function isEligible(...): bool
    {
        // 今日初回ログインかチェック
    }
    
    protected function getLoginBonusData(...): ?array
    {
        // 連続ログイン日数から該当データを取得
    }
}

/**
 * ComeBackLoginBonusService
 * 
 * カムバックログインボーナス（_BaseLoginBonusServiceを継承）
 */
class ComeBackLoginBonusService extends _BaseLoginBonusService
{
    public function isEligible(...): bool
    {
        // 休眠日数チェック
    }
    
    protected function getLoginBonusData(...): ?array
    {
        // 休眠日数から該当データを取得
    }
    
    // フックのオーバーライド例
    protected function beforeGrant(...): bool
    {
        // VIPレベルチェックなど
    }
    
    protected function afterGrant(...): void
    {
        // 分析ログ送信、プッシュ通知など
    }
}
```

### 3. Strategy Pattern実装

```php
namespace NexusLogin\Contracts;

interface LoginBonusStrategyInterface
{
    public function process(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array;
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool;
}
```

### 4. LoginBonusOrchestrator（オーケストレーター）

```php
namespace NexusLogin\Services;

class LoginBonusOrchestrator
{
    private array $strategies = [];
    
    public function registerStrategy(LoginBonusStrategyInterface $strategy, int $priority = 0): void;
    public function executeAll(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array;
    public function executeAllMerged(...): array;
}
```

## 使用例

### AppServiceProviderでの登録

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    // LoginBonusOrchestratorに戦略を登録
    $this->app->afterResolving(\NexusLogin\Services\LoginBonusOrchestrator::class, function ($orchestrator, $app) {
        // 通常ログインボーナス戦略（優先度: 100）
        // Domain側のServiceを使用
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\LoginBonusService::class),
            100
        );
        
        // カムバックログインボーナス戦略（優先度: 200、先に実行）
        // Domain側のServiceを使用
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\ComeBackLoginBonusService::class),
            200
        );
    });
}
```

### コントローラーでの使用

```php
// LoginController.php
public function login(Request $request)
{
    // ... 認証処理 ...
    
    $rewards = $this->loginBonusOrchestrator->executeAll(
        sysPlayerId: $player->getId(),
        lastLoginAt: $player->getLastLoginAt(),
        connectionName: 'trx_shard_1'
    );
    
    return response()->json([
        'player' => $player,
        'login_bonuses' => $rewards,
    ]);
}
```

## データ例

### 通常ログインボーナス設定例

```php
// 7日間ループの通常ログインボーナス
[
    'id' => 'daily_login_day1',
    'type' => 'daily',
    'day' => 1,
    'loop_days' => 7,
    'required_absent_days' => null,
    'valid_days' => null,
    'priority' => 0,
    'is_active' => true,
]
```

### カムバックボーナス設定例

```php
// 7日間休眠プレイヤー向け
[
    'id' => 'comeback_7days',
    'type' => 'comeback',
    'day' => 0, // 未使用
    'loop_days' => 0, // 未使用
    'required_absent_days' => 7,
    'valid_days' => 7, // 7日間有効
    'priority' => 1,
    'is_active' => true,
]

// 30日間休眠プレイヤー向け（より豪華）
[
    'id' => 'comeback_30days',
    'type' => 'comeback',
    'day' => 0, // 未使用
    'loop_days' => 0, // 未使用
    'required_absent_days' => 30,
    'valid_days' => 14, // 14日間有効
    'priority' => 2, // 優先度高（30日休眠の方が豪華な報酬）
    'is_active' => true,
]
```

## 判定フロー

1. **ログイン時**: LoginBonusOrchestratorが全戦略を実行
2. **通常ログインボーナス**: 前日ログインがあれば連続ログイン判定
3. **カムバックボーナス**: 
   - 最終ログインから7日以上 → `comeback_7days`を配布
   - 最終ログインから30日以上 → `comeback_30days`を配布（優先度高）
4. **重複防止**: カムバックボーナスは有効期間内に1度のみ受取可能

## 拡張性

### 将来の拡張候補
- **VIPランク別カムバックボーナス**: VIPレベルによって報酬を変える
- **季節限定カムバックボーナス**: イベント期間中の特別報酬
- **段階的カムバックボーナス**: 復帰後、連続ログインで追加報酬
- **カムバックミッション**: 復帰後の特別ミッション

### 設計の利点

### 1. レイヤー分離
- **Package層**: ログインボーナスの汎用的なフレームワークを提供
- **Domain層**: アプリケーション固有のビジネスロジックを実装
- 再利用性が高く、他プロジェクトでも package を流用可能

### 2. Template Method Pattern
- 基本処理は _BaseLoginBonusService に実装
- 拡張ポイント（フック）をオーバーライドして機能追加
- 共通部分のコード重複を排除

### 3. Open/Closed原則
- 新しい戦略を追加しても既存コードを変更不要
- VIP専用ボーナス、イベント限定ボーナスなど、将来の拡張が容易

### 4. Single Responsibility
- 各Serviceは1つの責務のみを持つ
- LoginBonusService: 通常ログインボーナス
- ComeBackLoginBonusService: カムバックボーナス

### 5. 依存性逆転
- インターフェースに依存し、具象クラスに依存しない
- テスタビリティが高い

### 6. 拡張例

```php
// Domain側で新しいボーナスタイプを追加
class VipLoginBonusService extends _BaseLoginBonusService
{
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        // VIPレベルをチェック
        $player = $this->playerRepository->findById($sysPlayerId);
        return $player && $player->getVipLevel() >= 5;
    }
    
    protected function getLoginBonusData(int $sysPlayerId, ?string $lastLoginAt): ?array
    {
        // VIP専用ボーナスを取得
    }
    
    protected function convertToResource(object $content): Resource
    {
        // VIPレベルによって報酬量を1.5倍
        $content->amount = (int) ($content->amount * 1.5);
        return parent::convertToResource($content);
    }
}

// AppServiceProviderで登録
$orchestrator->registerStrategy(
    $app->make(\App\Domain\Login\Services\VipLoginBonusService::class),
    150 // 優先度
);
```

## パフォーマンス考慮

- **クエリ最適化**: インデックスを適切に設定
- **キャッシュ戦略**: カムバックボーナス設定はRedisでキャッシュ
- **バッチ処理**: 履歴記録は非同期Jobで実行可能
