<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: パッケージ同士の依存（層）
 *
 * パッケージはなるべく着脱できるようにする。そのために、パッケージを3つの層に分け、
 * 依存してよい向きを決める。
 *
 * | 層 | 依存してよいもの |
 * |---|---|
 * | foundation（土台） | foundation |
 * | platform（ゲーム共通の部品） | foundation、platform（循環なし） |
 * | feature（機能） | foundation、platform。**feature 同士は依存しない** |
 *
 * - 機能のパッケージは、他の機能が壊れずに外せる
 * - platform は、機能が使う共通の部品なので、一緒に入れる前提にする
 * - 使っているパッケージは、必ず composer.json の require に宣言する（単体で入れたときに動くように）
 *
 * 新しいパッケージを足したら、下の LAYERS に層を書く。
 */
class PackageLayerTest extends TestCase
{
    private const FOUNDATION = 'foundation';

    private const PLATFORM = 'platform';

    private const FEATURE = 'feature';

    /** @var array<string, string> パッケージ（packages/ のディレクトリ名）→ 層 */
    private const LAYERS = [
        'nexus-core' => self::FOUNDATION,
        'nexus-core-unit-of-work' => self::FOUNDATION,
        'nexus-core-security' => self::FOUNDATION,
        'nexus-core-auth' => self::FOUNDATION,
        'nexus-pitr' => self::FOUNDATION,
        'nexus-tidb' => self::FOUNDATION,
        // 報酬（リソース）を配る仕組みは、ほぼ全ての機能が使うので、土台に含める。
        // nexus-resource-delivery が WalletService を直接使うため、nexus-wallet も同じ層に置く
        'nexus-resource' => self::FOUNDATION,
        'nexus-resource-delivery' => self::FOUNDATION,
        'nexus-wallet' => self::FOUNDATION,

        'nexus-notification' => self::PLATFORM,
        'nexus-mailbox' => self::PLATFORM,

        'nexus-agreement' => self::FEATURE,
        'nexus-album' => self::FEATURE,
        'nexus-chat' => self::FEATURE,
        'nexus-core-billing' => self::FEATURE,
        'nexus-event' => self::FEATURE,
        'nexus-friend' => self::FEATURE,
        'nexus-gacha' => self::FEATURE,
        'nexus-guild' => self::FEATURE,
        'nexus-level' => self::FEATURE,
        'nexus-login' => self::FEATURE,
        'nexus-maintenance' => self::FEATURE,
        'nexus-mission' => self::FEATURE,
        'nexus-reward-track' => self::FEATURE,
        'nexus-stamina' => self::FEATURE,
        'nexus-tag' => self::FEATURE,
        'nexus-trade' => self::FEATURE,
        'nexus-version' => self::FEATURE,
        'nexus-vip' => self::FEATURE,
    ];

    /** @var array<string, list<string>> 層 → 依存してよい層 */
    private const ALLOWED = [
        self::FOUNDATION => [self::FOUNDATION],
        self::PLATFORM => [self::FOUNDATION, self::PLATFORM],
        self::FEATURE => [self::FOUNDATION, self::PLATFORM],
    ];

    #[Test]
    public function test_every_package_has_a_layer(): void
    {
        $packages = array_keys($this->packages());
        $unassigned = array_values(array_diff($packages, array_keys(self::LAYERS)));
        $removed = array_values(array_diff(array_keys(self::LAYERS), $packages));

        sort($unassigned);
        sort($removed);

        $this->assertSame(
            [],
            $unassigned,
            "層が決まっていないパッケージがあります:\n".implode("\n", $unassigned)."\n"
            .'このテストの LAYERS に、foundation / platform / feature のどれかを書いてください（.claude/architecture.md の6節）。'
        );
        $this->assertSame([], $removed, "LAYERS に、存在しないパッケージがあります:\n".implode("\n", $removed));
    }

    #[Test]
    public function test_declared_dependencies_follow_the_layers(): void
    {
        $violations = [];

        foreach ($this->packages() as $name => $package) {
            foreach ($package['declared'] as $dependency) {
                $from = self::LAYERS[$name] ?? null;
                $to = self::LAYERS[$dependency] ?? null;

                if ($from === null || $to === null) {
                    continue; // 層が無いことは test_every_package_has_a_layer が検出する
                }

                if (! in_array($to, self::ALLOWED[$from], true)) {
                    $violations[] = "{$name}（{$from}）→ {$dependency}（{$to}）";
                }
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "層の向きに反する依存があります:\n".implode("\n", $violations)."\n"
            .'機能（feature）同士は依存できません。契約（インターフェース）を消費する側のパッケージに定義し、api/app のAdapterで実装してください。'
        );
    }

    #[Test]
    public function test_used_packages_are_declared_in_composer_json(): void
    {
        $violations = [];

        foreach ($this->packages() as $name => $package) {
            foreach ($package['used'] as $dependency) {
                if (! in_array($dependency, $package['declared'], true)) {
                    $violations[] = "{$name} は {$dependency} を使っていますが、composer.json の require にありません";
                }
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "宣言していない依存があります:\n".implode("\n", $violations)."\n"
            .'packages/<名前>/composer.json の require に "nexus/<名前>": "@dev" を足してください。'
        );
    }

    #[Test]
    public function test_declared_dependencies_do_not_form_a_cycle(): void
    {
        $graph = [];
        foreach ($this->packages() as $name => $package) {
            $graph[$name] = $package['declared'];
        }

        $cycle = $this->findCycle($graph);

        $this->assertNull($cycle, '依存が循環しています: '.($cycle === null ? '' : implode(' → ', $cycle)));
    }

    /**
     * @return array<string, array{declared: list<string>, used: list<string>}>
     */
    private function packages(): array
    {
        $root = dirname(__DIR__, 3).'/packages';

        if (! is_dir($root)) {
            $this->markTestSkipped('packages/ が見当たりません');
        }

        /** @var array<string, string> $namespaces 名前空間の接頭辞 → パッケージ */
        $namespaces = [];
        /** @var array<string, array<string, mixed>> $composers */
        $composers = [];

        foreach (glob($root.'/*/composer.json') ?: [] as $file) {
            $name = basename(dirname($file));
            $composer = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $composers[$name] = $composer;

            foreach (array_keys($composer['autoload']['psr-4'] ?? []) as $prefix) {
                $namespaces[$prefix] = $name;
            }
        }

        $packages = [];

        foreach ($composers as $name => $composer) {
            $declared = [];
            foreach (array_keys($composer['require'] ?? []) as $required) {
                if (str_starts_with($required, 'nexus/')) {
                    $declared[] = 'nexus-'.substr($required, strlen('nexus/'));
                }
            }

            $packages[$name] = [
                'declared' => $declared,
                'used' => $this->usedPackages($root.'/'.$name.'/src', $name, $namespaces),
            ];
        }

        ksort($packages);

        return $packages;
    }

    /**
     * src で参照している他のパッケージ（コメントは見ない）
     *
     * @param  array<string, string>  $namespaces
     * @return list<string>
     */
    private function usedPackages(string $src, string $self, array $namespaces): array
    {
        if (! is_dir($src)) {
            return [];
        }

        $used = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $entry) {
            if (! $entry->isFile() || $entry->getExtension() !== 'php') {
                continue;
            }

            $code = $this->stripComments((string) file_get_contents($entry->getPathname()));

            foreach ($namespaces as $prefix => $package) {
                if ($package === $self || in_array($package, $used, true)) {
                    continue;
                }

                // 他のクラス名の途中（ExampleNexusFoo\）に当たらないよう、直前が識別子でないことを確かめる
                if (preg_match('/(?<![A-Za-z0-9_])'.preg_quote($prefix, '/').'/', $code) === 1) {
                    $used[] = $package;
                }
            }
        }

        sort($used);

        return $used;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @return list<string>|null 循環の経路。無ければ null
     */
    private function findCycle(array $graph): ?array
    {
        $state = []; // 1 = 探索中、2 = 完了
        $path = [];

        $visit = function (string $node) use (&$visit, &$state, &$path, $graph): ?array {
            $state[$node] = 1;
            $path[] = $node;

            foreach ($graph[$node] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    return [...array_slice($path, (int) array_search($next, $path, true)), $next];
                }

                if (($state[$next] ?? 0) === 0 && ($cycle = $visit($next)) !== null) {
                    return $cycle;
                }
            }

            array_pop($path);
            $state[$node] = 2;

            return null;
        };

        foreach (array_keys($graph) as $node) {
            if (($state[$node] ?? 0) === 0 && ($cycle = $visit($node)) !== null) {
                return $cycle;
            }
        }

        return null;
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
}
