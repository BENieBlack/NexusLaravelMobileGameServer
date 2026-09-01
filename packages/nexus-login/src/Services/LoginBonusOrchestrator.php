<?php

namespace NexusLogin\Services;

use NexusLogin\Contracts\LoginBonusStrategyInterface;
use NexusResource\DataTransferObjects\Resource;

/**
 * LoginBonusOrchestrator
 *
 * 複数のログインボーナス戦略を統合管理するオーケストレーター
 * 通常ログインボーナス、カムバックログインボーナスなどを一括処理
 */
class LoginBonusOrchestrator
{
    /** @var array<LoginBonusStrategyInterface> */
    private array $strategies = [];

    /**
     * ログインボーナス戦略を登録
     *
     * @param  LoginBonusStrategyInterface  $strategy  戦略
     * @param  int  $priority  優先度（大きいほど先に実行）
     * @return void
     */
    public function registerStrategy(LoginBonusStrategyInterface $strategy, int $priority = 0): void
    {
        $this->strategies[] = [
            'strategy' => $strategy,
            'priority' => $priority,
        ];

        // 優先度順にソート
        usort($this->strategies, fn ($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * 全ての適用可能な戦略を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string|null  $lastLoginAt  最終ログイン日時（UTC、文字列形式）
     * @param  string  $connectionName  シャーディングされたDB接続名
     * @return array{daily: array<resource>, comeback: array<resource>} 配布した報酬
     */
    public function executeAll(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array
    {
        $results = [
            'daily' => [],
            'comeback' => [],
        ];

        foreach ($this->strategies as $item) {
            /** @var LoginBonusStrategyInterface $strategy */
            $strategy = $item['strategy'];

            if ($strategy->isEligible($sysPlayerId, $lastLoginAt)) {
                $rewards = $strategy->process($sysPlayerId, $lastLoginAt, $connectionName);

                // クラス名から戦略の種類を判定
                $className = get_class($strategy);
                if (str_contains($className, 'ComeBackLoginBonusService')) {
                    $results['comeback'] = $rewards;
                } else {
                    $results['daily'] = $rewards;
                }
            }
        }

        return $results;
    }

    /**
     * 全ての報酬を統合して返す
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string|null  $lastLoginAt  最終ログイン日時（UTC、文字列形式）
     * @param  string  $connectionName  シャーディングされたDB接続名
     * @return array<resource> 配布した全報酬
     */
    public function executeAllMerged(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array
    {
        $results = $this->executeAll($sysPlayerId, $lastLoginAt, $connectionName);

        return array_merge($results['daily'], $results['comeback']);
    }

    /**
     * 登録されている戦略の数を取得
     *
     * @return int
     */
    public function getStrategyCount(): int
    {
        return count($this->strategies);
    }

    /**
     * 全ての戦略をクリア
     *
     * @return void
     */
    public function clearStrategies(): void
    {
        $this->strategies = [];
    }
}
