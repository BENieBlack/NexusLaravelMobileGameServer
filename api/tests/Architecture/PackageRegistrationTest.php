<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: パッケージの登録漏れ
 *
 * packages/ にパッケージを足したら、api の次の3か所に手で登録する。
 * どれかが漏れると、そのパッケージだけ読み込まれない・静的解析されない・カバレッジに入らない。
 * 実際に nexus-agreement が PHPStan とカバレッジから漏れていた（2026-11-03 の評価 N4）。
 *
 * - api/composer.json  … path リポジトリと require
 * - api/phpstan.neon   … paths（静的解析の対象）
 * - api/phpunit.xml    … source（カバレッジの対象）
 */
class PackageRegistrationTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function packageProvider(): array
    {
        $packages = [];

        foreach (glob(self::packagesRoot().'/*/composer.json') ?: [] as $composerJson) {
            $name = basename(dirname($composerJson));
            $packages[$name] = [$name];
        }

        return $packages;
    }

    #[Test]
    #[DataProvider('packageProvider')]
    public function パッケージはcomposerに登録されている(string $package): void
    {
        $composer = $this->readJson(self::apiRoot().'/composer.json');
        $packageName = $this->readJson(self::packagesRoot()."/{$package}/composer.json")['name'];

        $repositoryUrls = array_column($composer['repositories'] ?? [], 'url');
        $this->assertContains(
            "../packages/{$package}",
            $repositoryUrls,
            "api/composer.json の repositories に ../packages/{$package} を path で足してください",
        );
        $this->assertArrayHasKey(
            $packageName,
            $composer['require'],
            "api/composer.json の require に \"{$packageName}\": \"@dev\" を足してください",
        );
    }

    #[Test]
    #[DataProvider('packageProvider')]
    public function パッケージは静的解析の対象に入っている(string $package): void
    {
        if (! is_dir(self::packagesRoot()."/{$package}/src")) {
            $this->markTestSkipped("{$package} は src を持たない");
        }

        $this->assertStringContainsString(
            "../packages/{$package}/src",
            (string) file_get_contents(self::apiRoot().'/phpstan.neon'),
            "api/phpstan.neon の paths に ../packages/{$package}/src を足してください",
        );
    }

    #[Test]
    #[DataProvider('packageProvider')]
    public function パッケージはカバレッジの対象に入っている(string $package): void
    {
        if (! is_dir(self::packagesRoot()."/{$package}/src")) {
            $this->markTestSkipped("{$package} は src を持たない");
        }

        $this->assertStringContainsString(
            "<directory>../packages/{$package}/src</directory>",
            (string) file_get_contents(self::apiRoot().'/phpunit.xml'),
            "api/phpunit.xml の source に ../packages/{$package}/src を足してください",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private static function apiRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function packagesRoot(): string
    {
        return dirname(__DIR__, 3).'/packages';
    }
}
