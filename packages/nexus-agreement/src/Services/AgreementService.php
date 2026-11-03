<?php

namespace NexusAgreement\Services;

use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusAgreement\DataTransferObjects\Agreement;
use NexusAgreement\DataTransferObjects\PendingAgreement;
use NexusAgreement\Exceptions\AgreementException;
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
        $requiredAgreements = $this->repository->selectRequiredPublishedUntil(ClockUtility::nowToString());
        $consentedIds = $this->repository->selectConsentedAgreementIds($sysPlayerId);

        $pendingAgreements = array_map(
            fn (Agreement $agreement) => new PendingAgreement(
                sysAgreementId: $agreement->getId(),
                version: $agreement->getVersion(),
                title: $agreement->getTitle(),
                url: $agreement->getUrl(),
                publishedAt: $agreement->getPublishedAt(),
            ),
            array_values(array_filter(
                $requiredAgreements,
                fn (Agreement $agreement) => ! in_array($agreement->getId(), $consentedIds, true),
            )),
        );

        return new CustomCollection($pendingAgreements);
    }

    /**
     * 規約への同意を記録する
     *
     * @throws AgreementException 規約が存在しない場合
     * @throws AgreementException 既に同意済みの場合
     */
    public function consent(int $sysPlayerId, int $sysAgreementId): void
    {
        $now = ClockUtility::nowToString();

        // 規約の存在確認（公開済みの必須規約のみ対象）
        $exists = false;
        foreach ($this->repository->selectRequiredPublishedUntil($now) as $agreement) {
            if ($agreement->getId() === $sysAgreementId) {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            throw AgreementException::agreementNotFound($sysAgreementId);
        }

        // 既同意チェック
        if (in_array($sysAgreementId, $this->repository->selectConsentedAgreementIds($sysPlayerId), true)) {
            throw AgreementException::alreadyConsented($sysAgreementId);
        }

        $this->repository->insertConsent($sysPlayerId, $sysAgreementId, $now);
    }
}
