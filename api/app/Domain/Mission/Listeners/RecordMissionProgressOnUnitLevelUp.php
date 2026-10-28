<?php

namespace App\Domain\Mission\Listeners;

use App\Domain\Mission\Constants\MissionTriggerType;
use NexusMission\Services\MissionService;
use NexusMission\ValueObjects\MissionTrigger;
use NexusResource\Enums\ResourceType;
use NexusResource\Events\UnitLeveledUp;
use NexusTag\Services\TagService;

/**
 * RecordMissionProgressOnUnitLevelUp
 *
 * ユニットのレベルアップを、ミッションの進捗（unit_level_up）として数える。
 *
 * ユニットが持つタグごとのカウンタと、タグを問わない通算を進める。
 * 1回で複数レベル上がったときは、上がったレベルの数だけ数える。
 *
 * レベルアップと同じUnitOfWorkに書き込むため、同期で受ける（ShouldQueueにしない）。
 */
class RecordMissionProgressOnUnitLevelUp
{
    public function __construct(
        private readonly MissionService $missionService,
        private readonly TagService $tagService,
    ) {}

    public function handle(UnitLeveledUp $event): void
    {
        $leveledUpCount = $event->afterLevel - $event->beforeLevel;
        if ($leveledUpCount <= 0) {
            return;
        }

        $this->missionService->record($event->sysPlayerId, new MissionTrigger(
            triggerType: MissionTriggerType::UNIT_LEVEL_UP,
            mstTagIds: $this->tagService->findTagIds(ResourceType::UNIT->value, $event->mstUnitId),
            amount: $leveledUpCount,
        ));
    }
}
