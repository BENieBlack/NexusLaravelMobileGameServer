<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysDeploy;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysDeploy のテスト
 *
 * クライアントに配信するデプロイの世代。
 * 配信開始日時を過ぎた有効な世代だけがダウンロード対象になる。
 */
class SysDeployTest extends TestCase
{
    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function デプロイキーを年月日と連番に分解する(): void
    {
        $deploy = $this->makeDeploy(['deploy_key' => 202603153]);

        $this->assertSame(
            ['year' => 2026, 'month' => 3, 'day' => 15, 'count' => 3],
            $deploy->parseDeployKey()
        );
    }

    #[Test]
    public function 有効フラグを判定できる(): void
    {
        $this->assertTrue($this->makeDeploy(['is_active' => true])->isActive());
        $this->assertFalse($this->makeDeploy(['is_active' => false])->isActive());
    }

    #[Test]
    public function 配信開始日時を過ぎていれば開始済み(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->assertTrue($this->makeDeploy(['start_at' => '2026-03-15 11:00:00'])->isStarted());
        $this->assertFalse($this->makeDeploy(['start_at' => '2026-03-15 13:00:00'])->isStarted());
    }

    #[Test]
    public function ダウンロード可能なのは有効かつ開始済みのときだけ(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->assertTrue($this->makeDeploy([
            'is_active' => true,
            'start_at' => '2026-03-15 11:00:00',
        ])->isDownloadable());

        // 開始前
        $this->assertFalse($this->makeDeploy([
            'is_active' => true,
            'start_at' => '2026-03-15 13:00:00',
        ])->isDownloadable());

        // 無効化されている
        $this->assertFalse($this->makeDeploy([
            'is_active' => false,
            'start_at' => '2026-03-15 11:00:00',
        ])->isDownloadable());
    }

    #[Test]
    public function レスポンスではidをsys_deploy_idに置き換える(): void
    {
        $deploy = $this->makeDeploy();
        $deploy->id = 9;

        $array = $deploy->toResponseArray();

        $this->assertSame(9, $array['sys_deploy_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $deploy = new SysDeploy;
        $deploy->setDeployKey(202603151);
        $deploy->setStartAt('2026-03-15 02:00:00');
        $deploy->setSysDeployMasterId(3);
        $deploy->setSysDeployAssetId(4);
        $deploy->setIsActive(true);

        $this->assertSame(202603151, $deploy->getDeployKey());
        $this->assertStringStartsWith('2026-03-15 02:00:00', $deploy->getStartAt());
        $this->assertSame(3, $deploy->getSysDeployMasterId());
        $this->assertSame(4, $deploy->getSysDeployAssetId());
        $this->assertTrue($deploy->isActive());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeDeploy(array $attributes = []): SysDeploy
    {
        return new SysDeploy(array_merge([
            'deploy_key' => 202601010,
            'start_at' => '2026-01-01 00:00:00',
            'sys_deploy_master_id' => 1,
            'sys_deploy_asset_id' => 1,
            'is_active' => true,
        ], $attributes));
    }
}
