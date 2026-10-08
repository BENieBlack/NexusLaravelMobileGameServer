<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: モジュール境界
 *
 * app/Domain/{Context} と packages/nexus-* をモジュールとみなし、
 * 境界をまたぐ依存をレビュー頼みにせず静的に検出する。
 *
 * 既存の違反は許可リスト（ベースライン）に載せ、新規の違反だけを落とす。
 * 許可リストはラチェットとして扱い、違反が解消されたのに残っている項目も落とす。
 * 解消したら許可リストから消すこと。
 */
class ModuleBoundaryTest extends TestCase
{
    /**
     * UseCaseがHTTPのレスポンス型を返している既存箇所
     *
     * @var list<string>
     */
    private const DOMAIN_TO_HTTP_BASELINE = [
    ];

    /**
     * 他コンテキストの内部クラスを直接参照している既存箇所（"ファイル -> 参照先コンテキスト"）
     *
     * @var list<string>
     */
    private const CROSS_CONTEXT_BASELINE = [
        'app/Domain/Auth/UseCases/SignUpUseCase.php -> Sharding',
        'app/Domain/Equipment/UseCases/LevelUpUseCase.php -> Item',
        'app/Domain/Gacha/Services/GachaCostService.php -> InAppPurchase',
        'app/Domain/Gacha/Services/GachaCostService.php -> Item',
        'app/Domain/InAppPurchase/Services/InAppPurchasePackService.php -> Item',
        'app/Domain/Player/Services/ExperienceGranterAdapter.php -> Equipment',
        'app/Domain/Player/Services/ExperienceGranterAdapter.php -> Unit',
        'app/Domain/Unit/UseCases/LevelUpUseCase.php -> Item',
    ];

    /**
     * ドメインパッケージからフレームワークの永続化・Facadeを参照している既存箇所
     *
     * @var list<string>
     */
    private const PACKAGE_FRAMEWORK_BASELINE = [
    ];

    /**
     * 技術基盤のパッケージ。フレームワーク統合が責務なので検査対象から外す
     */
    private const INFRASTRUCTURE_PACKAGES = [
        'nexus-core',
        'nexus-core-auth',
        'nexus-core-billing',
        'nexus-core-security',
        'nexus-core-unit-of-work',
        'nexus-maintenance',
        'nexus-pitr',
        'nexus-tidb',
    ];

    /**
     * 他コンテキストからの参照を許す共有領域
     */
    private const SHARED_CONTEXTS = ['Common'];

    /**
     * ドメインパッケージで参照を禁じるフレームワークの名前空間
     */
    private const FORBIDDEN_FRAMEWORK_NAMESPACES = [
        'Illuminate\\Database',
        'Illuminate\\Support\\Facades',
        'Illuminate\\Http',
    ];

    #[Test]
    public function test_domain_does_not_depend_on_http_layer(): void
    {
        $violations = [];

        foreach ($this->phpFiles($this->appRoot().'/Domain') as $file) {
            $code = $this->stripComments((string) file_get_contents($file));

            if ($this->referencesNamespace($code, 'App\\Http')) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertMatchesBaseline(
            $violations,
            self::DOMAIN_TO_HTTP_BASELINE,
            'app/Domain から App\\Http を参照しないでください。'
            .'UseCaseはDTOを返し、Responseへの変換はControllerで行ってください。'
        );
    }

    #[Test]
    public function test_domain_contexts_do_not_reach_into_each_other(): void
    {
        $violations = [];
        $domainRoot = $this->appRoot().'/Domain';

        foreach (glob($domainRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextDir) {
            $context = basename($contextDir);

            foreach ($this->phpFiles($contextDir) as $file) {
                $code = $this->stripComments((string) file_get_contents($file));

                preg_match_all('/App\\\\Domain\\\\([A-Za-z]+)\\\\/', $code, $matches);

                $referenced = array_unique($matches[1]);
                sort($referenced);

                foreach ($referenced as $other) {
                    if ($other === $context || in_array($other, self::SHARED_CONTEXTS, true)) {
                        continue;
                    }
                    $violations[] = $this->relativePath($file).' -> '.$other;
                }
            }
        }

        $this->assertMatchesBaseline(
            $violations,
            self::CROSS_CONTEXT_BASELINE,
            '他コンテキストの内部クラス（App\\Domain\\{Other}\\...）を直接参照しないでください。'
            .'パッケージ側の契約（Contracts / RepositoryInterface）を経由してください。'
        );
    }

    #[Test]
    public function test_domain_packages_do_not_depend_on_framework_persistence(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 3).'/packages';

        foreach (glob($root.'/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            if (in_array(basename(dirname($src)), self::INFRASTRUCTURE_PACKAGES, true)) {
                continue;
            }

            foreach ($this->phpFiles($src) as $file) {
                $code = $this->stripComments((string) file_get_contents($file));

                foreach (self::FORBIDDEN_FRAMEWORK_NAMESPACES as $namespace) {
                    if ($this->referencesNamespace($code, $namespace)) {
                        $violations[] = $this->relativePath($file);
                        break;
                    }
                }
            }
        }

        $this->assertMatchesBaseline(
            $violations,
            self::PACKAGE_FRAMEWORK_BASELINE,
            'ドメインパッケージから Eloquent / Facade / Http を参照しないでください。'
            .'DTO・値オブジェクトとインターフェースで表現し、実装は api/app のAdapterに置いてください。'
        );
    }

    /**
     * 検出結果が許可リストと一致することを確認する
     *
     * @param  list<string>  $violations
     * @param  list<string>  $baseline
     */
    private function assertMatchesBaseline(array $violations, array $baseline, string $guidance): void
    {
        $violations = array_values(array_unique($violations));

        $new = array_values(array_diff($violations, $baseline));
        $resolved = array_values(array_diff($baseline, $violations));

        sort($new);
        sort($resolved);

        $this->assertSame([], $new, "新たな境界違反があります:\n".implode("\n", $new)."\n".$guidance);
        $this->assertSame(
            [],
            $resolved,
            "解消済みの違反が許可リストに残っています。許可リストから削除してください:\n".implode("\n", $resolved)
        );
    }

    /**
     * use文とFQCN参照の両方を拾う
     */
    private function referencesNamespace(string $code, string $namespace): bool
    {
        $quoted = preg_quote($namespace, '/');

        return preg_match('/^\s*use\s+'.$quoted.'\\\\/m', $code) === 1
            || preg_match('/\\\\'.$quoted.'\\\\/', $code) === 1;
    }

    private function appRoot(): string
    {
        return dirname(__DIR__, 2).'/app';
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

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

    /**
     * app/ 配下は api/ 起点、packages/ 配下はリポジトリ起点の相対パスにする。
     * Dockerでは api/ が html/ にマウントされるため、ディレクトリ名に依存させない
     */
    private function relativePath(string $path): string
    {
        $real = realpath($path) ?: $path;

        foreach ([dirname(__DIR__, 2), dirname(__DIR__, 3)] as $base) {
            $base = realpath($base);

            if ($base !== false && str_starts_with($real, $base.'/')) {
                return substr($real, strlen($base) + 1);
            }
        }

        return $real;
    }
}
