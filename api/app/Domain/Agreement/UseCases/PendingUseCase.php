<?php

namespace App\Domain\Agreement\UseCases;

use App\Domain\_BaseUseCase;
use Nexus\Core\Support\CustomCollection;
use NexusAgreement\DataTransferObjects\PendingAgreement;
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
     * @return CustomCollection<int, PendingAgreement>
     */
    public function handle(int $sysPlayerId): CustomCollection
    {
        return $this->agreementService->getPendingAgreements($sysPlayerId);
    }
}
