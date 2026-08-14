<?php

namespace Nexus\Core\Exceptions;

use RuntimeException;

/**
 * Eloquentによる即時書き込みを検出した際に投げる例外
 *
 * Sys/Trx/LogのデータはUnitOfWorkでキューイングし、
 * UseCaseのトランザクション終了時に一括で反映する。
 * $model->save() などを直接呼ぶとトランザクション境界とPITRログの
 * 整合性が壊れるため禁止する。
 *
 * テストのフィクスチャやSeederなど、UnitOfWorkを介さずに投入する経路は
 * _BaseModel::allowDirectWrites() で明示的に許可する。
 */
class DirectWriteNotAllowedException extends RuntimeException
{
    /**
     * @param  class-string  $modelClass  対象のモデルクラス
     * @param  string  $operation  試みた操作（save / update / delete など）
     */
    public static function forOperation(string $modelClass, string $operation): self
    {
        return new self(
            "Eloquentによる即時書き込みは禁止されています: {$modelClass}::{$operation}() が呼ばれました。".
            'RepositoryのsetModel()でUnitOfWorkにキューイングしてください。'.
            'テストやSeederから投入する場合は _BaseModel::allowDirectWrites() で明示的に許可してください。'
        );
    }
}
