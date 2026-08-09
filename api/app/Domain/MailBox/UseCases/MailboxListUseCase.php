<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\MailBox\Constants\Category;
use App\Domain\MailBox\Constants\Priority;
use App\Http\Responses\Mailbox\ListResponse;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * MailboxListUseCase
 *
 * メールボックス一覧取得
 */
class MailboxListUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {}

    /**
     * メールボックス一覧を取得
     *
     * @param  string|null  $category  カテゴリフィルタ
     * @param  string|null  $priority  優先度フィルタ
     * @param  bool  $onlyUnread  未読のみ
     * @param  bool  $onlyLocked  ロックのみ
     */
    public function exec(
        int $sysPlayerId,
        ?string $category = null,
        ?string $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): ListResponse {
        // Enumに変換
        $categoryEnum = $category !== null ? Category::fromString($category) : null;
        $priorityEnum = $priority !== null ? Priority::fromString($priority) : null;

        // メールボックス一覧を取得
        $trxMailboxCollection = $this->trxMailboxRepository->selectByPlayerId(
            $sysPlayerId,
            $categoryEnum,
            $priorityEnum,
            $onlyUnread,
            $onlyLocked
        );

        // カテゴリ別未読数を取得
        $unreadCounts = $this->trxMailboxRepository->countUnreadByCategory($sysPlayerId);

        return ListResponse::fromCollection($trxMailboxCollection, $unreadCounts);
    }
}
