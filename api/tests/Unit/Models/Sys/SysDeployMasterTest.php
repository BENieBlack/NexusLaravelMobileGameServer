<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysDeployMaster;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysDeployMaster のテスト
 *
 * マスターデータのデプロイ記録。デプロイキーの分解と、
 * ハッシュからのバージョン文字列生成を確認する。
 */
class SysDeployMasterTest extends TestCase
{
    /** 64文字のSHA-256ハッシュ */
    private const VALID_HASH = 'b1c2d3e4f5061718192021222324252627282930313233343536373839404142';

    #[Test]
    public function デプロイキーを年月日と連番に分解する(): void
    {
        $master = $this->makeMaster(['deploy_key' => 202612319]);

        $this->assertSame(
            ['year' => 2026, 'month' => 12, 'day' => 31, 'count' => 9],
            $master->parseDeployKey()
        );
    }

    #[Test]
    public function ステータスを判定できる(): void
    {
        $this->assertTrue($this->makeMaster(['status' => SysDeployMaster::STATUS_COMPLETED])->isCompleted());
        $this->assertTrue($this->makeMaster(['status' => SysDeployMaster::STATUS_FAILED])->isFailed());
        $this->assertTrue($this->makeMaster(['status' => SysDeployMaster::STATUS_ROLLED_BACK])->isRolledBack());

        $scheduled = $this->makeMaster(['status' => SysDeployMaster::STATUS_SCHEDULED]);
        $this->assertFalse($scheduled->isCompleted());
        $this->assertFalse($scheduled->isFailed());
        $this->assertFalse($scheduled->isRolledBack());
    }

    #[Test]
    public function ハッシュは64文字のときだけ有効(): void
    {
        $this->assertTrue($this->makeMaster(['hash' => self::VALID_HASH])->hasValidHash());
        $this->assertFalse($this->makeMaster(['hash' => 'too-short'])->hasValidHash());
    }

    #[Test]
    public function バージョン文字列はハッシュの先頭8文字(): void
    {
        $this->assertSame('b1c2d3e4', $this->makeMaster(['hash' => self::VALID_HASH])->buildVersionString());
        $this->assertNull($this->makeMaster(['hash' => 'too-short'])->buildVersionString());
    }

    #[Test]
    public function レスポンスではidをsys_deploy_master_idに置き換える(): void
    {
        $master = $this->makeMaster();
        $master->id = 7;

        $array = $master->toResponseArray();

        $this->assertSame(7, $array['sys_deploy_master_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $master = new SysDeployMaster;
        $master->setDeployKey(202603151);
        $master->setHash(self::VALID_HASH);
        $master->setDeployCount(2);
        $master->setStatus(SysDeployMaster::STATUS_IN_PROGRESS);
        $master->setDeployedBy('deployer');
        $master->setDescription('マスタ更新');

        $this->assertSame(202603151, $master->getDeployKey());
        $this->assertSame(self::VALID_HASH, $master->getHash());
        $this->assertSame(2, $master->getDeployCount());
        $this->assertSame(SysDeployMaster::STATUS_IN_PROGRESS, $master->getStatus());
        $this->assertSame('deployer', $master->getDeployedBy());
        $this->assertSame('マスタ更新', $master->getDescription());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeMaster(array $attributes = []): SysDeployMaster
    {
        return new SysDeployMaster(array_merge([
            'deploy_key' => 202601010,
            'hash' => self::VALID_HASH,
            'deploy_count' => 1,
            'status' => SysDeployMaster::STATUS_COMPLETED,
        ], $attributes));
    }
}
