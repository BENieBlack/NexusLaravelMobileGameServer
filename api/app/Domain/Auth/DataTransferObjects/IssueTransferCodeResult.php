<?php

namespace App\Domain\Auth\DataTransferObjects;

/**
 * IssueTransferCodeResult
 *
 * 引き継ぎコード発行の結果
 */
class IssueTransferCodeResult
{
    /**
     * @param  string  $transferCode  引き継ぎコード
     * @param  string  $expiresAt  有効期限（Y-m-d H:i:s形式）
     */
    public function __construct(
        public readonly string $transferCode,
        public readonly string $expiresAt,
    ) {}
}
