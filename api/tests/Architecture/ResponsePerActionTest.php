<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * アーキテクチャテスト: アクションごとの Response と UseCase の入口
 *
 * - Controller の各アクションは専用の `App\Http\Responses\{Controller名}\{メソッド名}Response` を返す。
 *   複数のアクションで1つの Response を共用しない（形が同じなら継承して分ける）
 * - Response は UseCase の戻り値から `fromResult()` で作る。
 *   UseCase の handle() が void のときだけ `new` で作ってよい
 * - Response が外に見せる生成メソッドは fromResult() だけ
 * - UseCase の入口は handle()
 */
class ResponsePerActionTest extends TestCase
{
    /**
     * JSON を直接返す運営向けの Controller は対象外
     */
    private const EXCLUDED_CONTROLLER_DIRS = ['Admin'];

    #[Test]
    public function each_action_returns_its_own_response(): void
    {
        $violations = [];

        foreach ($this->actions() as [$controller, $method, $body]) {
            $context = $this->contextOf($controller);
            $expected = 'App\\Http\\Responses\\'.$context.'\\'.ucfirst($method->getName()).'Response';
            $used = $this->responsesUsedIn($controller, $body);

            if ($used !== [$expected]) {
                $violations[] = sprintf(
                    '%s::%s() は %s を返すこと（現在: %s）',
                    $controller->getShortName(),
                    $method->getName(),
                    $this->shortName($expected),
                    $used === [] ? 'なし' : implode(', ', array_map($this->shortName(...), $used)),
                );
            }
        }

        $this->assertSame([], $violations, "アクションごとに専用の Response を返してください:\n".implode("\n", $violations));
    }

    #[Test]
    public function responses_are_built_from_use_case_results(): void
    {
        $violations = [];

        foreach ($this->actions() as [$controller, $method, $body]) {
            if (! preg_match('/\bnew\s+([A-Z]\w*Response)\b/', $body, $m)) {
                continue;
            }

            $nonVoid = array_filter(
                $this->useCasesUsedIn($controller, $method, $body),
                fn (string $useCase): bool => $this->handleReturnType($useCase) !== 'void',
            );

            if ($nonVoid !== []) {
                $violations[] = sprintf(
                    '%s::%s() は new %s ではなく %s::fromResult() で作ること（%s は値を返す）',
                    $controller->getShortName(),
                    $method->getName(),
                    $m[1],
                    $m[1],
                    implode(', ', array_map($this->shortName(...), $nonVoid)),
                );
            }
        }

        $this->assertSame([], $violations, "UseCase の戻り値がある場合は fromResult() で Response を作ってください:\n".implode("\n", $violations));
    }

    #[Test]
    public function responses_expose_only_from_result(): void
    {
        $violations = [];

        foreach ($this->classesIn('Http/Responses', 'App\\Http\\Responses') as $class) {
            if (! str_ends_with($class->getShortName(), 'Response') || str_starts_with($class->getShortName(), '_')) {
                continue;
            }

            foreach ($class->getMethods(ReflectionMethod::IS_STATIC) as $staticMethod) {
                // private の補助メソッドは外から呼べないので数えない
                if (! $staticMethod->isPublic() || $staticMethod->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                if ($staticMethod->getName() !== 'fromResult') {
                    $violations[] = $class->getShortName().'::'.$staticMethod->getName().'()';
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Response の生成メソッドは fromResult() に統一してください:\n".implode("\n", $violations));
    }

    #[Test]
    public function use_case_entry_is_handle(): void
    {
        $violations = [];

        foreach ($this->classesIn('Domain', 'App\\Domain') as $class) {
            if (! str_ends_with($class->getShortName(), 'UseCase') || $class->isAbstract()) {
                continue;
            }
            if (! $class->hasMethod('handle') || ! $class->getMethod('handle')->isPublic()) {
                $violations[] = $class->getName().' に public handle() が無い';
            }
            foreach (['exec', 'execute', 'run', '__invoke'] as $other) {
                if ($class->hasMethod($other)) {
                    $violations[] = $class->getName()."::{$other}() は handle() にする";
                }
            }
        }

        $this->assertSame([], $violations, "UseCase の入口は handle() に統一してください:\n".implode("\n", $violations));
    }

    /**
     * @return list<array{0: ReflectionClass<object>, 1: ReflectionMethod, 2: string}>
     */
    private function actions(): array
    {
        $actions = [];

        foreach ($this->classesIn('Http/Controllers', 'App\\Http\\Controllers') as $controller) {
            if (str_starts_with($controller->getShortName(), '_') || $controller->isAbstract()) {
                continue;
            }
            if (in_array($this->subDirOf($controller), self::EXCLUDED_CONTROLLER_DIRS, true)) {
                continue;
            }

            $lines = file((string) $controller->getFileName());
            foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $controller->getName() || $method->isConstructor()) {
                    continue;
                }
                $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
                $actions[] = [$controller, $method, $body];
            }
        }

        return $actions;
    }

    /**
     * アクションの本体に出てくる Response クラス（完全修飾名）
     *
     * @param  ReflectionClass<object>  $controller
     * @return list<string>
     */
    private function responsesUsedIn(ReflectionClass $controller, string $body): array
    {
        $imports = $this->importsOf($controller);
        preg_match_all('/\b([A-Z]\w*Response)\b/', $body, $matches);

        $used = [];
        foreach (array_unique($matches[1]) as $short) {
            if ($short === 'JsonResponse') {
                continue;
            }
            $used[] = $imports[$short] ?? $short;
        }

        return $used;
    }

    /**
     * アクションが呼ぶ UseCase（引数で受けるものと、コンストラクタで受けてプロパティに持つもの）
     *
     * @param  ReflectionClass<object>  $controller
     * @return list<string>
     */
    private function useCasesUsedIn(ReflectionClass $controller, ReflectionMethod $method, string $body): array
    {
        $useCases = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && str_ends_with($type->getName(), 'UseCase')) {
                $useCases[] = $type->getName();
            }
        }

        preg_match_all('/\$this->(\w+UseCase)\b/', $body, $matches);
        foreach (array_unique($matches[1]) as $property) {
            $type = $controller->hasProperty($property) ? $controller->getProperty($property)->getType() : null;
            if ($type instanceof ReflectionNamedType) {
                $useCases[] = $type->getName();
            }
        }

        return array_values(array_unique($useCases));
    }

    private function handleReturnType(string $useCase): string
    {
        $class = new ReflectionClass($useCase);
        $type = $class->hasMethod('handle') ? $class->getMethod('handle')->getReturnType() : null;

        return $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return array<string, string> 短い名前 => 完全修飾名
     */
    private function importsOf(ReflectionClass $class): array
    {
        preg_match_all('/^use ([\w\\\\]+?)(?: as (\w+))?;/m', (string) file_get_contents((string) $class->getFileName()), $matches, PREG_SET_ORDER);

        $imports = [];
        foreach ($matches as $m) {
            $short = $m[2] ?? substr((string) strrchr('\\'.$m[1], '\\'), 1);
            $imports[$short] = $m[1];
        }

        return $imports;
    }

    /**
     * @param  ReflectionClass<object>  $controller
     */
    private function contextOf(ReflectionClass $controller): string
    {
        return substr($controller->getShortName(), 0, -strlen('Controller'));
    }

    /**
     * @param  ReflectionClass<object>  $controller
     */
    private function subDirOf(ReflectionClass $controller): string
    {
        $relative = substr($controller->getNamespaceName(), strlen('App\\Http\\Controllers'));

        return ltrim($relative, '\\');
    }

    private function shortName(string $class): string
    {
        return substr((string) strrchr('\\'.$class, '\\'), 1);
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private function classesIn(string $dir, string $namespace): array
    {
        $base = realpath(__DIR__.'/../../app/'.$dir);
        $this->assertNotFalse($base);

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($base) + 1, -4);
            $fqcn = $namespace.'\\'.str_replace('/', '\\', $relative);
            if (class_exists($fqcn)) {
                $classes[] = new ReflectionClass($fqcn);
            }
        }

        usort($classes, fn (ReflectionClass $a, ReflectionClass $b): int => strcmp($a->getName(), $b->getName()));

        return $classes;
    }
}
