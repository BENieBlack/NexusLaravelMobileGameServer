<?php

namespace App\Http\Responses\Agreement;

use App\Http\Responses\_BaseResponse;
use Nexus\Core\Support\CustomCollection;
use NexusAgreement\DataTransferObjects\PendingAgreement;

/**
 * PendingResponse
 *
 * GET /api/agreement/pending のレスポンス
 */
class PendingResponse extends _BaseResponse
{
    /**
     * @param  CustomCollection<int, PendingAgreement>  $pendingAgreements
     */
    public function __construct(
        private readonly CustomCollection $pendingAgreements,
    ) {}

    /**
     * @param  CustomCollection<int, PendingAgreement>  $pendingAgreements
     */
    public static function fromCollection(CustomCollection $pendingAgreements): self
    {
        return new self($pendingAgreements);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agreement_list' => $this->pendingAgreements
                ->values()
                ->map(fn (PendingAgreement $dto) => [
                    'sys_agreement_id' => $dto->getSysAgreementId(),
                    'version' => $dto->getVersion(),
                    'title' => $dto->getTitle(),
                    'url' => $dto->getUrl(),
                    'published_at' => $dto->getPublishedAt(),
                ])
                ->all(),
        ];
    }
}
