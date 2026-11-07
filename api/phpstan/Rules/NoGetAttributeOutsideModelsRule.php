<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * モデルの外から getAttribute() / setAttribute() を呼ぶことを禁止するルール
 *
 * 列の読み書きは、モデルが持つ型付きの getter / setter（getFreeAmount() など）を使う。
 * 文字列の列名を直接渡すと、列名の変更や型の変更に気づけず、IDE や静的解析も効かない。
 *
 * 許可する場所:
 * - モデル自身（api/app/Models/、各パッケージの Models/）。getter の実装そのもの
 * - $this に対する呼び出し（モデルが使うトレイトなど）
 * - 列名が動的になる基盤（packages/nexus-core の Repository / UnitOfWork / Pitr / Models）
 *
 * @implements Rule<Node\Expr\MethodCall>
 */
class NoGetAttributeOutsideModelsRule implements Rule
{
    private const FORBIDDEN_METHODS = ['getAttribute', 'setAttribute'];

    /** @var list<string> 列名をキーとして動的に扱う基盤のパス */
    private const ALLOWED_PATHS = [
        '/Models/',
        '/packages/nexus-core/src/Repositories/',
        '/packages/nexus-core/src/UnitOfWork/',
        '/packages/nexus-core/src/Pitr/',
    ];

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    /**
     * @param  Node\Expr\MethodCall  $node
     * @return array<int, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $method = $node->name->toString();
        if (! in_array($method, self::FORBIDDEN_METHODS, true)) {
            return [];
        }

        // $this->getAttribute() は、モデル自身かモデルが使うトレイトの呼び出し
        if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'this') {
            return [];
        }

        $file = $scope->getFile();
        foreach (self::ALLOWED_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        if (! (new ObjectType(Model::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'モデルの外から %s() を呼ばないでください。モデルの getter / setter を使ってください'
                    .'（足りなければモデルに追加する）。',
                    $method
                )
            )
                ->identifier('model.noGetAttributeOutsideModels')
                ->build(),
        ];
    }
}
