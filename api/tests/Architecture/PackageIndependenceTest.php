<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: パッケージからApplication層への逆依存
 *
 * packages/ は他タイトルへ持ち出せる汎用実装として置いている。
 * App\ を直接参照すると api/ を切り離した瞬間に壊れるため、
 * 永続化などApplication層に依存する処理はパッケージ側でインターフェースを
 * 定義し、api/app 側でAdapterを実装してコンテナでバインドする。
 *
 * 例: NexusResourceDelivery\Contracts\UnitRepositoryInterface
 *     ← App\Repositories\Trx\UnitRepositoryAdapter
 */
class PackageIndependenceTest extends TestCase
{
    #[Test]
    public function test_packages_do_not_depend_on_application_layer(): void
    {
        $violations = [];

        foreach ($this->packageSourceFiles() as $file) {
            $code = $this->stripComments((string) file_get_contents($file));

            // use App\...; と \App\... のFQCN参照の両方を拾う
            if (preg_match('/^\s*use\s+App\\\\/m', $code) || preg_match('/\\\\App\\\\[A-Z]/', $code)) {
                $violations[] = $this->relativePath($file);
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "packages/ からApp\\を参照しないでください:\n".implode("\n", $violations)."\n"
            .'パッケージ側にインターフェースを定義し、api/app にAdapterを置いてバインドしてください。'
        );
    }

    /**
     * @return list<string>
     */
    private function packageSourceFiles(): array
    {
        $root = dirname(__DIR__, 3).'/packages';

        if (! is_dir($root)) {
            $this->markTestSkipped('packages/ が見当たりません');
        }

        $files = [];

        foreach (glob($root.'/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }

    private function stripComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];

                continue;
            }
            $code .= $token;
        }

        return $code;
    }

    private function relativePath(string $path): string
    {
        $base = realpath(dirname(__DIR__, 3));
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
