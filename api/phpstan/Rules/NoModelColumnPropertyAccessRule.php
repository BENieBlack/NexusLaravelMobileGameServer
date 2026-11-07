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
 * モデルの外から、列を `$model->column` のプロパティとして読み書きすることを禁止するルール
 *
 * 列の読み書きは、モデルの型付き getter / setter（getFreeAmount() など）を使う。
 * プロパティ経由は、マジックメソッド（__get / __set）で動いていて、型も列名の綴りも保証されない。
 *
 * 許可する場所は NoGetAttributeOutsideModelsRule と同じ。
 * クラスに実在するプロパティ（$exists など）と、リレーション（同名のメソッドがある）は対象外。docblock の @property で宣言した列が対象。
 *
 * @implements Rule<Node\Expr\PropertyFetch>
 */
class NoModelColumnPropertyAccessRule implements Rule
{
    /** @var list<string> */
    private const ALLOWED_PATHS = [
        '/Models/',
        '/packages/nexus-core/src/Repositories/',
        '/packages/nexus-core/src/UnitOfWork/',
        '/packages/nexus-core/src/Pitr/',
    ];

    public function getNodeType(): string
    {
        return Node\Expr\PropertyFetch::class;
    }

    /**
     * @param  Node\Expr\PropertyFetch  $node
     * @return array<int, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'this') {
            return [];
        }

        $file = $scope->getFile();
        foreach (self::ALLOWED_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        $type = $scope->getType($node->var);
        if (! (new ObjectType(Model::class))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        $name = $node->name->toString();

        // クラスに実在するプロパティ（$exists、$wasRecentlyCreated など）は対象外
        foreach ($type->getObjectClassReflections() as $classReflection) {
            if ($classReflection->hasNativeProperty($name)) {
                return [];
            }

            // リレーション（同名のメソッドがある）は、列ではないので対象外
            if ($classReflection->hasNativeMethod($name)) {
                return [];
            }
        }

        $class = $type->getObjectClassNames()[0] ?? 'Model';

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'モデルの外から列 $%s をプロパティで読み書きしないでください。モデルの getter / setter を使ってください'
                    .'（足りなければモデルに追加する）。[%s]',
                    $name,
                    $class
                )
            )
                ->identifier('model.noColumnPropertyAccess')
                ->build(),
        ];
    }
}
