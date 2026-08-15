<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Model→DTO変換の置き場所
 *
 * 変換ロジックは App\Adapters 配下に集約する。
 * RepositoryAdapter の内部に private 変換メソッドを書くと、
 * UseCase など他の層から同じ変換を使えず重複の原因になる。
 */
class ConverterLocationTest extends TestCase
{
    #[Test]
    public function test_repository_adapters_delegate_conversion(): void
    {
        $violations = [];

        foreach ($this->repositoryAdapters() as $file) {
            $content = $this->stripComments(file_get_contents($file));

            if (preg_match_all('/(?:private|protected) function (\w*(?:[Tt]oDto|Convert\w*)\w*)\s*\(/', $content, $matches)) {
                foreach ($matches[1] as $method) {
                    $violations[] = $this->relativePath($file)."::{$method}()";
                }
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "Model→DTO変換は App\\Adapters に置いてください:\n".
            implode("\n", $violations)."\n".
            'RepositoryAdapter は変換を委譲するだけにします。'
        );
    }

    #[Test]
    public function test_adapters_directory_holds_converters(): void
    {
        $base = realpath(__DIR__.'/../../app/Adapters');

        $this->assertNotFalse($base, 'app/Adapters が存在しません');

        $missing = [];

        foreach (glob($base.'/*/*.php') ?: [] as $file) {
            $content = file_get_contents($file);

            if (! str_contains($content, 'public static function toDto(')) {
                $missing[] = $this->relativePath($file);
            }
        }

        $this->assertSame(
            [],
            $missing,
            "App\\Adapters 配下のクラスは toDto() を持つ変換クラスにしてください:\n".implode("\n", $missing)
        );
    }

    /**
     * @return list<string>
     */
    private function repositoryAdapters(): array
    {
        return glob(__DIR__.'/../../app/Repositories/*/*RepositoryAdapter.php') ?: [];
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
        $base = realpath(__DIR__.'/../../..');
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
