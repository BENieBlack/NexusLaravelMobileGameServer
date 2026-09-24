<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysDeployAsset;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysDeployAsset のテスト
 *
 * アセット配信のデプロイ記録。デプロイキーの分解、S3のURL組み立て、
 * ハッシュからのバージョン文字列生成を確認する。
 */
class SysDeployAssetTest extends TestCase
{
    /** 64文字のSHA-256ハッシュ */
    private const VALID_HASH = 'a1b2c3d4e5f6071819202122232425262728293031323334353637383940414f';

    #[Test]
    public function デプロイキーを年月日と連番に分解する(): void
    {
        $asset = $this->makeAsset(['deploy_key' => 202603152]);

        $this->assertSame(
            ['year' => 2026, 'month' => 3, 'day' => 15, 'count' => 2],
            $asset->parseDeployKey()
        );
    }

    #[Test]
    public function s3のフルurlを組み立てる(): void
    {
        $asset = $this->makeAsset(['s3_bucket' => 'nexus-assets', 's3_path' => 'v1/assets.zip']);

        $this->assertSame('s3://nexus-assets/v1/assets.zip', $asset->buildS3FullUrl());
    }

    #[Test]
    public function バケットかパスが欠けていればurlは作らない(): void
    {
        $this->assertNull($this->makeAsset(['s3_bucket' => null])->buildS3FullUrl());
        $this->assertNull($this->makeAsset(['s3_path' => null])->buildS3FullUrl());
    }

    #[Test]
    public function ステータスを判定できる(): void
    {
        $this->assertTrue($this->makeAsset(['status' => SysDeployAsset::STATUS_COMPLETED])->isCompleted());
        $this->assertTrue($this->makeAsset(['status' => SysDeployAsset::STATUS_FAILED])->isFailed());
        $this->assertTrue($this->makeAsset(['status' => SysDeployAsset::STATUS_ROLLED_BACK])->isRolledBack());

        $inProgress = $this->makeAsset(['status' => SysDeployAsset::STATUS_IN_PROGRESS]);
        $this->assertFalse($inProgress->isCompleted());
        $this->assertFalse($inProgress->isFailed());
        $this->assertFalse($inProgress->isRolledBack());
    }

    #[Test]
    public function ファイルサイズを読みやすい単位に変換する(): void
    {
        $this->assertSame('512 B', $this->makeAsset(['total_size' => 512])->formatHumanReadableSize());
        $this->assertSame('1 KB', $this->makeAsset(['total_size' => 1024])->formatHumanReadableSize());
        $this->assertSame('1.5 MB', $this->makeAsset(['total_size' => 1024 * 1024 * 1.5])->formatHumanReadableSize());
        $this->assertSame('2 GB', $this->makeAsset(['total_size' => 1024 ** 3 * 2])->formatHumanReadableSize());
    }

    #[Test]
    public function サイズが未設定なら変換しない(): void
    {
        $this->assertNull($this->makeAsset(['total_size' => null])->formatHumanReadableSize());
    }

    #[Test]
    public function ハッシュは64文字のときだけ有効(): void
    {
        $this->assertTrue($this->makeAsset(['hash' => self::VALID_HASH])->hasValidHash());
        $this->assertFalse($this->makeAsset(['hash' => 'short'])->hasValidHash());
        $this->assertFalse($this->makeAsset(['hash' => ''])->hasValidHash());
    }

    #[Test]
    public function バージョン文字列はハッシュの先頭8文字(): void
    {
        $this->assertSame('a1b2c3d4', $this->makeAsset(['hash' => self::VALID_HASH])->buildVersionString());

        // 不正なハッシュからは作らない
        $this->assertNull($this->makeAsset(['hash' => 'short'])->buildVersionString());
    }

    #[Test]
    public function レスポンスではidをsys_deploy_asset_idに置き換える(): void
    {
        $asset = $this->makeAsset();
        $asset->id = 12;

        $array = $asset->toResponseArray();

        $this->assertSame(12, $array['sys_deploy_asset_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $asset = new SysDeployAsset;
        $asset->setDeployKey(202603151);
        $asset->setHash(self::VALID_HASH);
        $asset->setDeployCount(1);
        $asset->setStatus(SysDeployAsset::STATUS_SCHEDULED);
        $asset->setS3Bucket('bucket');
        $asset->setS3Path('path/to/asset');
        $asset->setAssetVersion('1.0.0');
        $asset->setTotalSize(2048);
        $asset->setFileCount(10);
        $asset->setDeployedBy('deployer');
        $asset->setDescription('テスト配信');

        $this->assertSame(202603151, $asset->getDeployKey());
        $this->assertSame(self::VALID_HASH, $asset->getHash());
        $this->assertSame(1, $asset->getDeployCount());
        $this->assertSame(SysDeployAsset::STATUS_SCHEDULED, $asset->getStatus());
        $this->assertSame('bucket', $asset->getS3Bucket());
        $this->assertSame('path/to/asset', $asset->getS3Path());
        $this->assertSame('1.0.0', $asset->getAssetVersion());
        $this->assertSame(2048, $asset->getTotalSize());
        $this->assertSame(10, $asset->getFileCount());
        $this->assertSame('deployer', $asset->getDeployedBy());
        $this->assertSame('テスト配信', $asset->getDescription());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAsset(array $attributes = []): SysDeployAsset
    {
        return new SysDeployAsset(array_merge([
            'deploy_key' => 202601010,
            'hash' => self::VALID_HASH,
            'deploy_count' => 1,
            'status' => SysDeployAsset::STATUS_COMPLETED,
            's3_bucket' => 'nexus-assets',
            's3_path' => 'v1/assets.zip',
            'asset_version' => '1.0.0',
            'total_size' => 1024,
            'file_count' => 5,
        ], $attributes));
    }
}
