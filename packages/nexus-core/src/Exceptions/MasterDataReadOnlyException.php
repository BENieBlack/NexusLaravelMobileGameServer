<?php

namespace Nexus\Core\Exceptions;

use RuntimeException;

/**
 * マスターデータへの不正な書き込みを検出した際に投げる例外
 *
 * マスターデータはデプロイで投入するものであり、実行時に書き換えてはならない。
 * 投入側は _BaseMst::allowWrites() で明示的に許可する。
 */
class MasterDataReadOnlyException extends RuntimeException
{
    /**
     * @param  class-string  $modelClass  対象のモデルクラス
     * @param  string  $operation  試みた操作（save / update / delete など）
     */
    public static function forOperation(string $modelClass, string $operation): self
    {
        return new self(
            "マスターデータは読み取り専用です: {$modelClass}::{$operation}() が実行時に呼ばれました。".
            'マスターデータの投入は _BaseMst::allowWrites() で明示的に許可してください。'
        );
    }
}
