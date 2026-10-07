<?php

namespace App\Http\Controllers;

use App\Domain\Mailbox\UseCases\ListUseCase;
use App\Domain\Mailbox\UseCases\LockUseCase;
use App\Domain\Mailbox\UseCases\OpenUseCase;
use App\Domain\Mailbox\UseCases\ReceiveAllUseCase;
use App\Domain\Mailbox\UseCases\ReceiveUseCase;
use App\Http\Requests\Mailbox\ListRequest;
use App\Http\Requests\Mailbox\LockRequest;
use App\Http\Requests\Mailbox\OpenRequest;
use App\Http\Requests\Mailbox\ReceiveAllRequest;
use App\Http\Requests\Mailbox\ReceiveRequest;
use App\Http\Responses\Mailbox\ListResponse;
use App\Http\Responses\Mailbox\LockResponse;
use App\Http\Responses\Mailbox\OpenResponse;
use App\Http\Responses\Mailbox\ReceiveAllResponse;
use App\Http\Responses\Mailbox\ReceiveResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * MailboxController
 *
 * メールボックス機能のエンドポイント
 */
class MailboxController extends _BaseController
{
    public function __construct(
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * メールボックス一覧取得
     */
    public function list(ListRequest $request, ListUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => ListResponse::fromResult($useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getCategory(),
            $request->getPriority(),
            $request->getOnlyUnread(),
            $request->getOnlyLocked()
        )));
    }

    /**
     * メール既読
     */
    public function open(OpenRequest $request, OpenUseCase $useCase): JsonResponse
    {
        return $this->execute(function () use ($request, $useCase) {
            $trxMailboxId = $request->getTrxMailboxId();

            $useCase->exec($this->apiSession->getSysPlayerId(), $trxMailboxId);

            return new OpenResponse($trxMailboxId, true);
        });
    }

    /**
     * 添付配布物受取
     */
    public function receive(ReceiveRequest $request, ReceiveUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => ReceiveResponse::fromResult($useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxId()
        )));
    }

    /**
     * 添付配布物一括受取
     */
    public function receiveAll(ReceiveAllRequest $request, ReceiveAllUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => ReceiveAllResponse::fromResult($useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxIds(),
            $request->getCategory()
        )));
    }

    /**
     * メールロック
     */
    public function lock(LockRequest $request, LockUseCase $useCase): JsonResponse
    {
        return $this->execute(function () use ($request, $useCase) {
            $trxMailboxId = $request->getTrxMailboxId();
            $isLocked = $request->getIsLocked();

            $useCase->exec($this->apiSession->getSysPlayerId(), $trxMailboxId, $isLocked);

            return new LockResponse(
                trxMailboxId: $trxMailboxId,
                isLocked: $isLocked,
                success: true
            );
        });
    }
}
