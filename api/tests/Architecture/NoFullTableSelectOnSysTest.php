<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Models\Sys\SysGuild;
use Nexus\Core\Repositories\Sys\_BaseSysRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Sysテーブルの全件取得の禁止
 *
 * sys_player は全プレイヤー、sys_guild は全ギルドが1つのテーブルに入る。
 * Trxのようにプレイヤーで分かれていないため、全件を読むクエリは
 * 件数が増えた分だけそのまま重くなる。
 *
 * 実行時は _BaseSysRepository::selectAll() が例外を投げるが、
 * Model::all() を直に書かれると素通りするので静的にも検出する。
 */
class NoFullTableSelectOnSysTest extends TestCase
{
    #[Test]
    public function test_sys_repositories_do_not_select_all_rows(): void
    {
        $violations = [];

        foreach ($this->sysRepositoryFiles() as $file) {
            $content = $this->stripComments((string) file_get_contents($file));

            // SysGuild::all() / $this->modelClass::all() のような
            // 条件なしの全件取得。Collection::all() は別物なので
            // 「->all()」ではなく「::all()」だけを見る
            if (preg_match('/(?:\$this->modelClass|[A-Z]\w*)::all\s*\(/', $content)) {
                $violations[] = $this->relativePath($file).' contains ::all()';
            }

            // ->get() を条件も件数の上限も付けずに呼んでいる形
            if (preg_match('/->newQuery\(\s*\)\s*->get\s*\(/', $content)) {
                $violations[] = $this->relativePath($file).' contains newQuery()->get()';
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Sysテーブルの全件取得は禁止されています:\n"
            .implode("\n", $violations)."\n"
            .'自分に関係する行は queryOrMemory()、それ以外は selectWithoutCache() に'
            .'条件と件数の上限を付けて使ってください。'
        );
    }

    #[Test]
    public function test_select_all_throws_at_runtime(): void
    {
        $repository = new class extends _BaseSysRepository
        {
            protected string $modelClass = SysGuild::class;
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Sysテーブルの全件取得は禁止');

        $repository->selectAll();
    }

    /**
     * 検査対象（Sysリポジトリの本番コード）
     *
     * @return list<string>
     */
    private function sysRepositoryFiles(): array
    {
        $roots = [
            __DIR__.'/../../app/Repositories/Sys',
            __DIR__.'/../../../packages/nexus-core/src/Repositories/Sys',
        ];

        $files = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            ) as $entry) {
                /** @var \SplFileInfo $entry */
                if ($entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * コメントとDocコメントを除去する
     */
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

    private function relativePath(string $file): string
    {
        return str_replace(realpath(__DIR__.'/../../..').'/', '', (string) realpath($file));
    }
}
