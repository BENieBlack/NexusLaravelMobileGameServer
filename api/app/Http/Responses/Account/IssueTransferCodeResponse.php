<?php

namespace App\Http\Responses\Account;

use App\Domain\Auth\DataTransferObjects\IssueTransferCodeResult;
use App\Http\Responses\_BaseResponse;

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

    public static function fromResult(IssueTransferCodeResult $result): self
    {
        return new self(
            transferCode: $result->transferCode,
            expiresAt: $result->expiresAt,
        );
    }

    public function toArray(): array
    {
        return [
            'transfer_code' => $this->transferCode,
            'expires_at' => $this->expiresAt,
        ];
    }
}
