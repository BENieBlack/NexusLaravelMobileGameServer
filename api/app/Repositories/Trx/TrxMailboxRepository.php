<?php

namespace App\Repositories\Trx;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Models\Mst\MstMailbox;
use App\Models\Trx\TrxMailbox;
use Carbon\Carbon;
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
     * @return CustomCollection<int, TrxMailbox>
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

        // カテゴリ・優先度はマスター側の値。
        // mst_mailbox は別のDB接続にあるため、whereHas ではJOINできない。
        // 先にマスターから該当するIDを引いて whereIn で絞る。
        if ($category !== null || $priority !== null) {
            $query->whereIn('mst_mailbox_id', $this->findMstMailboxIds($category, $priority));
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
        // priorityはMstMailbox側でPriorityにキャスト済みなので、Enumのまま扱う
        $priorityOrder = [
            Priority::URGENT->value => 1,
            Priority::IMPORTANT->value => 2,
            Priority::NORMAL->value => 3,
        ];

        $sorted = $results->sort(function ($a, $b) use ($priorityOrder) {
            // マスターが引けないメールは通常扱いにする
            $priorityAValue = ($a->mstMailbox->priority ?? Priority::NORMAL)->value;
            $priorityBValue = ($b->mstMailbox->priority ?? Priority::NORMAL)->value;

            $orderA = $priorityOrder[$priorityAValue];
            $orderB = $priorityOrder[$priorityBValue];

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
            // categoryはMstMailbox側でCategoryにキャスト済み
            $category = $mailbox->mstMailbox?->category;
            if ($category !== null) {
                $counts[$category->value] = ($counts[$category->value] ?? 0) + 1;
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
     * @return CustomCollection<int, TrxMailbox>
     */
    public function selectExpired(int $sysPlayerId): CustomCollection
    {
        $records = $this->modelClass::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->where('is_protected', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->get();

        return new CustomCollection($records->all());
    }

    /**
     * カテゴリ・優先度に一致するメールボックスマスターのIDを返す
     *
     * mst_mailbox は mst 接続にあり、trx 側のクエリから直接JOINできない。
     *
     * @return list<string>
     */
    private function findMstMailboxIds(?Category $category, ?Priority $priority): array
    {
        $query = MstMailbox::query();

        if ($category !== null) {
            $query->where('category', $category->value);
        }

        if ($priority !== null) {
            $query->where('priority', $priority->value);
        }

        return $query->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
