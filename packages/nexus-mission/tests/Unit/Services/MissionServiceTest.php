<?php

namespace NexusMission\Tests\Unit\Services;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Utilities\ClockUtility;
use NexusMission\Contracts\MissionMasterRepositoryInterface;
use NexusMission\DataTransferObjects\Mission;
use NexusMission\DataTransferObjects\MissionContent;
use NexusMission\DataTransferObjects\MissionReceipt;
use NexusMission\DataTransferObjects\Progress;
use NexusMission\Exceptions\MissionException;
use NexusMission\Repositories\MissionReceiptRepositoryInterface;
use NexusMission\Repositories\ProgressRepositoryInterface;
use NexusMission\Services\MissionService;
use NexusMission\ValueObjects\MissionStatus;
use NexusMission\ValueObjects\MissionTrigger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MissionServiceのユニットテスト
 *
 * 進捗は「出来事 × タグ」の通算カウンタで持ち、ミッションはそれを参照する。
 * 1つの出来事でタグごとのカウンタが全部進むこと、後から足したミッションも過去の実績で
 * 達成できること、報酬の二重取りを防ぐことを固定する。
 */
class MissionServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SYS_PLAYER_ID = 100;

    private FakeMissionMasterRepository $masterRepository;

    private FakeProgressRepository $progressRepository;

    private FakeMissionReceiptRepository $receiptRepository;

    /** @var ResourceDeliveryService&MockInterface */
    private ResourceDeliveryService $deliveryService;

    private MissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-10-15 12:00:00');

        $this->masterRepository = new FakeMissionMasterRepository;
        $this->progressRepository = new FakeProgressRepository;
        $this->receiptRepository = new FakeMissionReceiptRepository;
        $this->deliveryService = Mockery::mock(ResourceDeliveryService::class);

        $this->service = new MissionService(
            $this->masterRepository,
            $this->progressRepository,
            $this->receiptRepository,
            $this->deliveryService,
        );
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // カウンタ
    // =========================================================

    #[Test]
    public function 対象が持つタグごとのカウンタと通算のカウンタを進める(): void
    {
        $updated = $this->service->record(
            self::SYS_PLAYER_ID,
            new MissionTrigger('unit_max_limit_break', ['tag_series_v_gundam', 'tag_type_gundam']),
        );

        $this->assertSame(['tag_series_v_gundam', 'tag_type_gundam', 'all'], array_map(
            fn (Progress $progress) => $progress->getSubTargetId(),
            $updated,
        ));
        $this->assertSame(1, $this->progressRepository->valueOf('unit_max_limit_break', 'tag_series_v_gundam'));
        $this->assertSame(1, $this->progressRepository->valueOf('unit_max_limit_break', 'tag_type_gundam'));
        $this->assertSame(1, $this->progressRepository->valueOf('unit_max_limit_break', 'all'));
    }

    #[Test]
    public function ミッションが無くてもカウンタは進む(): void
    {
        // どのミッションがあるかに関係なく数える（後から足したミッションのため）
        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break', ['tag_series_wing']));

        $this->assertSame(1, $this->progressRepository->valueOf('unit_max_limit_break', 'tag_series_wing'));
    }

    #[Test]
    public function 既存のカウンタに量を加算する(): void
    {
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_level_up', 'tag_type_gundam', 4));

        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_level_up', ['tag_type_gundam'], amount: 3));

        $this->assertSame(7, $this->progressRepository->valueOf('unit_level_up', 'tag_type_gundam'));
    }

    #[Test]
    public function 出来事の種類ごとに別々に数える(): void
    {
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_level_up', 'all', 10));

        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break'));

        $this->assertSame(10, $this->progressRepository->valueOf('unit_level_up', 'all'));
        $this->assertSame(1, $this->progressRepository->valueOf('unit_max_limit_break', 'all'));
    }

    #[Test]
    public function 同じタグが重なっても1回だけ数える(): void
    {
        $this->service->record(
            self::SYS_PLAYER_ID,
            new MissionTrigger('unit_level_up', ['tag_type_gundam', 'tag_type_gundam', 'all']),
        );

        $this->assertSame(1, $this->progressRepository->valueOf('unit_level_up', 'tag_type_gundam'));
        $this->assertSame(1, $this->progressRepository->valueOf('unit_level_up', 'all'));
    }

    #[Test]
    public function 進める量は1以上でなければならない(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MissionTrigger('unit_level_up', amount: 0);
    }

    // =========================================================
    // 一覧
    // =========================================================

    #[Test]
    public function 一覧はタグのカウンタを参照して期間内のミッションを表示順で返す(): void
    {
        $this->masterRepository->missions = [
            $this->makeMission('mission_b', mstTagId: null, sortOrder: 2),
            $this->makeMission('mission_a', mstTagId: 'tag_series_v_gundam', sortOrder: 1),
            $this->makeMission('mission_ended', endAt: '2026-10-14 23:59:59'),
        ];
        $this->masterRepository->contents = [
            new MissionContent('mission_a', 'item', 'item_potion_001', 5, 0),
        ];
        $this->progressRepository->save(
            new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'tag_series_v_gundam', 5)
        );
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'all', 2));

        $statuses = $this->service->findStatuses(self::SYS_PLAYER_ID);

        $this->assertSame(
            [
                [
                    'mst_mission_id' => 'mission_a',
                    'progress_value' => 3,
                    'target_value' => 3,
                    'is_completed' => true,
                    'is_received' => false,
                    'received_at' => null,
                    'end_at' => null,
                    'content_list' => [
                        ['content_type' => 'item', 'content_mst_id' => 'item_potion_001', 'content_quantity' => 5],
                    ],
                ],
                [
                    'mst_mission_id' => 'mission_b',
                    'progress_value' => 2,
                    'target_value' => 3,
                    'is_completed' => false,
                    'is_received' => false,
                    'received_at' => null,
                    'end_at' => null,
                    'content_list' => [],
                ],
            ],
            array_map(fn (MissionStatus $status) => $status->toArray(), $statuses),
            '通算は目標値で頭打ちにして見せ、タグ指定の無いミッションは all を見る',
        );
    }

    #[Test]
    public function 一覧に受け取り済みを示す(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001')];
        $this->receiptRepository->insert(new MissionReceipt(self::SYS_PLAYER_ID, 'mission_001', '2026-10-14 00:00:00'));

        $status = $this->service->findStatuses(self::SYS_PLAYER_ID)[0];

        $this->assertTrue($status->isReceived());
        $this->assertSame('2026-10-14 00:00:00', $status->toArray()['received_at']);
    }

    // =========================================================
    // 受け取り
    // =========================================================

    #[Test]
    public function 達成したミッションの報酬を配って受け取り記録を残す(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001', mstTagId: 'tag_series_v_gundam')];
        $this->masterRepository->contents = [
            new MissionContent('mission_001', 'item', 'item_potion_001', 5, 0),
            new MissionContent('mission_001', 'item', 'free_diamond', 100, 1),
        ];
        $this->progressRepository->save(
            new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'tag_series_v_gundam', 3)
        );

        $this->deliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::on(function (array $contents) {
                $actual = array_map(
                    fn (ResourceDeliveryContent $content) => [$content->getTypeValue(), $content->getId(), $content->getAmount()],
                    $contents,
                );

                return $actual === [['item', 'item_potion_001', 5], ['item', 'free_diamond', 100]];
            }));
        $this->deliveryService->shouldReceive('deliver')->once()->with(self::SYS_PLAYER_ID);

        $status = $this->service->receive(self::SYS_PLAYER_ID, 'mission_001');

        $this->assertTrue($status->isReceived());
        $this->assertSame(
            '2026-10-15 12:00:00',
            $this->receiptRepository->selectByMissionId(self::SYS_PLAYER_ID, 'mission_001')?->getReceivedAt(),
        );
        $this->assertSame(
            3,
            $this->progressRepository->valueOf('unit_max_limit_break', 'tag_series_v_gundam'),
            'カウンタは他のミッションと共有なので受け取っても減らさない',
        );
    }

    #[Test]
    public function 後から足したミッションも過去の実績で受け取れる(): void
    {
        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break', ['tag_series_v_gundam']));

        // 実績の後に公開されたミッション
        $this->masterRepository->missions = [
            $this->makeMission('mission_new', mstTagId: 'tag_series_v_gundam', targetValue: 1),
        ];

        $this->assertTrue($this->service->receive(self::SYS_PLAYER_ID, 'mission_new')->isReceived());
    }

    #[Test]
    public function 同じタグを数えるミッションはカウンタを共有する(): void
    {
        $this->masterRepository->missions = [
            $this->makeMission('mission_v_gundam_1', mstTagId: 'tag_series_v_gundam', targetValue: 1),
            $this->makeMission('mission_v_gundam_3', mstTagId: 'tag_series_v_gundam', targetValue: 3),
        ];
        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break', ['tag_series_v_gundam']));
        $this->service->receive(self::SYS_PLAYER_ID, 'mission_v_gundam_1');

        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break', ['tag_series_v_gundam']));
        $this->service->record(self::SYS_PLAYER_ID, new MissionTrigger('unit_max_limit_break', ['tag_series_v_gundam']));

        $this->assertTrue($this->service->receive(self::SYS_PLAYER_ID, 'mission_v_gundam_3')->isReceived());
    }

    #[Test]
    public function 報酬の無いミッションも受け取り記録を残せる(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001', mstTagId: null)];
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'all', 3));

        $this->deliveryService->shouldNotReceive('deliver');

        $this->assertTrue($this->service->receive(self::SYS_PLAYER_ID, 'mission_001')->isReceived());
    }

    #[Test]
    public function 未達成のミッションは受け取れない(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001', mstTagId: 'tag_series_v_gundam')];
        $this->progressRepository->save(
            new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'tag_series_v_gundam', 2)
        );

        $this->assertReceiveFails('mission_001', 'MISSION_NOT_COMPLETED');
    }

    #[Test]
    public function 別のタグのカウンタでは達成にならない(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001', mstTagId: 'tag_series_v_gundam')];
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'tag_type_gundam', 3));
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_level_up', 'tag_series_v_gundam', 3));

        $this->assertReceiveFails('mission_001', 'MISSION_NOT_COMPLETED');
    }

    #[Test]
    public function 受け取り済みのミッションは二度受け取れない(): void
    {
        $this->masterRepository->missions = [$this->makeMission('mission_001', mstTagId: null)];
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'all', 3));
        $this->receiptRepository->insert(new MissionReceipt(self::SYS_PLAYER_ID, 'mission_001', '2026-10-14 00:00:00'));

        $this->assertReceiveFails('mission_001', 'MISSION_ALREADY_RECEIVED');
    }

    #[Test]
    public function 存在しないミッションは受け取れない(): void
    {
        $this->assertReceiveFails('mission_none', 'MISSION_NOT_FOUND');
    }

    #[Test]
    public function 期間外のミッションは受け取れない(): void
    {
        $this->masterRepository->missions = [
            $this->makeMission('mission_001', mstTagId: null, endAt: '2026-10-14 23:59:59'),
        ];
        $this->progressRepository->save(new Progress(self::SYS_PLAYER_ID, 'unit_max_limit_break', 'all', 3));

        $this->assertReceiveFails('mission_001', 'MISSION_NOT_OPEN');
    }

    #[Test]
    public function 例外は種類を示すエラーコードを持つ(): void
    {
        // アプリケーション層がこのコードを見てクライアント向けの番号へ翻訳する。
        // 文字列が変わると翻訳表から漏れて汎用エラーに落ちる
        $this->assertSame('MISSION_NOT_FOUND', MissionException::missionNotFound('m')->getErrorCode());
        $this->assertSame('MISSION_NOT_OPEN', MissionException::missionNotOpen('m')->getErrorCode());
        $this->assertSame('MISSION_NOT_COMPLETED', MissionException::notCompleted('m', 3, 2)->getErrorCode());
        $this->assertSame('MISSION_ALREADY_RECEIVED', MissionException::alreadyReceived('m')->getErrorCode());
    }

    private function assertReceiveFails(string $mstMissionId, string $errorCode): void
    {
        $this->deliveryService->shouldNotReceive('deliver');

        try {
            $this->service->receive(self::SYS_PLAYER_ID, $mstMissionId);
            $this->fail('MissionException が投げられるはず');
        } catch (MissionException $e) {
            $this->assertSame($errorCode, $e->getErrorCode());
        }

        $this->assertNotSame(
            '2026-10-15 12:00:00',
            $this->receiptRepository->selectByMissionId(self::SYS_PLAYER_ID, $mstMissionId)?->getReceivedAt(),
            '失敗したときは受け取り記録を残さない',
        );
    }

    private function makeMission(
        string $id,
        ?string $mstTagId = 'tag_series_v_gundam',
        int $targetValue = 3,
        string $startAt = '2026-10-01 00:00:00',
        ?string $endAt = null,
        int $sortOrder = 0,
    ): Mission {
        return new Mission(
            id: $id,
            triggerType: 'unit_max_limit_break',
            mstTagId: $mstTagId,
            targetValue: $targetValue,
            startAt: $startAt,
            endAt: $endAt,
            sortOrder: $sortOrder,
        );
    }
}

/**
 * テスト用のマスターRepository
 */
class FakeMissionMasterRepository implements MissionMasterRepositoryInterface
{
    /** @var array<int, Mission> */
    public array $missions = [];

    /** @var array<int, MissionContent> */
    public array $contents = [];

    public function selectAllMissions(): array
    {
        return $this->missions;
    }

    public function selectMissionById(string $mstMissionId): ?Mission
    {
        foreach ($this->missions as $mission) {
            if ($mission->getId() === $mstMissionId) {
                return $mission;
            }
        }

        return null;
    }

    public function selectContentsByMissionId(string $mstMissionId): array
    {
        return array_values(array_filter(
            $this->contents,
            fn (MissionContent $content) => $content->getMstMissionId() === $mstMissionId,
        ));
    }
}

/**
 * テスト用のカウンタRepository（1プレイヤー分をメモリに持つ）
 */
class FakeProgressRepository implements ProgressRepositoryInterface
{
    /** @var array<string, Progress> */
    private array $progresses = [];

    public function selectByPlayerId(int $sysPlayerId): array
    {
        return array_values($this->progresses);
    }

    public function save(Progress $progress): void
    {
        $this->progresses[$progress->getTriggerType().':'.$progress->getSubTargetId()] = $progress;
    }

    public function valueOf(string $triggerType, string $subTargetId): ?int
    {
        return ($this->progresses[$triggerType.':'.$subTargetId] ?? null)?->getProgressValue();
    }
}

/**
 * テスト用の受け取り記録Repository（1プレイヤー分をメモリに持つ）
 */
class FakeMissionReceiptRepository implements MissionReceiptRepositoryInterface
{
    /** @var array<string, MissionReceipt> */
    private array $missionReceipts = [];

    public function selectByPlayerId(int $sysPlayerId): array
    {
        return array_values($this->missionReceipts);
    }

    public function selectByMissionId(int $sysPlayerId, string $mstMissionId): ?MissionReceipt
    {
        return $this->missionReceipts[$mstMissionId] ?? null;
    }

    public function insert(MissionReceipt $missionReceipt): void
    {
        $this->missionReceipts[$missionReceipt->getMstMissionId()] = $missionReceipt;
    }
}
