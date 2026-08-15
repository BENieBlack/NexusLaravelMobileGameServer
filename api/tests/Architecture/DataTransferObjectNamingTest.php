<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: DTOの命名と配置
 *
 * ValueObjects と同様に、DTOもクラス名にサフィックスを付けず
 * ディレクトリと名前空間で役割を示す。
 */
class DataTransferObjectNamingTest extends TestCase
{
    #[Test]
    public function test_dto_classes_have_no_suffix(): void
    {
        $violations = [];

        foreach ($this->dtoFiles() as $file) {
            $className = basename($file, '.php');

            if (str_ends_with($className, 'Dto') || str_ends_with($className, 'DTO')) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "DataTransferObjects 配下のクラスにサフィックスは付けません:\n".
            implode("\n", $violations)."\n".
            'ValueObjects と同様、名前空間で役割を示してください。'
        );
    }

    #[Test]
    public function test_dto_directories_are_named_consistently(): void
    {
        $violations = [];

        foreach ($this->packageRoots() as $root) {
            foreach (['Dto', 'DTO', 'DTOs', 'Dtos'] as $legacy) {
                foreach ([$root.'/src/'.$legacy, $root.'/tests/Unit/'.$legacy] as $path) {
                    if (is_dir($path)) {
                        $violations[] = $this->relativePath($path);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "DTOのディレクトリ名は DataTransferObjects に統一します:\n".implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function dtoFiles(): array
    {
        $files = [];

        foreach ($this->packageRoots() as $root) {
            foreach ([$root.'/src/DataTransferObjects', $root.'/tests/Unit/DataTransferObjects'] as $dir) {
                if (! is_dir($dir)) {
                    continue;
                }
                foreach (glob($dir.'/*.php') ?: [] as $file) {
                    // テストクラスは対象外（XxxTest.php）
                    if (str_ends_with(basename($file, '.php'), 'Test')) {
                        continue;
                    }
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function packageRoots(): array
    {
        $base = realpath(__DIR__.'/../../../packages');

        return $base === false ? [] : (glob($base.'/*', GLOB_ONLYDIR) ?: []);
    }

    private function relativePath(string $path): string
    {
        $base = realpath(__DIR__.'/../../..');
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
