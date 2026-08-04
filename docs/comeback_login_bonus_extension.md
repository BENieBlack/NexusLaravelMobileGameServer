# カムバックログインボーナス - 拡張ガイド

## Domain層でのカスタマイズ方法

_BaseLoginBonusServiceを継承することで、アプリケーション固有のロジックを実装できます。

## 基本的な拡張パターン

### 1. 配布前のチェック（beforeGrant）

カムバックボーナス配布前に追加のチェックを実施

```php
// app/Domain/Login/Services/ComeBackLoginBonusService.php

protected function beforeGrant(int $sysPlayerId, array $bonusData, string $connectionName): bool
{
    // 例1: VIPレベルチェック
    $player = $this->playerRepository->findById($sysPlayerId);
    if ($player->getVipLevel() < 1) {
        \Log::info('Comeback bonus skipped: VIP level too low', [
            'player_id' => $sysPlayerId,
        ]);
        return false; // VIP1未満は対象外
    }

    // 例2: BANユーザーチェック
    if ($player->isBanned()) {
        return false;
    }

    // 例3: メンテナンス中チェック
    if ($this->maintenanceService->isUnderMaintenance()) {
        return false;
    }

    return parent::beforeGrant($sysPlayerId, $bonusData, $connectionName);
}
```

### 2. 配布後の処理（afterGrant）

カムバックボーナス配布後に追加の処理を実施

```php
protected function afterGrant(int $sysPlayerId, array $bonusData, array $rewards, string $connectionName): void
{
    // 例1: 分析ログ送信
    \Log::info('Comeback bonus granted', [
        'player_id' => $sysPlayerId,
        'bonus_id' => $bonusData['id'],
        'absent_days' => $bonusData['absent_days'] ?? 0,
        'rewards_count' => count($rewards),
        'total_diamond' => $this->calculateTotalDiamond($rewards),
    ]);

    // 例2: プッシュ通知送信
    $this->pushNotificationService->send(
        playerId: $sysPlayerId,
        title: 'おかえりなさい！',
        message: sprintf(
            '%d日ぶりのログインです！カムバックボーナスを受け取りました。',
            $bonusData['absent_days']
        )
    );

    // 例3: 特別イベント発火
    if ($bonusData['absent_days'] >= 30) {
        event(new LongTermComebackEvent($sysPlayerId, $bonusData['absent_days']));
    }

    // 例4: 分析サービスへトラッキング
    $this->analyticsService->track('comeback_bonus_received', [
        'player_id' => $sysPlayerId,
        'absent_days' => $bonusData['absent_days'],
        'bonus_tier' => $this->getBonusTier($bonusData['absent_days']),
    ]);

    parent::afterGrant($sysPlayerId, $bonusData, $rewards, $connectionName);
}

private function getBonusTier(int $absentDays): string
{
    if ($absentDays >= 90) return 'tier_4_ultra';
    if ($absentDays >= 30) return 'tier_3_high';
    if ($absentDays >= 14) return 'tier_2_medium';
    return 'tier_1_low';
}
```

### 3. リソース変換のカスタマイズ（convertToResource）

報酬内容をVIPレベルや休眠日数に応じて調整

```php
protected function convertToResource(object $content): ResourceDto
{
    // VIPレベルによる報酬ボーナス
    $player = $this->getPlayer();
    $multiplier = 1.0;

    if ($player->getVipLevel() >= 10) {
        $multiplier = 2.0; // VIP10以上は2倍
    } elseif ($player->getVipLevel() >= 5) {
        $multiplier = 1.5; // VIP5以上は1.5倍
    }

    // 報酬量を調整
    $content->amount = (int) ($content->amount * $multiplier);

    // ログ記録
    if ($multiplier > 1.0) {
        \Log::info('Comeback bonus boosted', [
            'player_id' => $player->getId(),
            'vip_level' => $player->getVipLevel(),
            'multiplier' => $multiplier,
            'original_amount' => $content->amount / $multiplier,
            'boosted_amount' => $content->amount,
        ]);
    }

    return parent::convertToResource($content);
}
```

## 高度な拡張例

### 例1: 休眠日数に応じた特別アイテム追加

```php
protected function grantBonus(int $sysPlayerId, array $bonusData, string $connectionName): array
{
    // 基本報酬を配布
    $rewards = parent::grantBonus($sysPlayerId, $bonusData, $connectionName);

    // 30日以上休眠している場合、特別アイテムを追加
    if (($bonusData['absent_days'] ?? 0) >= 30) {
        $specialItem = ResourceDto::fromTypeString(
            typeString: 'item',
            id: 'special_comeback_ticket',
            amount: 1,
        );

        $this->resourceDeliveryService->addResources([$specialItem]);
        $this->resourceDeliveryService->deliver($sysPlayerId);

        $rewards[] = $specialItem;

        \Log::info('Special comeback item granted', [
            'player_id' => $sysPlayerId,
            'item_id' => 'special_comeback_ticket',
        ]);
    }

    return $rewards;
}
```

### 例2: カムバックミッション自動発行

```php
protected function afterGrant(int $sysPlayerId, array $bonusData, array $rewards, string $connectionName): void
{
    // カムバック専用ミッションを発行
    $this->missionService->issueComebackMission(
        sysPlayerId: $sysPlayerId,
        absentDays: $bonusData['absent_days']
    );

    \Log::info('Comeback mission issued', [
        'player_id' => $sysPlayerId,
        'absent_days' => $bonusData['absent_days'],
    ]);

    parent::afterGrant($sysPlayerId, $bonusData, $rewards, $connectionName);
}
```

### 例3: 段階的カムバックボーナス

復帰後の連続ログインで追加報酬

```php
class StagedComeBackLoginBonusService extends _BaseLoginBonusService
{
    protected function afterGrant(int $sysPlayerId, array $bonusData, array $rewards, string $connectionName): void
    {
        // カムバック後の連続ログイン日数を記録
        $this->setComebackLoginStreak($sysPlayerId, $connectionName);

        // 3日連続ログインで追加報酬
        $streak = $this->getComebackLoginStreak($sysPlayerId, $connectionName);
        if ($streak === 3) {
            $this->grantStreakBonus($sysPlayerId, 3);
        } elseif ($streak === 7) {
            $this->grantStreakBonus($sysPlayerId, 7);
        }

        parent::afterGrant($sysPlayerId, $bonusData, $rewards, $connectionName);
    }

    private function grantStreakBonus(int $sysPlayerId, int $streakDays): void
    {
        $bonus = ResourceDto::fromTypeString(
            typeString: 'diamond',
            id: 'free',
            amount: $streakDays * 100,
        );

        $this->resourceDeliveryService->addResources([$bonus]);
        $this->resourceDeliveryService->deliver($sysPlayerId);

        \Log::info('Comeback streak bonus granted', [
            'player_id' => $sysPlayerId,
            'streak_days' => $streakDays,
            'diamond_amount' => $streakDays * 100,
        ]);
    }
}
```

## 新しいログインボーナスタイプの追加

### VIP専用ログインボーナス

```php
namespace App\Domain\Login\Services;

use NexusLogin\Services\_BaseLoginBonusService;

class VipLoginBonusService extends _BaseLoginBonusService
{
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        $player = $this->playerRepository->findById($sysPlayerId);
        
        // VIP5以上のみ対象
        if (!$player || $player->getVipLevel() < 5) {
            return false;
        }

        // 今日初回ログインかチェック
        $currentTimeString = ClockUtility::nowToString();
        return $lastLoginAt === null || !ClockUtility::isSameGameDay($currentTimeString, $lastLoginAt);
    }

    protected function getLoginBonusData(int $sysPlayerId, ?string $lastLoginAt): ?array
    {
        $player = $this->playerRepository->findById($sysPlayerId);
        $vipLevel = $player->getVipLevel();

        // VIPレベル別のボーナスを取得
        // 例: vip_login_lv5, vip_login_lv10 など
        $bonusId = "vip_login_lv{$vipLevel}";
        
        return $this->bonusRepository->findActiveById($bonusId);
    }
}
```

### AppServiceProviderで登録

```php
public function boot(): void
{
    $this->app->afterResolving(\NexusLogin\Services\LoginBonusOrchestrator::class, function ($orchestrator, $app) {
        // 通常ログインボーナス（優先度: 100）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\LoginBonusService::class),
            100
        );
        
        // VIP専用ログインボーナス（優先度: 150）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\VipLoginBonusService::class),
            150
        );
        
        // カムバックログインボーナス（優先度: 200、最優先）
        $orchestrator->registerStrategy(
            $app->make(\App\Domain\Login\Services\ComeBackLoginBonusService::class),
            200
        );
    });
}
```

## テスト例

```php
namespace Tests\Domain\Login\Services;

use Tests\TestCase;
use App\Domain\Login\Services\ComeBackLoginBonusService;

class ComeBackLoginBonusServiceTest extends TestCase
{
    public function test_before_grant_hook_works()
    {
        // VIPレベルが低いプレイヤー
        $player = $this->createPlayer(['vip_level' => 0]);
        $lastLoginAt = now()->subDays(7)->toDateTimeString();

        $service = app(ComeBackLoginBonusService::class);

        // isEligibleはtrueだが、beforeGrantでfalseになる想定
        $this->assertTrue($service->isEligible($player->id, $lastLoginAt));
        
        $rewards = $service->process($player->id, $lastLoginAt, 'trx1');
        
        // beforeGrantで弾かれるため報酬なし
        $this->assertEmpty($rewards);
    }

    public function test_after_grant_sends_notification()
    {
        \Notification::fake();

        $player = $this->createPlayer(['vip_level' => 5]);
        $lastLoginAt = now()->subDays(30)->toDateTimeString();

        $service = app(ComeBackLoginBonusService::class);
        $service->process($player->id, $lastLoginAt, 'trx1');

        // プッシュ通知が送信されたことを確認
        \Notification::assertSentTo($player, ComebackBonusNotification::class);
    }
}
```

## まとめ

- **Package層**: 汎用的なフレームワーク（_BaseLoginBonusService）
- **Domain層**: アプリケーション固有のロジック（継承して実装）
- **拡張ポイント**: beforeGrant, afterGrant, convertToResource
- **新タイプ追加**: _BaseLoginBonusServiceを継承して新クラス作成
- **柔軟性**: Orchestratorで優先度を制御して複数タイプを統合

詳細な設計については `/docs/comeback_login_bonus_design.md` を参照してください。
