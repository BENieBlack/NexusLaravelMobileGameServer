<?php

namespace NexusMailbox\Tests\Unit\Services\Template;

use NexusMailbox\Services\Template\_BaseTemplateEngine;
use NexusMailbox\Services\Template\PlaceholderResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * _BaseTemplateEngine のユニットテスト
 *
 * 置換の優先順位（params > resolver）と、未解決プレースホルダーの
 * 挙動（empty / keep）が仕様どおりであることを検証する。
 */
class BaseTemplateEngineTest extends TestCase
{
    #[Test]
    public function resolverで置換できる(): void
    {
        $engine = $this->makeEngine([$this->makePlayerResolver()]);

        $result = $engine->render('こんにちは{player_name}さん', [], ['player_name' => 'ハナコ']);

        $this->assertSame('こんにちはハナコさん', $result);
    }

    #[Test]
    public function paramsがresolverより優先される(): void
    {
        $engine = $this->makeEngine([$this->makePlayerResolver()]);

        $result = $engine->render(
            '{player_name}',
            ['player_name' => 'パラメータ優先'],
            ['player_name' => 'リゾルバ']
        );

        $this->assertSame('パラメータ優先', $result);
    }

    #[Test]
    public function paramsは数値でも文字列に変換される(): void
    {
        $engine = $this->makeEngine();

        $this->assertSame('Lv.30', $engine->render('Lv.{level}', ['level' => 30]));
    }

    #[Test]
    public function 先に登録したresolverが優先される(): void
    {
        $engine = $this->makeEngine([
            $this->makeFixedResolver(['player_name'], '先'),
            $this->makeFixedResolver(['player_name'], '後'),
        ]);

        $this->assertSame('先', $engine->render('{player_name}'));
    }

    #[Test]
    public function resolverがnullを返したら次のresolverに委ねる(): void
    {
        $engine = $this->makeEngine([
            $this->makeFixedResolver(['player_name'], null),
            $this->makeFixedResolver(['player_name'], 'フォールバック'),
        ]);

        $this->assertSame('フォールバック', $engine->render('{player_name}'));
    }

    #[Test]
    public function 未解決のプレースホルダーは既定で空文字になる(): void
    {
        $engine = $this->makeEngine();

        $this->assertSame('[]', $engine->render('[{unknown_key}]'));
    }

    #[Test]
    public function keep指定なら未解決のプレースホルダーを残す(): void
    {
        $engine = $this->makeEngine();
        $engine->setUnresolvedBehavior('keep');

        $this->assertSame('[{unknown_key}]', $engine->render('[{unknown_key}]'));
    }

    #[Test]
    public function 同じプレースホルダーが複数あってもすべて置換される(): void
    {
        $engine = $this->makeEngine();

        $this->assertSame(
            'ハナコとハナコ',
            $engine->render('{player_name}と{player_name}', ['player_name' => 'ハナコ'])
        );
    }

    #[Test]
    public function プレースホルダーを含まないテンプレートはそのまま返る(): void
    {
        $engine = $this->makeEngine([$this->makePlayerResolver()]);

        $this->assertSame('ただの本文', $engine->render('ただの本文'));
    }

    #[Test]
    public function 英数字とアンダースコア以外のキーはプレースホルダーとみなさない(): void
    {
        $engine = $this->makeEngine();

        // 数字やハイフンを含むキーはパース対象外なので置換されない
        $this->assertSame('{item-1}{item 1}', $engine->render('{item-1}{item 1}', ['item-1' => 'x']));
    }

    #[Test]
    public function supported_placeholdersはresolverごとのキー一覧を返す(): void
    {
        $engine = $this->makeEngine([$this->makePlayerResolver()]);

        $supported = $engine->supportedPlaceholders();

        $this->assertArrayHasKey('PlayerResolverStub', $supported);
        $this->assertSame(['player_name', 'player_level'], $supported['PlayerResolverStub']);
    }

    #[Test]
    public function resolverが未登録ならsupported_placeholdersは空配列を返す(): void
    {
        $this->assertSame([], $this->makeEngine()->supportedPlaceholders());
    }

    /**
     * @param  array<PlaceholderResolverInterface>  $resolvers
     */
    private function makeEngine(array $resolvers = []): _BaseTemplateEngine
    {
        $engine = new class extends _BaseTemplateEngine {};

        foreach ($resolvers as $resolver) {
            $engine->registerResolver($resolver);
        }

        return $engine;
    }

    /**
     * contextの値をそのまま返すResolver
     */
    private function makePlayerResolver(): PlaceholderResolverInterface
    {
        return new PlayerResolverStub;
    }

    /**
     * 指定したキーに対して常に同じ値を返すResolver
     *
     * @param  array<string>  $keys
     */
    private function makeFixedResolver(array $keys, ?string $value): PlaceholderResolverInterface
    {
        return new class($keys, $value) implements PlaceholderResolverInterface
        {
            /**
             * @param  array<string>  $keys
             */
            public function __construct(
                private readonly array $keys,
                private readonly ?string $value,
            ) {}

            public function supports(string $key): bool
            {
                return in_array($key, $this->keys, true);
            }

            public function resolve(string $key, array $context): ?string
            {
                return $this->value;
            }

            public function supportedKeys(): array
            {
                return $this->keys;
            }
        };
    }
}

/**
 * supportedPlaceholders() がクラス名をキーに使うため、
 * 名前を確認できるよう匿名クラスではなく named class にしている。
 */
class PlayerResolverStub implements PlaceholderResolverInterface
{
    public function supports(string $key): bool
    {
        return in_array($key, $this->supportedKeys(), true);
    }

    public function resolve(string $key, array $context): ?string
    {
        return isset($context[$key]) ? (string) $context[$key] : null;
    }

    public function supportedKeys(): array
    {
        return ['player_name', 'player_level'];
    }
}
