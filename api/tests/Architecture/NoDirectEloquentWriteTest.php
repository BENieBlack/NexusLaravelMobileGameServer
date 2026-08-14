<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Eloquentによる即時書き込みの禁止
 *
 * Sys/Trx/LogのデータはUnitOfWorkにキューイングし、
 * UseCaseのトランザクション終了時に一括で反映する規約。
 *
 * 実行時は _BaseModel が save() を拒否するが、テストのフィクスチャ投入のため
 * テスト実行中は許可状態になる。そのため本番コードへの混入は静的に検出する。
 */
class NoDirectEloquentWriteTest extends TestCase
{
    #[Test]
    public function test_production_code_does_not_call_eloquent_write_methods(): void
    {
        // $this->save() / $model->delete() など、引数なしで呼ばれる書き込みメソッド
        $forbidden = ['save', 'delete', 'forceDelete'];

        $violations = [];

        foreach ($this->productionFiles() as $file) {
            // コメント内の記述を誤検出しないよう、コードのみを対象にする
            $content = $this->stripComments(file_get_contents($file));

            foreach ($forbidden as $method) {
                // $model->save() のようにModelインスタンスへ直接呼ぶ形のみを対象にする。
                // ->where(...)->delete() のようなクエリビルダのチェーンは別物なので除外する。
                if (preg_match('/\$\w+->'.$method.'\(\s*\)/', $content)) {
                    $violations[] = $this->relativePath($file)." contains \$model->{$method}()";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Eloquentによる即時書き込みは禁止されています:\n".
            implode("\n", $violations)."\n".
            'RepositoryのsetModel() / softDeleteModel() / hardDeleteModel() でUnitOfWorkにキューイングしてください。'
        );
    }

    /**
     * 検査対象の本番コード
     *
     * @return list<string>
     */
    private function productionFiles(): array
    {
        $roots = [
            __DIR__.'/../../app',
            __DIR__.'/../../../packages',
        ];

        $files = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            // vendorと各パッケージのtestsは走査しない（本番コードのみ検査する）
            $directories = new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $entry) => ! $entry->isDir()
                    || ! in_array($entry->getFilename(), ['vendor', 'tests', 'database'], true)
            );

            foreach (new \RecursiveIteratorIterator($directories) as $entry) {
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

    private function relativePath(string $path): string
    {
        $base = realpath(__DIR__.'/../../..');
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
