<?php

namespace App\Http\Controllers;

use App\Domain\Mailbox\UseCases\MailboxListUseCase;
use App\Domain\Mailbox\UseCases\MailboxOpenUseCase;
use App\Domain\Mailbox\UseCases\MailboxLockUseCase;
use App\Domain\Mailbox\UseCases\MailboxReceiveAllUseCase;
use App\Domain\Mailbox\UseCases\MailboxReceiveUseCase;
use App\Http\Requests\Mailbox\ListRequest;
use App\Http\Requests\Mailbox\OpenRequest;
use App\Http\Requests\Mailbox\LockRequest;
use App\Http\Requests\Mailbox\ReceiveAllRequest;
use App\Http\Requests\Mailbox\ReceiveRequest;
use App\Http\Responses\Mailbox\ListResponse;
use App\Http\Responses\Mailbox\OpenResponse;
use App\Http\Responses\Mailbox\LockResponse;
use App\Http\Responses\Mailbox\ReceiveAllResponse;
use App\Http\Responses\Mailbox\ReceiveResponse;
use App\Persistence\ApiSession;

/**
 * MailboxController
 *
 * メールボックス機能のエンドポイント
 */
class MailboxController extends _BaseController
{
    public function __construct(
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * メールボックス一覧取得
     *
     * @param ListRequest $request
     * @param ListUseCase $useCase
     * @return ListResponse
     */
    public function list(ListRequest $request, MailboxListUseCase $useCase): ListResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        
        return $useCase->exec(
            $sysPlayerId,
            $request->getCategory(),
            $request->getPriority(),
            $request->getOnlyUnread(),
            $request->getOnlyLocked()
        );
    }

    /**
     * メール既読
     *
     * @param OpenRequest $request
     * @param OpenUseCase $useCase
     * @return OpenResponse
     */
    public function open(OpenRequest $request, MailboxOpenUseCase $useCase): OpenResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->exec($sysPlayerId, $request->getTrxMailboxId());
    }

    /**
     * 添付配布物受取
     *
     * @param ReceiveRequest $request
     * @param ReceiveUseCase $useCase
     * @return ReceiveResponse
     */
    public function receive(ReceiveRequest $request, MailboxReceiveUseCase $useCase): ReceiveResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->exec($sysPlayerId, $request->getTrxMailboxId());
    }

    /**
     * 添付配布物一括受取
     *
     * @param ReceiveAllRequest $request
     * @param ReceiveAllUseCase $useCase
     * @return ReceiveAllResponse
     */
    public function receiveAll(ReceiveAllRequest $request, MailboxReceiveAllUseCase $useCase): ReceiveAllResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->exec(
            $sysPlayerId,
            $request->getTrxMailboxIds(),
            $request->getCategory()
        );
    }

    /**
     * メールロック
     *
     * @param LockRequest $request
     * @param LockUseCase $useCase
     * @return LockResponse
     */
    public function lock(LockRequest $request, MailboxLockUseCase $useCase): LockResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->exec(
            $sysPlayerId,
            $request->getTrxMailboxId(),
            $request->getIsLocked()
        );
    }
}
