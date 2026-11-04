<?php

namespace App\Domain\Agreement\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Agreement\Responses\PendingResponse;
use NexusAgreement\Services\AgreementService;

/**
 * PendingUseCase
 *
 * 未同意の必須規約一覧を取得する
 */
class PendingUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly AgreementService $agreementService,
    ) {}

    /**
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     */
    public function handle(int $sysPlayerId): PendingResponse
    {
        return PendingResponse::from($this->agreementService->getPendingAgreements($sysPlayerId));
    }
}
