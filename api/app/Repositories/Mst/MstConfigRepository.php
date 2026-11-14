<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstConfig;

/**
 * MstConfigRepository
 *
 * 設定マスターの読み取り。テーブル全体を Redis に 1 キー（mst:mst_config:all）で持つ。
 *
 * @extends _BaseMstRepository<MstConfig>
 */
class MstConfigRepository extends _BaseMstRepository
{
    protected string $modelClass = MstConfig::class;
}
