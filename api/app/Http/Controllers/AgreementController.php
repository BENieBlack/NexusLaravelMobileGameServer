<?php

namespace App\Http\Controllers;

use App\Domain\Agreement\Support\AgreementExceptionTranslator;
use App\Http\Requests\Agreement\ConsentRequest;
use App\Http\Responses\Agreement\ConsentResponse;
use App\Http\Responses\Agreement\PendingResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;
use NexusAgreement\Services\AgreementService;

/**
 * AgreementController
 *
 * 利用規約関連のAPIエンドポイント
 */
class AgreementController extends _BaseController
{
    public function __construct(
        private readonly AgreementService $agreementService,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * 未同意の必須規約一覧取得
     *
     * GET /api/agreement/pending
     */
    public function pending(): JsonResponse
    {
        return $this->execute(fn () => PendingResponse::fromCollection(
            $this->agreementService->getPendingAgreements($this->apiSession->getSysPlayerId())
        ));
    }

    /**
     * 規約への同意を記録
     *
     * POST /api/agreement/consent
     */
    public function consent(ConsentRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            AgreementExceptionTranslator::translate(
                fn () => $this->agreementService->consent(
                    sysPlayerId: $this->apiSession->getSysPlayerId(),
                    sysAgreementId: $request->getSysAgreementId(),
                )
            );

            return new ConsentResponse;
        });
    }
}
