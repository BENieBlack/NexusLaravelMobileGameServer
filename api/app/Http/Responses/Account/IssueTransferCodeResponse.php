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

    public static function fromResult(IssueTransferCodeResult $issueTransferCodeResult): self
    {
        return new self(
            transferCode: $issueTransferCodeResult->transferCode,
            expiresAt: $issueTransferCodeResult->expiresAt,
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
