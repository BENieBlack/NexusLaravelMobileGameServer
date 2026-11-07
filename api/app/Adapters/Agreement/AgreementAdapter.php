<?php

namespace App\Adapters\Agreement;

use App\Models\Sys\SysAgreement;
use NexusAgreement\DataTransferObjects\Agreement;

/**
 * AgreementAdapter
 *
 * SysAgreement Model と Agreement の変換を行うアダプター
 */
class AgreementAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysAgreement $sysAgreement): Agreement
    {
        return new Agreement(
            id: $sysAgreement->getId(),
            version: $sysAgreement->getVersion(),
            title: $sysAgreement->getTitle(),
            url: $sysAgreement->getUrl(),
            isRequired: $sysAgreement->getIsRequired(),
            publishedAt: $sysAgreement->getPublishedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysAgreement>  $sysAgreements
     * @return array<int, Agreement>
     */
    public static function toDtoArray(iterable $sysAgreements): array
    {
        $dtos = [];

        foreach ($sysAgreements as $sysAgreement) {
            $dtos[] = self::toDto($sysAgreement);
        }

        return $dtos;
    }
}
