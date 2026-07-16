<?php

namespace App\Repositories\Trx;

use App\Domain\MailBox\Constants\Category;
use App\Domain\MailBox\Constants\Priority;
use App\Models\Trx\TrxMailbox;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * TrxMailboxRepository
 *
 * プレイヤーのメールボックスデータを管理するRepository
 * 
 * @extends _BaseTrxRepository<TrxMailbox>
 */
class TrxMailboxRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxMailbox::class;

    /**
     * プレイヤーIDでメールボックス一覧を取得
     *
     * @param int $sysPlayerId
     * @param Category|null $category カテゴリフィルタ
     * @param Priority|null $priority 優先度フィルタ
     * @param bool $onlyUnread 未読のみ
     * @param bool $onlyProtected 保護のみ
     * @return Collection
     */
    public function selectByPlayerId(
        int $sysPlayerId, 
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyProtected = false
    ): Collection {
        $query = $this->modelClass::query()
            ->with(['mstMailbox.message', 'mstMailbox.contentCollection'])
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false);

        // 有効期限切れを除外
        $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        });

        // カテゴリフィルタ
        if ($category !== null) {
            $query->whereHas('mstMailbox', function ($q) use ($category) {
                $q->where('category', $category->value);
            });
        }

        // 優先度フィルタ
        if ($priority !== null) {
            $query->whereHas('mstMailbox', function ($q) use ($priority) {
                $q->where('priority', $priority->value);
            });
        }

        // 未読フィルタ
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        // 保護フィルタ
        if ($onlyProtected) {
            $query->where('is_protected', true);
        }

        // 優先度 → 作成日時の順でソート
        $query->orderByRaw("
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM mst_mailbox 
                    WHERE mst_mailbox.id = trx_mailbox.mst_mailbox_id 
                    AND mst_mailbox.priority = 'Urgent'
                ) THEN 1
                WHEN EXISTS (
                    SELECT 1 FROM mst_mailbox 
                    WHERE mst_mailbox.id = trx_mailbox.mst_mailbox_id 
                    AND mst_mailbox.priority = 'Important'
                ) THEN 2
                ELSE 3
            END
        ");
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * カテゴリごとの未読数を取得
     *
     * @param int $sysPlayerId
     * @return array<string, int>
     */
    public function countUnreadByCategory(int $sysPlayerId): array
    {
        $results = $this->modelClass::query()
            ->join('mst_mailbox', 'trx_mailbox.mst_mailbox_id', '=', 'mst_mailbox.id')
            ->where('trx_mailbox.sys_player_id', $sysPlayerId)
            ->where('trx_mailbox.is_delete', false)
            ->whereNull('trx_mailbox.read_at')
            ->where(function ($q) {
                $q->whereNull('trx_mailbox.expires_at')
                  ->orWhere('trx_mailbox.expires_at', '>', Carbon::now());
            })
            ->selectRaw('mst_mailbox.category, COUNT(*) as count')
            ->groupBy('mst_mailbox.category')
            ->get();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result->category] = $result->count;
        }

        return $counts;
    }

    /**
     * IDでメールボックスを取得
     *
     * @param int $trxMailboxId
     * @return TrxMailbox|null
     */
    public function selectById(int $trxMailboxId): ?TrxMailbox
    {
        $trxMailbox = $this->getModel($trxMailboxId);
        
        if ($trxMailbox !== null) {
            /** @var TrxMailbox */
            return $trxMailbox;
        }
        
        $trxMailbox = $this->modelClass::find($trxMailboxId);
        
        if ($trxMailbox !== null) {
            $this->setModel($trxMailbox);
        }
        
        return $trxMailbox;
    }

    /**
     * 既読にする
     *
     * @param TrxMailbox $trxMailbox
     * @return void
     */
    public function markAsOpened(TrxMailbox $trxMailbox): void
    {
        $trxMailbox->setIsOpened(true);
        if ($trxMailbox->getReadAt() === null) {
            $trxMailbox->setReadAt(Carbon::now());
        }
        $this->setModel($trxMailbox);
    }

    /**
     * 受取済みにする
     *
     * @param TrxMailbox $trxMailbox
     * @return void
     */
    public function markAsReceived(TrxMailbox $trxMailbox): void
    {
        $trxMailbox->setIsReceived(true);
        $trxMailbox->setReceivedAt(Carbon::now());
        $this->setModel($trxMailbox);
    }

    /**
     * 保護状態を切り替える
     *
     * @param TrxMailbox $trxMailbox
     * @param bool $isProtected
     * @return void
     */
    public function toggleProtection(TrxMailbox $trxMailbox, bool $isProtected): void
    {
        $trxMailbox->setIsProtected($isProtected);
        $this->setModel($trxMailbox);
    }

    /**
     * 期限切れメールを取得
     *
     * @param int $sysPlayerId
     * @return Collection
     */
    public function selectExpired(int $sysPlayerId): Collection
    {
        return $this->modelClass::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->where('is_protected', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->get();
    }
}
