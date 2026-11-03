<?php

namespace App\Repositories\Sys;

use App\Adapters\Agreement\AgreementAdapter;
use App\Models\Trx\TrxAgreement;
use App\Repositories\Trx\TrxAgreementRepository;
use NexusAgreement\Repositories\AgreementRepositoryInterface;

/**
 * AgreementRepositoryAdapter
 *
 * SysAgreementRepository / TrxAgreementRepository（Model）と
 * AgreementRepositoryInterface（DTO）の橋渡し
 */
class AgreementRepositoryAdapter implements AgreementRepositoryInterface
{
    public function __construct(
        private readonly SysAgreementRepository $sysAgreementRepository,
        private readonly TrxAgreementRepository $trxAgreementRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectRequiredPublishedUntil(string $now): array
    {
        return AgreementAdapter::toDtoArray($this->sysAgreementRepository->selectRequiredPublishedUntil($now));
    }

    /**
     * {@inheritDoc}
     *
     * TrxAgreementRepository はログイン中プレイヤーの行を引く
     */
    public function selectConsentedAgreementIds(int $sysPlayerId): array
    {
        return $this->trxAgreementRepository->selectAllByPlayer()
            ->map(fn (TrxAgreement $trxAgreement) => $trxAgreement->getSysAgreementId())
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function insertConsent(int $sysPlayerId, int $sysAgreementId, string $consentedAt): void
    {
        $this->trxAgreementRepository->insertConsent($sysPlayerId, $sysAgreementId, $consentedAt);
    }
}
