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
    public static function toDto(SysAgreement $model): Agreement
    {
        return new Agreement(
            id: $model->getId(),
            version: $model->getVersion(),
            title: $model->getTitle(),
            url: $model->getUrl(),
            isRequired: $model->getIsRequired(),
            publishedAt: $model->getPublishedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysAgreement>  $models
     * @return array<int, Agreement>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];

        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
