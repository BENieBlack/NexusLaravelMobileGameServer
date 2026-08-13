<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstMailbox;

/**
 * MstMailboxRepository
 *
 * メールボックスマスターデータを管理するRepository
 *
 * @extends _BaseMstRepository<MstMailbox>
 */
class MstMailboxRepository extends _BaseMstRepository
{
    protected string $modelClass = MstMailbox::class;

    /**
     * IDでメールボックスマスターを取得
     */
    public function selectById($mstMailboxId): ?MstMailbox
    {
        return $this->modelClass::find($mstMailboxId);
    }
}
