<?php

namespace App\Domain\Item\DataTransferObjects;

/**
 * UseItemResult
 *
 * アイテム使用の結果
 */
class UseItemResult
{
    public function __construct(
        public readonly string $mstItemId,
        public readonly string $effect,
        public readonly int $itemUsed,
        public readonly int $appliedValue,
    ) {}
}
