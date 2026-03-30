<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxMailbox;
use Illuminate\Database\Eloquent\Collection;

/**
 * TrxMailboxRepository
 *
 * プレイヤーのメールボックスデータを管理するRepository
 */
class TrxMailboxRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxMailbox::class;

    /**
     * プレイヤーIDでメールボックス一覧を取得
     *
     * @param int $sysPlayerId
     * @return Collection
     */
    public function selectByPlayerId(int $sysPlayerId): Collection
    {
        // データベースから直接取得（メモリキャッシュは使用しない）
        return $this->modelClass::where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->orderBy('created_at', 'desc')
            ->get();
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
        $this->setModel($trxMailbox);
    }
}
