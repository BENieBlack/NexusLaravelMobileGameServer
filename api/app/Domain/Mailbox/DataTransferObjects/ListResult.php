<?php

namespace App\Domain\Mailbox\DataTransferObjects;

use App\Models\Trx\TrxMailbox;
use Nexus\Core\Support\CustomCollection;

/**
 * ListResult
 *
 * メールボックス一覧取得の結果
 */
class ListResult
{
    /**
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection  メールボックス一覧
     * @param  array<string, int>  $unreadCounts  カテゴリ別未読数
     */
    public function __construct(
        public readonly CustomCollection $trxMailboxCollection,
        public readonly array $unreadCounts = [],
    ) {}
}
