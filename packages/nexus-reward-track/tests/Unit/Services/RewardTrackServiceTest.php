<?php

namespace NexusRewardTrack\Tests\Unit\Services;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;
use NexusRewardTrack\DataTransferObjects\RewardTrack;
use NexusRewardTrack\DataTransferObjects\RewardTrackLine;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;
use NexusRewardTrack\Repositories\RewardTrackLineRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackMilestoneRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackRepositoryInterface;
use NexusRewardTrack\Services\RewardTrackService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * RewardTrackServiceのユニットテスト
 *
 * 報酬の二重取り・未購入ラインの受け取り・期間外の進捗加算を
 * 落とせているかを重点的に固定する。
 */
class RewardTrackServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONNECTION = 'trx1';

    private RewardTrackMasterRepositoryInterface $masterRepository;

    private RewardTrackRepositoryInterface $progressRepository;

    private RewardTrackLineRepositoryInterface $lineRepository;

    private RewardTrackMilestoneRepositoryInterface $milestoneRepository;

    private ResourceDeliveryService $deliveryService;

    private RewardTrackService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masterRepository = Mockery::mock(RewardTrackMasterRepositoryInterface::class);
        $this->progressRepository = Mockery::mock(RewardTrackRepositoryInterface::class);
        $this->lineRepository = Mockery::mock(RewardTrackLineRepositoryInterface::class);
        $this->milestoneRepository = Mockery::mock(RewardTrackMilestoneRepositoryInterface::class);
        $this->deliveryService = Mockery::mock(ResourceDeliveryService::class);

        $this->service = new RewardTrackService(
            $this->masterRepository,
            $this->progressRepository,
            $this->lineRepository,
            $this->milestoneRepository,
            $this->deliveryService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // 進捗
    // =========================================================

    #[Test]
    public function 開催中のトラックには進捗を加算できる(): void
    {
        $progress = $this->makeProgress(currentProgress: 15);

        $this->masterRepository->shouldReceive('selectTrackById')->with('bp_001')->andReturn($this->makeTrack());
        $this->progressRepository->shouldReceive('addProgress')
            ->with(100, 'bp_001', 5, self::CONNECTION)
            ->andReturn($progress);

        $this->assertSame($progress, $this->service->addProgress(100, 'bp_001', 5, self::CONNECTION));
    }

    #[Test]
    public function 存在しないトラックには進捗を加算できない(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RewardTrack が見つかりません: bp_001');

        $this->service->addProgress(100, 'bp_001', 5, self::CONNECTION);
    }

    #[Test]
    public function 開始前のトラックには進捗を加算できない(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')
            ->andReturn($this->makeTrack(startAt: '2099-01-01 00:00:00'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('トラックはまだ開始していません');

        $this->service->addProgress(100, 'bp_001', 5, self::CONNECTION);
    }

    #[Test]
    public function 終了したトラックには進捗を加算できない(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')
            ->andReturn($this->makeTrack(endAt: '2020-01-01 00:00:00'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('トラックは終了しています');

        $this->service->addProgress(100, 'bp_001', 5, self::CONNECTION);
    }

    #[Test]
    public function 終了日が無いトラックは終了扱いにしない(): void
    {
        // 常設トラックは end_at が null になる
        $progress = $this->makeProgress();
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn($this->makeTrack(endAt: null));
        $this->progressRepository->shouldReceive('addProgress')->andReturn($progress);

        $this->assertSame($progress, $this->service->addProgress(100, 'bp_001', 5, self::CONNECTION));
    }

    #[Test]
    public function 進捗を直接設定できる(): void
    {
        // プレイヤーレベル連動のトラックは加算ではなく値をそのまま入れる
        $progress = $this->makeProgress(currentProgress: 30);

        $this->masterRepository->shouldReceive('selectTrackById')->andReturn($this->makeTrack());
        $this->progressRepository->shouldReceive('upsertProgress')
            ->with(100, 'bp_001', 30, self::CONNECTION)
            ->andReturn($progress);

        $this->assertSame($progress, $this->service->setProgress(100, 'bp_001', 30, self::CONNECTION));
    }

    #[Test]
    public function 存在しないトラックには進捗を設定できない(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn(null);

        $this->expectException(RuntimeException::class);

        $this->service->setProgress(100, 'bp_001', 30, self::CONNECTION);
    }

    // =========================================================
    // ライン購入
    // =========================================================

    #[Test]
    public function 有料ラインを付与できる(): void
    {
        $line = $this->makeLine();

        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->with('bp_001')->andReturn($this->lines());
        $this->lineRepository->shouldReceive('hasLine')->with(100, 'bp_001_paid', self::CONNECTION)->andReturn(false);
        $this->lineRepository->shouldReceive('insertLine')
            ->withArgs(fn (int $playerId, string $lineId, int $purchaseId, string $purchasedAt, string $connection): bool => $playerId === 100
                    && $lineId === 'bp_001_paid'
                    && $purchaseId === 501
                    && $connection === self::CONNECTION)
            ->andReturn($line);

        $this->assertSame($line, $this->service->grantLine(100, 'bp_001_paid', 501, self::CONNECTION));
    }

    #[Test]
    public function 無料ラインは購入できない(): void
    {
        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('無料ラインは購入できません');

        $this->service->grantLine(100, 'bp_001_free', 501, self::CONNECTION);
    }

    #[Test]
    public function 購入済みのラインは二重に付与できない(): void
    {
        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());
        $this->lineRepository->shouldReceive('hasLine')->andReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('既に購入済みのラインです');

        $this->service->grantLine(100, 'bp_001_paid', 501, self::CONNECTION);
    }

    #[Test]
    public function どのトラックにも属さないラインは付与できない(): void
    {
        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ラインIDからトラックIDを解決できませんでした');

        $this->service->grantLine(100, 'unknown_line', 501, self::CONNECTION);
    }

    // =========================================================
    // サマリー
    // =========================================================

    #[Test]
    public function サマリーには無料ラインが常に所持として入る(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')->with('bp_001')->andReturn($this->makeTrack());
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());
        $this->masterRepository->shouldReceive('selectMilestonesByTrackId')->andReturn($this->milestones());
        $this->masterRepository->shouldReceive('selectContentsByMilestoneIds')->andReturn($this->contents());
        $this->masterRepository->shouldReceive('selectFreeLineId')->with('bp_001')->andReturn('bp_001_free');
        $this->progressRepository->shouldReceive('findByPlayerAndTrack')->andReturn($this->makeProgress(currentProgress: 10));
        $this->lineRepository->shouldReceive('findOwnedLineIds')->andReturn([]);
        $this->milestoneRepository->shouldReceive('findReceivedKeySet')->andReturn([]);

        $summary = $this->service->getSummary(100, 'bp_001', self::CONNECTION);

        $this->assertSame(['bp_001_free'], $summary['owned_line_ids']);
        $this->assertSame(10, $summary['current_progress']);
    }

    #[Test]
    public function サマリーのマイルストーンにはライン別の報酬がぶら下がる(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn($this->makeTrack());
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());
        $this->masterRepository->shouldReceive('selectMilestonesByTrackId')->andReturn($this->milestones());
        $this->masterRepository->shouldReceive('selectContentsByMilestoneIds')->andReturn($this->contents());
        $this->masterRepository->shouldReceive('selectFreeLineId')->andReturn('bp_001_free');
        $this->progressRepository->shouldReceive('findByPlayerAndTrack')->andReturn(null);
        $this->lineRepository->shouldReceive('findOwnedLineIds')->andReturn(['bp_001_paid']);
        $this->milestoneRepository->shouldReceive('findReceivedKeySet')->andReturn(['bp_001_ms_10:bp_001_free' => true]);

        $summary = $this->service->getSummary(100, 'bp_001', self::CONNECTION);

        // 進捗行が無いプレイヤーは0から始まる
        $this->assertSame(0, $summary['current_progress']);
        $this->assertArrayHasKey('bp_001_free', $summary['milestones'][0]['contents']);
        $this->assertArrayHasKey('bp_001_paid', $summary['milestones'][0]['contents']);
        $this->assertSame(['bp_001_paid', 'bp_001_free'], $summary['owned_line_ids']);
    }

    #[Test]
    public function 存在しないトラックのサマリーは取得できない(): void
    {
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn(null);

        $this->expectException(RuntimeException::class);

        $this->service->getSummary(100, 'bp_001', self::CONNECTION);
    }

    // =========================================================
    // 報酬受け取り
    // =========================================================

    #[Test]
    public function 条件を満たせば報酬を受け取れる(): void
    {
        $receipt = $this->makeReceipt();
        $this->stubReceiveMilestone(currentProgress: 10, ownsPaidLine: true, alreadyReceived: false);

        $this->deliveryService->shouldReceive('addContents')->once();
        $this->deliveryService->shouldReceive('deliver')->with(100)->once();
        $this->milestoneRepository->shouldReceive('insertReceipt')
            ->withArgs(fn (int $playerId, string $milestoneId, string $lineId, string $receivedAt, string $connection): bool => $playerId === 100 && $milestoneId === 'bp_001_ms_10' && $lineId === 'bp_001_paid')
            ->andReturn($receipt);

        $result = $this->service->receiveMilestone(100, 'bp_001_ms_10', 'bp_001_paid', self::CONNECTION);

        $this->assertSame($receipt, $result);
    }

    #[Test]
    public function 無料ラインは購入していなくても受け取れる(): void
    {
        $receipt = $this->makeReceipt();
        $this->stubReceiveMilestone(currentProgress: 10, ownsPaidLine: false, alreadyReceived: false);

        $this->deliveryService->shouldReceive('addContents')->once();
        $this->deliveryService->shouldReceive('deliver')->once();
        $this->milestoneRepository->shouldReceive('insertReceipt')->andReturn($receipt);

        $this->assertSame($receipt, $this->service->receiveMilestone(100, 'bp_001_ms_10', 'bp_001_free', self::CONNECTION));
    }

    #[Test]
    public function 進捗が足りなければ受け取れない(): void
    {
        $this->stubReceiveMilestone(currentProgress: 9, ownsPaidLine: true, alreadyReceived: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('進捗が不足しています。必要: 10, 現在: 9');

        $this->service->receiveMilestone(100, 'bp_001_ms_10', 'bp_001_paid', self::CONNECTION);
    }

    #[Test]
    public function 購入していないラインの報酬は受け取れない(): void
    {
        $this->stubReceiveMilestone(currentProgress: 10, ownsPaidLine: false, alreadyReceived: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('このラインは購入していません');

        $this->service->receiveMilestone(100, 'bp_001_ms_10', 'bp_001_paid', self::CONNECTION);
    }

    #[Test]
    public function 受け取り済みの報酬は二重に受け取れない(): void
    {
        $this->stubReceiveMilestone(currentProgress: 10, ownsPaidLine: true, alreadyReceived: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('既に受け取り済みです');

        $this->service->receiveMilestone(100, 'bp_001_ms_10', 'bp_001_paid', self::CONNECTION);
    }

    #[Test]
    public function 存在しないマイルストーンは受け取れない(): void
    {
        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectMilestonesByTrackId')->andReturn($this->milestones());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('マイルストーンIDからトラックIDを解決できませんでした');

        $this->service->receiveMilestone(100, 'unknown_ms', 'bp_001_paid', self::CONNECTION);
    }

    // =========================================================
    // ヘルパ
    // =========================================================

    /**
     * receiveMilestone が通る手前までのモックをまとめて積む
     */
    private function stubReceiveMilestone(int $currentProgress, bool $ownsPaidLine, bool $alreadyReceived): void
    {
        $this->masterRepository->shouldReceive('selectActiveTracks')->andReturn([$this->makeTrack()]);
        $this->masterRepository->shouldReceive('selectMilestonesByTrackId')->andReturn($this->milestones());
        $this->masterRepository->shouldReceive('selectTrackById')->andReturn($this->makeTrack());
        $this->masterRepository->shouldReceive('selectLinesByTrackId')->andReturn($this->lines());
        $this->masterRepository->shouldReceive('selectFreeLineId')->andReturn('bp_001_free');
        $this->masterRepository->shouldReceive('selectContentsByMilestoneIds')->andReturn($this->contents());
        $this->progressRepository->shouldReceive('findByPlayerAndTrack')
            ->andReturn($this->makeProgress(currentProgress: $currentProgress));
        $this->lineRepository->shouldReceive('hasLine')->andReturn($ownsPaidLine);
        $this->milestoneRepository->shouldReceive('hasReceived')->andReturn($alreadyReceived);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTrack(string $startAt = '2020-01-01 00:00:00', ?string $endAt = '2099-12-31 23:59:59'): array
    {
        return [
            'id' => 'bp_001',
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lines(): array
    {
        return [
            ['id' => 'bp_001_free', 'is_free' => true],
            ['id' => 'bp_001_paid', 'is_free' => false],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function milestones(): array
    {
        return [
            ['id' => 'bp_001_ms_10', 'required_progress' => 10],
            ['id' => 'bp_001_ms_20', 'required_progress' => 20],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contents(): array
    {
        return [
            [
                'mst_reward_track_milestone_id' => 'bp_001_ms_10',
                'mst_reward_track_line_id' => 'bp_001_free',
                'content_type' => 'item',
                'content_mst_id' => 'item_heal_001',
                'content_quantity' => 2,
                'amount' => 1,
                'content_option' => [],
            ],
            [
                'mst_reward_track_milestone_id' => 'bp_001_ms_10',
                'mst_reward_track_line_id' => 'bp_001_paid',
                'content_type' => 'item',
                'content_mst_id' => 'item_heal_002',
                'content_quantity' => 3,
                'amount' => 2,
                'content_option' => [],
            ],
        ];
    }

    private function makeProgress(int $currentProgress = 0): RewardTrack
    {
        return new RewardTrack(
            id: 1,
            sysPlayerId: 100,
            mstRewardTrackId: 'bp_001',
            currentProgress: $currentProgress,
            isDelete: false,
            createdAt: '2026-09-05 00:00:00',
            updatedAt: '2026-09-05 00:00:00',
        );
    }

    private function makeLine(): RewardTrackLine
    {
        return new RewardTrackLine(
            id: 1,
            sysPlayerId: 100,
            mstRewardTrackLineId: 'bp_001_paid',
            mstInAppPurchaseId: 501,
            purchasedAt: '2026-09-05 00:00:00',
            isDelete: false,
            createdAt: '2026-09-05 00:00:00',
            updatedAt: '2026-09-05 00:00:00',
        );
    }

    private function makeReceipt(): RewardTrackMilestone
    {
        return new RewardTrackMilestone(
            id: 1,
            sysPlayerId: 100,
            mstRewardTrackMilestoneId: 'bp_001_ms_10',
            mstRewardTrackLineId: 'bp_001_paid',
            receivedAt: '2026-09-05 00:00:00',
            isDelete: false,
            createdAt: '2026-09-05 00:00:00',
            updatedAt: '2026-09-05 00:00:00',
        );
    }
}
