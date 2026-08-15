<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: Service層のDB操作規約
 *
 * Service層では直接Eloquentの save/update/delete を呼び出さないことを検証します。
 * 必ずRepository経由で操作する必要があります。
 */
class ServiceLayerTest extends TestCase
{
    /**
     * Service層で直接 save/update/delete を呼び出していないかチェック
     */
    public function test_services_do_not_call_save_directly(): void
    {
        $serviceFiles = glob(__DIR__.'/../../app/Domain/*/Services/*.php');

        $violations = [];

        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);

            // ->save(), ->update(), ->delete(), ->forceDelete() を検索
            if (preg_match('/->save\(\)/', $content)) {
                $violations[] = basename($file).' contains ->save()';
            }
            if (preg_match('/->update\(\)/', $content)) {
                $violations[] = basename($file).' contains ->update()';
            }
            if (preg_match('/->delete\(\)/', $content)) {
                $violations[] = basename($file).' contains ->delete()';
            }
            if (preg_match('/->forceDelete\(\)/', $content)) {
                $violations[] = basename($file).' contains ->forceDelete()';
            }
        }

        $this->assertEmpty(
            $violations,
            "Service層で直接Eloquentの save/update/delete を呼び出さないでください:\n".
            implode("\n", $violations)."\n".
            'Repository経由で操作してください (setModel() or updatePlayer())'
        );
    }

    /**
     * Service層のクラスが 'Service' で終わっているかチェック
     *
     * 例外: パッケージ層のインターフェース・基底クラスを実装するアダプタは、
     * 実装先の契約名に揃えた命名（TokenValidator, TemplateEngine など）を許容する。
     */
    public function test_service_classes_must_end_with_service_suffix(): void
    {
        $serviceFiles = glob(__DIR__.'/../../app/Domain/*/Services/*.php');

        $violations = [];

        foreach ($serviceFiles as $file) {
            $className = basename($file, '.php');

            if (str_ends_with($className, 'Service')) {
                continue;
            }

            if ($this->implementsPackageContract($file, $className)) {
                continue;
            }

            $violations[] = $className;
        }

        $this->assertEmpty(
            $violations,
            "Service層のクラスは 'Service' で終わる必要があります".
            '（パッケージ層の契約を実装するアダプタは除く）: '.implode(', ', $violations)
        );
    }

    /**
     * パッケージ層（Nexus*名前空間）のインターフェースまたは基底クラスを実装しているか
     */
    private function implementsPackageContract(string $file, string $className): bool
    {
        // app/Domain/{Domain}/Services/{Class}.php → App\Domain\{Domain}\Services\{Class}
        $domain = basename(dirname($file, 2));
        $fqcn = "App\\Domain\\{$domain}\\Services\\{$className}";

        if (! class_exists($fqcn)) {
            return false;
        }

        $reflection = new \ReflectionClass($fqcn);

        $ancestors = $reflection->getInterfaceNames();

        if ($parent = $reflection->getParentClass()) {
            $ancestors[] = $parent->getName();
        }

        foreach ($ancestors as $ancestor) {
            if (str_starts_with($ancestor, 'Nexus')) {
                return true;
            }
        }

        return false;
    }

    /**
     * UseCase層のクラスが 'UseCase' で終わっているかチェック
     */
    public function test_usecase_classes_must_end_with_usecase_suffix(): void
    {
        $useCaseFiles = glob(__DIR__.'/../../app/Domain/*/UseCases/*.php');

        $violations = [];

        foreach ($useCaseFiles as $file) {
            $className = basename($file, '.php');
            if (! str_ends_with($className, 'UseCase')) {
                $violations[] = $className;
            }
        }

        $this->assertEmpty(
            $violations,
            "UseCase層のクラスは 'UseCase' で終わる必要があります: ".implode(', ', $violations)
        );
    }

    /**
     * Repository層のクラスが 'Repository' で終わっているかチェック
     *
     * インターフェース宣言は実装と同居させる方針のため、'RepositoryInterface' も許容する。
     */
    public function test_repository_classes_must_end_with_repository_suffix(): void
    {
        $repositoryFiles = array_merge(
            glob(__DIR__.'/../../app/Repositories/*/*.php'),
            glob(__DIR__.'/../../app/Repositories/*/*/*.php')
        );

        $violations = [];

        foreach ($repositoryFiles as $file) {
            $className = basename($file, '.php');

            // _で始まる抽象クラスは除外
            if (str_starts_with($className, '_')) {
                continue;
            }

            // パッケージのInterfaceを実装しDTOへ詰め替えるアダプタは 'RepositoryAdapter' を許容する
            $allowed = ['Repository', 'RepositoryInterface', 'RepositoryAdapter'];

            $matched = false;
            foreach ($allowed as $suffix) {
                if (str_ends_with($className, $suffix)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $violations[] = $className;
            }
        }

        $this->assertEmpty(
            $violations,
            "Repository層のクラスは 'Repository' / 'RepositoryInterface' / 'RepositoryAdapter' で終わる必要があります: ".implode(', ', $violations)
        );
    }
}
