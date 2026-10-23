<?php

namespace NexusMission\Repositories;

use NexusMission\DataTransferObjects\Progress;

/**
 * ProgressRepositoryInterface
 *
 * 出来事 × 対象ごとの通算カウンタ（trx_progress）へのアクセスを抽象化する
 */
interface ProgressRepositoryInterface
{
    /**
     * プレイヤーのカウンタを全件取得する
     *
     * @return array<int, Progress>
     */
    public function selectByPlayerId(int $sysPlayerId): array;

    /**
     * カウンタを保存する（行が無ければ作り、あれば上書きする）
     */
    public function save(Progress $progress): void;
}
