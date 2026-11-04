<?php

namespace App\Domain\Agreement\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Agreement\Responses\ConsentResponse;
use App\Domain\Agreement\Support\AgreementExceptionTranslator;
use App\Exceptions\GameException;
use NexusAgreement\Services\AgreementService;

/**
 * ConsentUseCase
 *
 * 利用規約への同意を記録する
 */
class ConsentUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly AgreementService $agreementService,
    ) {}

    /**
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  int  $sysAgreementId  sys_agreement.id
     *
     * @throws GameException 規約が存在しない・同意済みの場合
     */
    public function handle(int $sysPlayerId, int $sysAgreementId): ConsentResponse
    {
        $this->executeWithTransaction(
            fn () => AgreementExceptionTranslator::translate(
                fn () => $this->agreementService->consent($sysPlayerId, $sysAgreementId)
            )
        );

        return new ConsentResponse;
    }
}
