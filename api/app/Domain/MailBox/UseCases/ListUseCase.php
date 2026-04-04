<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Mailbox\ListResponse;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * ListUseCase
 *
 * メールボックス一覧取得
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {
    }

    /**
     * メールボックス一覧を取得
     *
     * @param int $sysPlayerId
     * @return ListResponse
     */
    public function handle(int $sysPlayerId): ListResponse
    {
        // メールボックス一覧を取得
        $trxMailboxCollection = $this->trxMailboxRepository->selectByPlayerId($sysPlayerId);

        return ListResponse::fromCollection($trxMailboxCollection);
    }
}
