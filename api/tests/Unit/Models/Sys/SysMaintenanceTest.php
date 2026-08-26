<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysMaintenance;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysMaintenance のテスト
 *
 * メンテナンス期間の判定と、パッケージ層へ渡すDTOへの変換を確認する。
 */
class SysMaintenanceTest extends TestCase
{
    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 有効フラグを判定できる(): void
    {
        $this->assertTrue($this->makeMaintenance(['is_active' => true])->isActive());
        $this->assertFalse($this->makeMaintenance(['is_active' => false])->isActive());
    }

    #[Test]
    public function 開始前は期間中ではない(): void
    {
        ClockUtility::setNow('2026-03-15 01:00:00');

        $maintenance = $this->makeMaintenance([
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => '2026-03-15 06:00:00',
        ]);

        $this->assertFalse($maintenance->isInProgress());
    }

    #[Test]
    public function 開始後かつ終了前なら期間中(): void
    {
        ClockUtility::setNow('2026-03-15 03:00:00');

        $maintenance = $this->makeMaintenance([
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => '2026-03-15 06:00:00',
        ]);

        $this->assertTrue($maintenance->isInProgress());
    }

    #[Test]
    public function 終了後は期間中ではない(): void
    {
        ClockUtility::setNow('2026-03-15 07:00:00');

        $maintenance = $this->makeMaintenance([
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => '2026-03-15 06:00:00',
        ]);

        $this->assertFalse($maintenance->isInProgress());
    }

    #[Test]
    public function 終了日時が未定なら開始後はずっと期間中(): void
    {
        ClockUtility::setNow('2026-04-01 00:00:00');

        $maintenance = $this->makeMaintenance([
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => null,
        ]);

        $this->assertTrue($maintenance->isInProgress());
    }

    #[Test]
    public function パッケージ層のdtoに変換できる(): void
    {
        $maintenance = $this->makeMaintenance([
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => '2026-03-15 06:00:00',
            'title' => '定期メンテナンス',
            'message' => '停止します',
        ]);

        $dto = $maintenance->toDto();

        $this->assertTrue($dto->getIsMaintenance());
        $this->assertSame('2026-03-15 02:00:00', $dto->getStartAt());
        $this->assertSame('2026-03-15 06:00:00', $dto->getEndAt());
        $this->assertSame('定期メンテナンス', $dto->getTitle());
        $this->assertSame('停止します', $dto->getMessage());
    }

    #[Test]
    public function 終了日時が未定でもdtoに変換できる(): void
    {
        $dto = $this->makeMaintenance(['end_at' => null])->toDto();

        $this->assertNull($dto->getEndAt());
    }

    #[Test]
    public function レスポンスではidをsys_maintenance_idに置き換える(): void
    {
        $maintenance = $this->makeMaintenance();
        $maintenance->id = 5;

        $array = $maintenance->toResponseArray();

        $this->assertSame(5, $array['sys_maintenance_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $maintenance = new SysMaintenance;
        $maintenance->setTitle('緊急メンテ');
        $maintenance->setMessage('調査中');
        $maintenance->setStartAt('2026-03-15 02:00:00');
        $maintenance->setEndAt('2026-03-15 06:00:00');
        $maintenance->setIsActive(true);

        $this->assertSame('緊急メンテ', $maintenance->getTitle());
        $this->assertSame('調査中', $maintenance->getMessage());
        $this->assertStringStartsWith('2026-03-15 02:00:00', $maintenance->getStartAt());
        $this->assertStringStartsWith('2026-03-15 06:00:00', $maintenance->getEndAt());
        $this->assertTrue($maintenance->isActive());
    }

    #[Test]
    public function 終了日時はnullも設定できる(): void
    {
        $maintenance = $this->makeMaintenance();
        $maintenance->setEndAt(null);

        $this->assertNull($maintenance->getEndAt());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeMaintenance(array $attributes = []): SysMaintenance
    {
        return new SysMaintenance(array_merge([
            'title' => 'メンテナンス',
            'message' => '実施中です',
            'start_at' => '2026-03-15 02:00:00',
            'end_at' => '2026-03-15 06:00:00',
            'is_active' => true,
        ], $attributes));
    }
}
