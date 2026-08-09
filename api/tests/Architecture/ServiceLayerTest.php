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
     */
    public function test_service_classes_must_end_with_service_suffix(): void
    {
        $serviceFiles = glob(__DIR__.'/../../app/Domain/*/Services/*.php');

        $violations = [];

        foreach ($serviceFiles as $file) {
            $className = basename($file, '.php');
            if (! str_ends_with($className, 'Service')) {
                $violations[] = $className;
            }
        }

        $this->assertEmpty(
            $violations,
            "Service層のクラスは 'Service' で終わる必要があります: ".implode(', ', $violations)
        );
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

            if (! str_ends_with($className, 'Repository')) {
                $violations[] = $className;
            }
        }

        $this->assertEmpty(
            $violations,
            "Repository層のクラスは 'Repository' で終わる必要があります: ".implode(', ', $violations)
        );
    }
}
