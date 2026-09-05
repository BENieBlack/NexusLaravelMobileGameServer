<?php

namespace Tests\Unit\Domain\Mailbox;

use App\Domain\Mailbox\Services\TemplateEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TemplateEngine のテスト
 *
 * メール文面の {key} を実値に差し替えるエンジン。
 * ゲーム固有のResolver（player / alliance / battle）が
 * 登録順どおりに効くことと、値が引けない場合の落とし所を確認する。
 *
 * Resolverは「プロパティがあればそれ、無ければgetter」の順で値を引くので、
 * 両方の形のオブジェクトを渡して確かめる。
 */
class TemplateEngineTest extends TestCase
{
    private TemplateEngine $templateEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateEngine = new TemplateEngine;
    }

    #[Test]
    public function カスタムパラメータで置換する(): void
    {
        $rendered = $this->templateEngine->render(
            'こんにちは、{player_name}様。現在のレベルは{player_level}です。',
            ['player_name' => 'Commander', 'player_level' => '50'],
        );

        $this->assertSame('こんにちは、Commander様。現在のレベルは50です。', $rendered);
    }

    #[Test]
    public function カスタムパラメータはresolverより優先される(): void
    {
        $context = ['player' => (object) ['name' => 'FromContext']];

        $rendered = $this->templateEngine->render(
            '{player_name}',
            ['player_name' => 'FromParams'],
            $context,
        );

        $this->assertSame('FromParams', $rendered);
    }

    #[Test]
    public function プレイヤー情報をプロパティから引く(): void
    {
        $context = ['player' => (object) ['id' => 42, 'name' => 'Alice', 'level' => 7]];

        $rendered = $this->templateEngine->render('{player_id}/{player_name}/{player_level}', [], $context);

        $this->assertSame('42/Alice/7', $rendered);
    }

    #[Test]
    public function プレイヤー情報をgetterから引く(): void
    {
        $context = ['player' => new FakePlayer];

        $rendered = $this->templateEngine->render('{player_id}/{player_name}/{player_level}', [], $context);

        $this->assertSame('99/Bob/12', $rendered);
    }

    #[Test]
    public function アライアンス情報を置換する(): void
    {
        $context = [
            'alliance' => (object) [
                'id' => 1,
                'name' => 'Warriors',
                'rank' => 3,
                'level' => 10,
                'member_count' => 25,
                'max_members' => 50,
            ],
        ];

        $rendered = $this->templateEngine->render(
            '{alliance_name}({alliance_id}) Lv{alliance_level} 順位{alliance_rank} {member_count}/{max_members}',
            [],
            $context,
        );

        $this->assertSame('Warriors(1) Lv10 順位3 25/50', $rendered);
    }

    #[Test]
    public function 戦闘情報を置換する(): void
    {
        $context = [
            'battle' => (object) [
                'result' => 'Victory',
                'enemy_name' => 'Goblin',
                'damage_dealt' => 1000,
                'damage_received' => 200,
                'troops_lost' => 5,
                'troops_killed' => 30,
                'battle_time' => '2026-03-15 12:00:00',
            ],
        ];

        $rendered = $this->templateEngine->render(
            '{battle_result} vs {enemy_name} 与{damage_dealt}/被{damage_received} 損失{troops_lost} 撃破{troops_killed} at {battle_time}',
            [],
            $context,
        );

        $this->assertSame('Victory vs Goblin 与1000/被200 損失5 撃破30 at 2026-03-15 12:00:00', $rendered);
    }

    #[Test]
    public function システムプレースホルダーはコンテキスト無しで解決できる(): void
    {
        $rendered = $this->templateEngine->render('サーバー: {server_name}');

        $this->assertSame('サーバー: '.config('app.name', 'Game Server'), $rendered);
    }

    #[Test]
    public function 解決できないプレースホルダーは既定では空文字になる(): void
    {
        $rendered = $this->templateEngine->render(
            'Hello {unknown_key}, your name is {player_name}',
            ['player_name' => 'Alice'],
        );

        $this->assertSame('Hello , your name is Alice', $rendered);
    }

    #[Test]
    public function コンテキストが無ければresolver対象のキーも空文字になる(): void
    {
        $this->assertSame('', $this->templateEngine->render('{player_name}'));
    }

    #[Test]
    public function keepにすると未解決のプレースホルダーを残す(): void
    {
        $this->templateEngine->setUnresolvedBehavior('keep');

        $rendered = $this->templateEngine->render('Hello {unknown_key}', []);

        $this->assertSame('Hello {unknown_key}', $rendered);
    }

    #[Test]
    public function サポートするプレースホルダーをresolverごとに一覧できる(): void
    {
        $supported = $this->templateEngine->supportedPlaceholders();

        $this->assertSame(
            ['SystemPlaceholder', 'PlayerPlaceholder', 'AlliancePlaceholder', 'BattlePlaceholder'],
            array_keys($supported),
            '登録順にResolverが並ぶ',
        );
        $this->assertContains('player_name', $supported['PlayerPlaceholder']);
        $this->assertContains('alliance_name', $supported['AlliancePlaceholder']);
        $this->assertContains('battle_result', $supported['BattlePlaceholder']);
    }
}

/**
 * プロパティを持たず、getterだけで値を返すプレイヤー
 */
class FakePlayer
{
    public function getSysPlayerId(): int
    {
        return 99;
    }

    public function getName(): string
    {
        return 'Bob';
    }

    public function getLevel(): int
    {
        return 12;
    }
}
