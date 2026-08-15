<?php

namespace App\Http\Controllers;

use App\Domain\Mailbox\UseCases\MailboxListUseCase;
use App\Domain\Mailbox\UseCases\MailboxLockUseCase;
use App\Domain\Mailbox\UseCases\MailboxOpenUseCase;
use App\Domain\Mailbox\UseCases\MailboxReceiveAllUseCase;
use App\Domain\Mailbox\UseCases\MailboxReceiveUseCase;
use App\Http\Requests\Mailbox\ListRequest;
use App\Http\Requests\Mailbox\LockRequest;
use App\Http\Requests\Mailbox\OpenRequest;
use App\Http\Requests\Mailbox\ReceiveAllRequest;
use App\Http\Requests\Mailbox\ReceiveRequest;
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
    public function list(ListRequest $request, MailboxListUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getCategory(),
            $request->getPriority(),
            $request->getOnlyUnread(),
            $request->getOnlyLocked()
        ));
    }

    /**
     * メール既読
     */
    public function open(OpenRequest $request, MailboxOpenUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxId()
        ));
    }

    /**
     * 添付配布物受取
     */
    public function receive(ReceiveRequest $request, MailboxReceiveUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxId()
        ));
    }

    /**
     * 添付配布物一括受取
     */
    public function receiveAll(ReceiveAllRequest $request, MailboxReceiveAllUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxIds(),
            $request->getCategory()
        ));
    }

    /**
     * メールロック
     */
    public function lock(LockRequest $request, MailboxLockUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec(
            $this->apiSession->getSysPlayerId(),
            $request->getTrxMailboxId(),
            $request->getIsLocked()
        ));
    }
}
