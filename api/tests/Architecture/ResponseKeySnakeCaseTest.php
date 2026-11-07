<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Response のキーはスネークケース
 *
 * app/Domain/{Context}/Responses/ の `'key' =>` と書いた文字列リテラルのキーを検査する。
 * 変数から作るキー（received_key_set の中身、行IDをキーにした contents など）は
 * データ由来でリテラルに現れないため、検査の対象外。
 */
class ResponseKeySnakeCaseTest extends TestCase
{
    /**
     * スネークケースでないが、API契約として許容するキー（理由を添えて追加する）
     *
     * @var list<string>
     */
    private const ALLOWED = [];

    #[Test]
    public function test_response_keys_are_snake_case(): void
    {
        $violations = [];

        foreach ($this->responseFiles() as $file) {
            if (! preg_match_all("/'([^'\\n]+)'\\s*=>/", (string) file_get_contents($file), $matches)) {
                continue;
            }

            foreach (array_unique($matches[1]) as $key) {
                if (in_array($key, self::ALLOWED, true) || preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $key)) {
                    continue;
                }

                $violations[] = str_replace(realpath(__DIR__.'/../..').'/', '', $file).": '{$key}'";
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "Response のキーはスネークケースにしてください:\n".implode("\n", $violations)."\n".
            '例: \'isLeveledUp\' → \'is_leveled_up\'。API契約として必要なものだけ ALLOWED に理由つきで追加してください。'
        );
    }

    #[Test]
    public function test_response_files_are_found(): void
    {
        $this->assertNotEmpty($this->responseFiles(), 'Response が1つも見つからない。検査対象のパスが壊れている');
    }

    /**
     * @return list<string>
     */
    private function responseFiles(): array
    {
        $files = [];

        foreach (glob(__DIR__.'/../../app/Domain/*/Responses') ?: [] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $entry) {
                /** @var \SplFileInfo $entry */
                if ($entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }
}
