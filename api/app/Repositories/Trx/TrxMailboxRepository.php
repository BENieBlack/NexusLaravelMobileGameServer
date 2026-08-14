<?php

namespace App\Repositories\Trx;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Models\Trx\TrxMailbox;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Nexus\Core\Support\CustomCollection;

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
     * @param  Category|null  $category  カテゴリフィルタ
     * @param  Priority|null  $priority  優先度フィルタ
     * @param  bool  $onlyUnread  未読のみ
     * @param  bool  $onlyProtected  保護のみ
     * @return Collection
     */
    public function selectByPlayerId(
        int $sysPlayerId,
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyProtected = false
    ): CustomCollection {
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

        // 作成日時の順でソート（優先度ソートはPHP側で実施）
        $query->orderBy('created_at', 'desc');

        $results = $query->get();

        // 優先度順にソート（PHP側で実施）
        $sorted = $results->sort(function ($a, $b) {
            $priorityA = $a->mstMailbox?->priority;
            $priorityB = $b->mstMailbox?->priority;

            // オブジェクトの場合はvalue取得、文字列の場合はそのまま
            $priorityAValue = is_object($priorityA) ? $priorityA->value : (string) ($priorityA ?? 'Normal');
            $priorityBValue = is_object($priorityB) ? $priorityB->value : (string) ($priorityB ?? 'Normal');

            $priorityOrder = ['Urgent' => 1, 'Important' => 2, 'Normal' => 3];
            $orderA = $priorityOrder[$priorityAValue] ?? 3;
            $orderB = $priorityOrder[$priorityBValue] ?? 3;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            // 優先度が同じ場合は作成日時の降順
            return $b->created_at <=> $a->created_at;
        })->values();

        return new CustomCollection($sorted->all());
    }

    /**
     * カテゴリごとの未読数を取得
     *
     * @return array<string, int>
     */
    public function countUnreadByCategory(int $sysPlayerId): array
    {
        $mailboxes = $this->modelClass::query()
            ->with('mstMailbox')
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->whereNull('read_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->get();

        $counts = [];
        foreach ($mailboxes as $mailbox) {
            $category = $mailbox->mstMailbox?->category;
            if ($category !== null) {
                // Categoryオブジェクトの場合はvalue取得、文字列の場合はそのまま
                $categoryKey = is_object($category) ? $category->value : (string) $category;
                $counts[$categoryKey] = ($counts[$categoryKey] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * IDでメールボックスを取得
     */
    public function selectById(int $trxMailboxId): ?TrxMailbox
    {
        $trxMailbox = $this->findCachedModel($trxMailboxId);

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
     * 受取済みにする（Eloquent Model用）
     */
    public function markAsReceived(TrxMailbox $trxMailbox): void
    {
        $trxMailbox->setIsReceived(true);
        $trxMailbox->setReceivedAt(Carbon::now());
        $this->setModel($trxMailbox);
    }

    /**
     * 保護状態を切り替える
     */
    public function toggleProtection(TrxMailbox $trxMailbox, bool $isProtected): void
    {
        $trxMailbox->setIsProtected($isProtected);
        $this->setModel($trxMailbox);
    }

    /**
     * 期限切れメールを取得
     *
     * @return Collection
     */
    public function selectExpired(int $sysPlayerId): CustomCollection
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
