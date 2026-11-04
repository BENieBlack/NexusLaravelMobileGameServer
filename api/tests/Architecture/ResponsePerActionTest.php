<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Domain\_BaseResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * アーキテクチャテスト: UseCase ごとの Response
 *
 * - UseCase の入口は handle()
 * - handle() は自分の Response（App\Domain\{Context}\Responses\{UseCase名}Response）を返す。
 *   Response は UseCase の結果の時点で API の形に整形済みにする
 * - 1つの Response を複数の UseCase で共用しない（形が同じなら継承して分ける）。
 *   handle() を親クラスから受け継ぐ UseCase は、親の Response を返す
 * - Controller は Response を作らず、handle() の結果を返すだけにする
 * - Response が外に見せる生成メソッドは fromResult() だけ
 */
class ResponsePerActionTest extends TestCase
{
    #[Test]
    public function use_case_entry_is_handle(): void
    {
        $violations = [];

        foreach ($this->useCases() as $useCase) {
            if (! $useCase->hasMethod('handle') || ! $useCase->getMethod('handle')->isPublic()) {
                $violations[] = $useCase->getName().' に public handle() が無い';
            }
            foreach (['exec', 'execute', 'run', '__invoke'] as $other) {
                if ($useCase->hasMethod($other)) {
                    $violations[] = $useCase->getName()."::{$other}() は handle() にする";
                }
            }
        }

        $this->assertSame([], $violations, "UseCase の入口は handle() に統一してください:\n".implode("\n", $violations));
    }

    #[Test]
    public function handle_returns_its_own_response(): void
    {
        $violations = [];
        $owners = [];

        foreach ($this->useCases() as $useCase) {
            if (! $useCase->hasMethod('handle')) {
                continue;
            }

            $handle = $useCase->getMethod('handle');
            $declaring = $handle->getDeclaringClass();
            $expected = $this->expectedResponse($declaring);
            $type = $handle->getReturnType();
            $actual = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            if ($actual !== $expected) {
                $violations[] = sprintf('%s::handle() は %s を返すこと（現在: %s）', $declaring->getShortName(), $expected, $actual);

                continue;
            }
            if (! is_subclass_of($actual, _BaseResponse::class)) {
                $violations[] = $actual.' は '._BaseResponse::class.' を継承すること';
            }

            // handle() を宣言したクラスごとに1つ。継承した handle() は親の Response を使う
            $owners[$actual][$declaring->getName()] = true;
        }

        foreach ($owners as $response => $declarers) {
            if (count($declarers) > 1) {
                $violations[] = $response.' を複数の UseCase が返している: '.implode(', ', array_keys($declarers));
            }
        }

        $this->assertSame([], $violations, "UseCase の handle() は自分専用の Response を返してください:\n".implode("\n", $violations));
    }

    #[Test]
    public function controllers_do_not_build_responses(): void
    {
        $violations = [];
        $base = (string) realpath(__DIR__.'/../../app/Http/Controllers');

        foreach (glob($base.'/*Controller.php') ?: [] as $file) {
            if (str_starts_with(basename($file), '_')) {
                continue;
            }
            $code = (string) file_get_contents($file);
            if (preg_match_all('/\b(?:new\s+(\w+Response)\b|(\w+Response)::)/', $code, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = $m[1] !== '' ? $m[1] : $m[2];
                    if (! in_array($name, ['JsonResponse', 'ErrorResponse'], true)) {
                        $violations[] = basename($file).': '.$name;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), "Controller では Response を作らず、UseCase の handle() の結果を返してください:\n".implode("\n", $violations));
    }

    #[Test]
    public function responses_expose_only_from_result(): void
    {
        $violations = [];

        foreach ($this->classesIn('Domain', 'App\\Domain') as $class) {
            if (! $class->isSubclassOf(_BaseResponse::class)) {
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
    public function responses_live_in_domain_responses(): void
    {
        $violations = [];

        foreach ($this->classesIn('Domain', 'App\\Domain') as $class) {
            if (! $class->isSubclassOf(_BaseResponse::class)) {
                continue;
            }
            if (! preg_match('/^App\\\\Domain\\\\\w+\\\\Responses$/', $class->getNamespaceName())) {
                $violations[] = $class->getName();
            }
        }

        $this->assertSame([], $violations, "Response は App\\Domain\\{Context}\\Responses に置いてください:\n".implode("\n", $violations));
    }

    /**
     * handle() を宣言したクラスに対応する Response（_BaseBuyUseCase → BuyResponse）
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function expectedResponse(ReflectionClass $declaring): string
    {
        $context = explode('\\', $declaring->getNamespaceName())[2];
        $action = preg_replace('/^_Base/', '', substr($declaring->getShortName(), 0, -strlen('UseCase')));

        return 'App\\Domain\\'.$context.'\\Responses\\'.$action.'Response';
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private function useCases(): array
    {
        return array_values(array_filter(
            $this->classesIn('Domain', 'App\\Domain'),
            fn (ReflectionClass $class): bool => str_ends_with($class->getShortName(), 'UseCase')
                && ! $class->isAbstract()
                && str_ends_with($class->getNamespaceName(), '\\UseCases'),
        ));
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
