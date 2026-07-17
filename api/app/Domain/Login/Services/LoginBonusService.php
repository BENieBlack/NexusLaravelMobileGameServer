<?php

namespace App\Domain\Login\Services;

use NexusLogin\Services\LoginBonusService as BaseLoginBonusService;
use NexusResource\DTOs\ResourceDto;
use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;

/**
 * LoginBonusService
 *
 * ログインボーナスの配布処理を担当するサービス
 * パッケージ版のLoginBonusServiceのラッパー
 */
class LoginBonusService
{
    public function __construct(
        private readonly ApiSession $apiSession,
        private readonly BaseLoginBonusService $baseService,
    ) {
    }

    /**
     * 今日初回ログインかどうかをチェックし、ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param string|null $lastLoginAt 最終ログイン日時（UTC、文字列形式）
     * @param CarbonImmutable|null $now 現在時刻（テスト用、通常はnull）
     * @return array<ResourceDto> 配布したログインボーナスの内容
     * @throws \Exception
     */
    public function checkAndGrantLoginBonus(
        int $sysPlayerId,
        ?string $lastLoginAt,
        ?CarbonImmutable $now = null
    ): array {
        // シャーディングされたDB接続名を取得
        $connectionName = $this->apiSession->getConnectionNameValue();
        
        // パッケージ版のServiceに委譲
        return $this->baseService->checkAndGrantLoginBonus(
            sysPlayerId: $sysPlayerId,
            lastLoginAt: $lastLoginAt,
            connectionName: $connectionName,
            now: $now
        );
    }
}

