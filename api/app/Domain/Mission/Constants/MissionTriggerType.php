<?php

namespace App\Domain\Mission\Constants;

/**
 * ミッションの進捗を数える出来事の種類
 *
 * mst_mission.trigger_type と trx_progress.trigger_type に入る値。
 * 出来事を数える受け手（Listeners）を足したら、ここにも値を足す。
 */
class MissionTriggerType
{
    /**
     * ユニットのレベルアップ（1レベル上がるごとに1）
     */
    const UNIT_LEVEL_UP = 'unit_level_up';

    /**
     * ユニットの最大限界突破（完凸）
     *
     * 限界突破の機能がまだ無いため、数える受け手は無い。機能を作るときに発行する
     */
    const UNIT_MAX_LIMIT_BREAK = 'unit_max_limit_break';

    /**
     * 全種類の配列を取得
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::UNIT_LEVEL_UP,
            self::UNIT_MAX_LIMIT_BREAK,
        ];
    }
}
