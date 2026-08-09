<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Service層で直接Eloquentの save/update/delete を呼び出すことを禁止するルール
 *
 * Service層では必ずRepository経由でDB操作を行う必要があります。
 * - Trx Repository: setModel() を使用
 * - Sys Repository: updatePlayer() 等の専用メソッドを使用
 *
 * @implements Rule<Node\Expr\MethodCall>
 */
class NoDirectEloquentSaveInServiceRule implements Rule
{
    /**
     * 検出対象のノードタイプ
     */
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    /**
     * ルールのチェック処理
     *
     * @param  Node\Expr\MethodCall  $node
     * @return array<int, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Service層のファイルかチェック
        $filePath = $scope->getFile();
        if (! $this->isServiceFile($filePath)) {
            return [];
        }

        // メソッド名をチェック
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();
        $forbiddenMethods = ['save', 'update', 'delete', 'forceDelete'];

        if (! in_array($methodName, $forbiddenMethods, true)) {
            return [];
        }

        // エラーメッセージを構築
        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Service層で直接Eloquentの %s() を呼び出さないでください。'.PHP_EOL.
                    'Repository経由で操作してください：'.PHP_EOL.
                    '  - Trx Repository: $this->repository->setModel($model)'.PHP_EOL.
                    '  - Sys Repository: $this->repository->updatePlayer($player)',
                    $methodName
                )
            )
                ->identifier('service.noDirectEloquentSave')
                ->build(),
        ];
    }

    /**
     * Service層のファイルかどうかを判定
     */
    private function isServiceFile(string $filePath): bool
    {
        return str_contains($filePath, '/Domain/') && str_contains($filePath, '/Services/');
    }
}
