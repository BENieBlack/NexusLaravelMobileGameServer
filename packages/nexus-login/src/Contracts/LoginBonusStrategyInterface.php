<?php

namespace NexusLogin\Contracts;

/**
 * LoginBonusStrategyInterface
 * 
 * ログインボーナス配布の戦略インターフェース
 * Strategy Patternを使用して、通常ログインボーナスとカムバックログインボーナスを統合
 */
interface LoginBonusStrategyInterface
{
    /**
     * ログインボーナスを判定・配布
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string|null $lastLoginAt 最終ログイン日時（UTC、文字列形式）
     * @param string $connectionName シャーディングされたDB接続名
     * @return array<\NexusResource\DTOs\ResourceDto> 配布した報酬
     */
    public function process(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array;
    
    /**
     * この戦略が適用可能かチェック
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string|null $lastLoginAt 最終ログイン日時（UTC、文字列形式）
     * @return bool 適用可能ならtrue
     */
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool;
}
