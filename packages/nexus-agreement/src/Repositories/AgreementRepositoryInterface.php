<?php

namespace NexusAgreement\Repositories;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Nexus\Core\Support\CustomCollection;

/**
 * AgreementRepositoryInterface
 *
 * 利用規約リポジトリの契約
 */
interface AgreementRepositoryInterface
{
    /**
     * 必須規約のうち指定日時以前に公開されたものを全件取得する（sysDB）
     *
     * @return CustomCollection<int, \NexusAgreement\Models\SysAgreement>
     */
    public function findRequiredPublishedSince(DateTimeInterface $since): CustomCollection;

    /**
     * プレイヤーが同意済みの sys_agreement_id 一覧を返す（trxDB）
     *
     * @return array<int, int>
     */
    public function findConsentedIds(int $sysPlayerId): array;

    /**
     * 同意記録を insert する（trxDB）
     */
    public function insertConsent(int $sysPlayerId, int $sysAgreementId, CarbonImmutable $consentedAt): void;
}
