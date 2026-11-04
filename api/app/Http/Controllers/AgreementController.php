<?php

namespace App\Http\Controllers;

use App\Domain\Agreement\UseCases\ConsentUseCase;
use App\Domain\Agreement\UseCases\PendingUseCase;
use App\Http\Requests\Agreement\ConsentRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * AgreementController
 *
 * 利用規約関連のAPIエンドポイント
 */
class AgreementController extends _BaseController
{
    public function __construct(
        private readonly PendingUseCase $pendingUseCase,
        private readonly ConsentUseCase $consentUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * 未同意の必須規約一覧取得
     *
     * GET /api/agreement/pending
     */
    public function pending(): JsonResponse
    {
        return $this->execute(fn () => $this->pendingUseCase->handle($this->apiSession->getSysPlayerId()));
    }

    /**
     * 規約への同意を記録
     *
     * POST /api/agreement/consent
     */
    public function consent(ConsentRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            return $this->consentUseCase->handle(
                sysPlayerId: $this->apiSession->getSysPlayerId(),
                sysAgreementId: $request->getSysAgreementId(),
            );
        });
    }
}
