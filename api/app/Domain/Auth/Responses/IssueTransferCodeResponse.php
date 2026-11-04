<?php

namespace App\Domain\Auth\Responses;

use App\Domain\_BaseResponse;

/**
 * IssueTransferCodeResponse
 *
 * POST /api/account/transfer_code/issue のレスポンス
 */
class IssueTransferCodeResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $transferCode,
        public readonly string $expiresAt,
    ) {}

    public function toArray(): array
    {
        return [
            'transfer_code' => $this->transferCode,
            'expires_at' => $this->expiresAt,
        ];
    }
}
