<?php

namespace NexusAgreement\Repositories;

use NexusAgreement\DataTransferObjects\Agreement;

/**
 * AgreementRepositoryInterface
 *
 * 利用規約（sys_agreement）と同意記録（trx_agreement）へのアクセスを抽象化する
 */
interface AgreementRepositoryInterface
{
    /**
     * 必須規約のうち、指定日時までに公開されたものを取得する
     *
     * @param  string  $now  基準日時（Y-m-d H:i:s形式）
     * @return array<int, Agreement>
     */
    public function selectRequiredPublishedUntil(string $now): array;

    /**
     * プレイヤーが同意済みの sys_agreement_id 一覧を取得する
     *
     * @return array<int, int>
     */
    public function selectConsentedAgreementIds(int $sysPlayerId): array;

    /**
     * 同意記録を1件追加する
     *
     * 同意済みの規約が渡ることは無い前提（AgreementService が事前に判定する）
     *
     * @param  string  $consentedAt  同意日時（Y-m-d H:i:s形式）
     */
    public function insertConsent(int $sysPlayerId, int $sysAgreementId, string $consentedAt): void;
}
