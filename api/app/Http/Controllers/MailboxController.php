<?php

namespace App\Http\Controllers;

use App\Domain\Mailbox\UseCases\ListUseCase;
use App\Domain\Mailbox\UseCases\OpenUseCase;
use App\Domain\Mailbox\UseCases\ReceiveUseCase;
use App\Http\Requests\Mailbox\ListRequest;
use App\Http\Requests\Mailbox\OpenRequest;
use App\Http\Requests\Mailbox\ReceiveRequest;
use App\Http\Responses\Mailbox\ListResponse;
use App\Http\Responses\Mailbox\OpenResponse;
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
    public function list(ListRequest $request, ListUseCase $useCase): ListResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->handle($sysPlayerId);
    }

    /**
     * メール既読
     *
     * @param OpenRequest $request
     * @param OpenUseCase $useCase
     * @return OpenResponse
     */
    public function open(OpenRequest $request, OpenUseCase $useCase): OpenResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->handle($sysPlayerId, $request->getTrxMailboxId());
    }

    /**
     * 添付配布物受取
     *
     * @param ReceiveRequest $request
     * @param ReceiveUseCase $useCase
     * @return ReceiveResponse
     */
    public function receive(ReceiveRequest $request, ReceiveUseCase $useCase): ReceiveResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $useCase->handle($sysPlayerId, $request->getTrxMailboxId());
    }
}
