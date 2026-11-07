<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Trx モデルをドメイン層から直接読まない
 *
 * 書き込みは Repository のキューに積み、コミット時にDBへ書く。
 * Domain / Http から `TrxXxx::query()` でDBを直接読むと、同じ処理の中で前に加算・消費した結果が見えず、
 * 古い値から計算して前の更新が消える。読み取りは Repository の select*（メモリ優先）を通す。
 */
class NoDirectTrxReadInDomainTest extends TestCase
{
    #[Test]
    public function test_domain_and_http_do_not_query_trx_models_directly(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2).'/app';

        foreach (['Domain', 'Http'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$dir}", \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $entry) {
                /** @var \SplFileInfo $entry */
                if ($entry->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) file_get_contents($entry->getPathname());

                if (preg_match('/\bTrx[A-Z]\w*::(?:query|where|whereIn|find|findOrFail|first|on|all)\s*\(/', $content, $m)) {
                    $violations[] = str_replace($root.'/', 'app/', $entry->getPathname()).": {$m[0]}";
                }
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "Trx モデルを直接読まず、Repository の select* を使ってください:\n".implode("\n", $violations)
        );
    }
}
