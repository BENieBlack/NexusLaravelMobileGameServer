<?php

namespace NexusRewardTrack\Tests\Unit\DataTransferObjects;

use NexusRewardTrack\DataTransferObjects\RewardTrack;
use NexusRewardTrack\DataTransferObjects\RewardTrackLine;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RewardTrackのDTOのテスト
 *
 * Repositoryが行から組み立てて返す入れ物。
 * getterの名前と対応する値がずれると、受け取り履歴の突合が狂う。
 */
class RewardTrackDtoTest extends TestCase
{
    #[Test]
    public function 進捗の各値を取り出せる(): void
    {
        $progress = new RewardTrack(
            id: 1,
            sysPlayerId: 100,
            mstRewardTrackId: 'bp_001',
            currentProgress: 15,
            isDelete: false,
            createdAt: '2026-09-05 00:00:00',
            updatedAt: '2026-09-05 01:00:00',
        );

        $this->assertSame(1, $progress->getId());
        $this->assertSame(100, $progress->getSysPlayerId());
        $this->assertSame('bp_001', $progress->getMstRewardTrackId());
        $this->assertSame(15, $progress->getCurrentProgress());
        $this->assertFalse($progress->isDelete());
        $this->assertSame('2026-09-05 00:00:00', $progress->createdAt);
        $this->assertSame('2026-09-05 01:00:00', $progress->updatedAt);
    }

    #[Test]
    public function 購入済みラインの各値を取り出せる(): void
    {
        $line = new RewardTrackLine(
            id: 2,
            sysPlayerId: 100,
            mstRewardTrackLineId: 'bp_001_paid',
            mstInAppPurchaseId: 501,
            purchasedAt: '2026-09-05 00:00:00',
            isDelete: false,
            createdAt: '2026-09-05 00:00:00',
            updatedAt: null,
        );

        $this->assertSame(2, $line->getId());
        $this->assertSame(100, $line->getSysPlayerId());
        $this->assertSame('bp_001_paid', $line->getMstRewardTrackLineId());
        $this->assertSame(501, $line->getMstInAppPurchaseId());
        $this->assertSame('2026-09-05 00:00:00', $line->getPurchasedAt());
        $this->assertFalse($line->isDelete());
        $this->assertNull($line->updatedAt);
    }

    #[Test]
    public function 受け取り済みマイルストーンの各値を取り出せる(): void
    {
        $milestone = new RewardTrackMilestone(
            id: 3,
            sysPlayerId: 100,
            mstRewardTrackMilestoneId: 'bp_001_ms_10',
            mstRewardTrackLineId: 'bp_001_free',
            receivedAt: '2026-09-05 02:00:00',
            isDelete: false,
            createdAt: '2026-09-05 02:00:00',
            updatedAt: null,
        );

        $this->assertSame(3, $milestone->getId());
        $this->assertSame(100, $milestone->getSysPlayerId());
        $this->assertSame('bp_001_ms_10', $milestone->getMstRewardTrackMilestoneId());
        $this->assertSame('bp_001_free', $milestone->getMstRewardTrackLineId());
        $this->assertSame('2026-09-05 02:00:00', $milestone->getReceivedAt());
        $this->assertFalse($milestone->isDelete());
    }

    #[Test]
    public function 論理削除済みのデータも表現できる(): void
    {
        $progress = new RewardTrack(
            id: 1,
            sysPlayerId: 100,
            mstRewardTrackId: 'bp_001',
            currentProgress: 0,
            isDelete: true,
            createdAt: null,
            updatedAt: null,
        );

        $this->assertTrue($progress->isDelete());
        $this->assertNull($progress->createdAt);
    }
}
