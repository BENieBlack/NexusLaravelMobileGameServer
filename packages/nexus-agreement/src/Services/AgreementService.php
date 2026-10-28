<?php

namespace NexusAgreement\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusAgreement\DataTransferObjects\PendingAgreement;
use NexusAgreement\Exceptions\AgreementException;
use NexusAgreement\Models\SysAgreement;
use NexusAgreement\Repositories\AgreementRepositoryInterface;

/**
 * AgreementService
 *
 * 利用規約のビジネスロジック
 */
class AgreementService
{
    public function __construct(
        private readonly AgreementRepositoryInterface $repository,
    ) {}

    /**
     * 未同意の必須規約一覧を返す
     *
     * @return CustomCollection<int, PendingAgreement>
     */
    public function getPendingAgreements(int $sysPlayerId): CustomCollection
    {
        $now = ClockUtility::now();

        $requiredAgreements = $this->repository->findRequiredPublishedSince($now);
        $consentedIds = $this->repository->findConsentedIds($sysPlayerId);

        /** @var CustomCollection<int, PendingAgreement> */
        return $requiredAgreements
            ->filter(fn (SysAgreement $agreement) => ! in_array($agreement->getId(), $consentedIds, true))
            ->map(fn (SysAgreement $agreement) => new PendingAgreement(
                sysAgreementId: $agreement->getId(),
                version: $agreement->getVersion(),
                title: $agreement->getTitle(),
                url: $agreement->getUrl(),
                publishedAt: ClockUtility::parse($agreement->getPublishedAt()),
            ));
    }

    /**
     * 規約への同意を記録する
     *
     * @throws AgreementException 規約が存在しない場合
     * @throws AgreementException 既に同意済みの場合
     */
    public function consent(int $sysPlayerId, int $sysAgreementId): void
    {
        $now = ClockUtility::now();

        // 規約の存在確認（公開済みの必須規約のみ対象）
        $requiredAgreements = $this->repository->findRequiredPublishedSince($now);
        $agreement = $requiredAgreements->first(
            fn (SysAgreement $a) => $a->getId() === $sysAgreementId
        );

        if ($agreement === null) {
            throw AgreementException::agreementNotFound($sysAgreementId);
        }

        // 既同意チェック
        $consentedIds = $this->repository->findConsentedIds($sysPlayerId);
        if (in_array($sysAgreementId, $consentedIds, true)) {
            throw AgreementException::alreadyConsented($sysAgreementId);
        }

        $this->repository->insertConsent($sysPlayerId, $sysAgreementId, $now);
    }
}
