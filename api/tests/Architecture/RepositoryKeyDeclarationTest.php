<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: キー宣言の単一化
 *
 * ユニークキーは $primaryKey がそのまま担う。絞り込みキーは
 * Model の $selectKeys が持ち、Repositoryはそこから解決する。
 *
 * 同じことを2箇所に書くと片方だけ直し忘れる。実際
 * mst_vip_level_reward は Repository 側の宣言漏れで
 * 全行が同じキーに潰れ、報酬が1件しか配られていなかった。
 */
class RepositoryKeyDeclarationTest extends TestCase
{
    #[Test]
    public function test_repositories_do_not_redeclare_model_keys(): void
    {
        $violations = [];

        foreach ($this->repositoryFiles() as $file) {
            $content = (string) file_get_contents($file);

            foreach (['selectKeys'] as $property) {
                if (! preg_match('/protected array \$'.$property.' = (\[[^\]]*\]);/', $content, $matches)) {
                    continue;
                }

                if (! preg_match('/protected string \$modelClass = (\w+)::class;/', $content, $modelMatch)) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s が $%s を宣言している（%s に書くこと）',
                    basename($file),
                    $property,
                    $modelMatch[1]
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "キーの宣言はModel側に寄せてください:\n".implode("\n", $violations)."\n"
            .'Repositoryは _BaseRepository::getUniqueKeys() / getSelectKeys() 経由で解決します。'
        );
    }

    #[Test]
    public function test_no_model_declares_unique_keys(): void
    {
        // ユニークキーは $primaryKey がそのまま担う。
        // 採番idを別に持つと「行の識別子」と「業務上の一意」が
        // 二重管理になるため、trxテーブルは業務キーを主キーにする
        $violations = [];

        foreach ($this->modelFiles() as $file) {
            $content = (string) file_get_contents($file);

            if (preg_match('/protected array \$uniqueKeys = /', $content)) {
                $violations[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "\$uniqueKeys は廃止しました:\n".implode("\n", $violations)."\n"
            .'業務上の一意をそのまま $primaryKey に書いてください（配列で複合主キーを表せます）。'
        );
    }

    #[Test]
    public function test_every_model_resolves_its_unique_keys(): void
    {
        // 解決結果が空になるモデルがあると、キャッシュのキーが
        // 全行で同じになって1件しか保持できなくなる
        $violations = [];

        foreach ($this->modelClasses() as $class) {
            $model = new $class;

            if ($model->getUniqueKeys() === []) {
                $violations[] = $class;
            }
        }

        $this->assertSame([], $violations, "ユニークキーを解決できないモデル:\n".implode("\n", $violations));
    }

    /**
     * @return list<string>
     */
    private function repositoryFiles(): array
    {
        return $this->phpFiles(__DIR__.'/../../app/Repositories');
    }

    /**
     * @return list<string>
     */
    private function modelFiles(): array
    {
        return array_merge(
            $this->phpFiles(__DIR__.'/../../app/Models'),
            $this->phpFiles(__DIR__.'/../../../packages')
        );
    }

    /**
     * 実体化できるModelクラスを集める
     *
     * @return list<class-string<_BaseModel>>
     */
    private function modelClasses(): array
    {
        $classes = [];

        foreach ($this->phpFiles(__DIR__.'/../../app/Models') as $file) {
            $content = (string) file_get_contents($file);

            if (! preg_match('/namespace ([^;]+);/', $content, $namespace)
                || ! preg_match('/\nclass (\w+)/', $content, $class)) {
                continue;
            }

            $fqcn = $namespace[1].'\\'.$class[1];

            if (is_subclass_of($fqcn, _BaseModel::class)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        // vendor と各パッケージのtestsは走査しない
        $entries = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $entry) => ! $entry->isDir()
                || ! in_array($entry->getFilename(), ['vendor', 'tests', 'database'], true)
        );

        foreach (new \RecursiveIteratorIterator($entries) as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }
}
