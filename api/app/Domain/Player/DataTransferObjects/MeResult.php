<?php

namespace App\Domain\Player\DataTransferObjects;

/**
 * MeResult
 *
 * 認証済みプレイヤー情報取得の結果
 */
class MeResult
{
    public function __construct(
        public readonly string $myId,
        public readonly ?string $name,
    ) {}
}
