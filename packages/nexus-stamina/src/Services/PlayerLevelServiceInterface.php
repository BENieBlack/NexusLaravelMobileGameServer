<?php

namespace NexusStamina\Services;

/**
 * PlayerLevelServiceInterface
 *
 * プレイヤーレベルサービスへのアクセスを抽象化
 */
interface PlayerLevelServiceInterface
{
    /**
     * プレイヤーの最大スタミナを取得
     *
     * @return int 最大スタミナ値
     */
    public function getMaxStamina(int $sysPlayerId): int;
}
