<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: エージェント向けドキュメントの同期
 *
 * .claude と .opencode は同じ内容を両ツール向けに保持している。
 * 片方だけを更新すると内容が食い違うため、差分を検出する。
 *
 * .gitignore は各ツールの都合で異なるため対象外とする。
 */
class AgentDocsInSyncTest extends TestCase
{
    private const IGNORED = ['.gitignore', 'node_modules', 'bun.lock', 'package-lock.json'];

    #[Test]
    public function test_claude_and_opencode_docs_are_identical(): void
    {
        $claude = $this->collect(__DIR__.'/../../../.claude');
        $opencode = $this->collect(__DIR__.'/../../../.opencode');

        if ($claude === [] && $opencode === []) {
            $this->markTestSkipped('.claude / .opencode が存在しません');
        }

        $onlyClaude = array_diff(array_keys($claude), array_keys($opencode));
        $onlyOpencode = array_diff(array_keys($opencode), array_keys($claude));

        $this->assertSame([], array_values($onlyClaude), '.opencode に存在しないファイルがあります');
        $this->assertSame([], array_values($onlyOpencode), '.claude に存在しないファイルがあります');

        $differing = [];
        foreach ($claude as $rel => $hash) {
            if (($opencode[$rel] ?? null) !== $hash) {
                $differing[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $differing,
            ".claude と .opencode で内容が異なります:\n".implode("\n", $differing)."\n".
            '片方を更新したらもう片方にも同じ変更を反映してください。'
        );
    }

    /**
     * ディレクトリ配下のファイルを 相対パス => 内容ハッシュ で収集する
     *
     * @return array<string, string>
     */
    private function collect(string $dir): array
    {
        $dir = realpath($dir) ?: '';
        if ($dir === '' || ! is_dir($dir)) {
            return [];
        }

        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $e) => ! in_array($e->getFilename(), self::IGNORED, true)
        );

        $files = [];
        foreach (new \RecursiveIteratorIterator($filter) as $entry) {
            /** @var \SplFileInfo $entry */
            if (! $entry->isFile()) {
                continue;
            }
            $files[substr($entry->getPathname(), strlen($dir) + 1)] = md5_file($entry->getPathname());
        }

        ksort($files);

        return $files;
    }
}
