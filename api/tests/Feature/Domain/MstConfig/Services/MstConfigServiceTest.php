<?php

namespace Tests\Feature\Domain\MstConfig\Services;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Domain\MstConfig\Services\MstConfigService;
use App\Repositories\Mst\MstConfigRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MstConfigService のテスト
 *
 * 値が使えないとき（行なし・型違い・非整数・0 以下）は、例外にせず既定値を返して警告ログを残す。
 */
class MstConfigServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const KEY = MstConfigKey::StaminaRecoveryIntervalSeconds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function 正常な行の値を整数で返し_警告を出さない(): void
    {
        Log::spy();
        $this->insertConfig('600', 'int');

        $this->assertSame(600, $this->makeService()->findInt(self::KEY));

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function 行が無ければ既定値を返し_警告を出す(): void
    {
        Log::spy();

        $this->assertSame(self::KEY->defaultInt(), $this->makeService()->findInt(self::KEY));

        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function value_typeがintでなければ既定値を返し_警告を出す(): void
    {
        Log::spy();
        // 値が数字に見えても、型の宣言が違う行は使わない
        $this->insertConfig('600', 'string');

        $this->assertSame(self::KEY->defaultInt(), $this->makeService()->findInt(self::KEY));

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            '0' => ['0'],
            '負の数' => ['-5'],
            '小数' => ['300.5'],
            '数字でない文字' => ['abc'],
            '空文字' => [''],
            '桁あふれ' => ['99999999999999999999'],
        ];
    }

    #[Test]
    #[DataProvider('invalidValueProvider')]
    public function 値が整数でない_または下限未満なら既定値を返し_警告を出す(string $value): void
    {
        Log::spy();
        $this->insertConfig($value, 'int');

        $this->assertSame(self::KEY->defaultInt(), $this->makeService()->findInt(self::KEY));

        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function 下限ちょうどの1は有効な値として返す(): void
    {
        Log::spy();
        $this->insertConfig('1', 'int');

        $this->assertSame(1, $this->makeService()->findInt(self::KEY));

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function テーブル全体をredisにキャッシュし_行を変えても作り直すまで反映されない(): void
    {
        $this->insertConfig('600', 'int');
        $this->assertSame(600, $this->makeService()->findInt(self::KEY));

        // DB を直接書き換えても、Redis のキャッシュが残る間は古い値（運営ツールの更新が最大 TTL 遅れる仕様）
        DB::connection('mst')->table('mst_config')->where('id', self::KEY->value)->update(['value' => '900']);
        $this->assertSame(600, $this->makeService()->findInt(self::KEY));

        // キャッシュを消すと新しい値になる
        app(MstConfigRepository::class)->clearCache();
        $this->assertSame(900, $this->makeService()->findInt(self::KEY));
    }

    private function makeService(): MstConfigService
    {
        // リポジトリのメモリキャッシュを持ち越さないよう、毎回作り直す（Redis は共有される）
        return new MstConfigService(app(MstConfigRepository::class));
    }

    private function insertConfig(string $value, string $valueType): void
    {
        DB::connection('mst')->table('mst_config')->insert([
            'deploy_key' => 202601010,
            'id' => self::KEY->value,
            'value' => $value,
            'value_type' => $valueType,
            'description' => 'テスト用',
        ]);
        app(MstConfigRepository::class)->clearCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_config')->delete();
        app(MstConfigRepository::class)->clearCache();
    }
}
