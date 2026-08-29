<?php

namespace Tests\Unit\Domain\Mailbox\Constants;

use App\Domain\Mailbox\Constants\ContentType;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * メール添付物の種別のテスト
 *
 * DBの enum（パスカルケース）と配送側の ResourceType（スネークケース）を
 * 繋ぐ位置にある。ここがずれると受取時に落ちるか、黙って配られない。
 */
class ContentTypeTest extends TestCase
{
    #[Test]
    public function 文字列から変換できる(): void
    {
        $this->assertSame(ContentType::PAID_DIAMOND, ContentType::fromString('PaidDiamond'));
        $this->assertNull(ContentType::fromString('NoSuchType'));
    }

    #[Test]
    public function 有効な種別かどうかを判定できる(): void
    {
        $this->assertTrue(ContentType::isValid('AlliancePoints'));
        $this->assertFalse(ContentType::isValid('alliancepoints'), '大文字小文字は区別する');
        $this->assertFalse(ContentType::isValid('NoSuchType'));
    }

    #[Test]
    public function 全ての種別を文字列で取れる(): void
    {
        $all = ContentType::all();

        $this->assertContains('Diamond', $all);
        $this->assertContains('AlliancePoints', $all);
        $this->assertCount(count(ContentType::cases()), $all);
    }

    #[Test]
    public function 全ての種別がリソース種別へ写せる(): void
    {
        // 種別を増やしたときに match が落ちる。ここが通ることが繋ぎ込みの担保
        foreach (ContentType::cases() as $contentType) {
            $this->assertInstanceOf(
                ResourceType::class,
                $contentType->toResourceType(),
                "{$contentType->value} の変換先が無い"
            );
        }
    }

    #[Test]
    public function 複数語の種別はスネークケースへ写る(): void
    {
        // 素の小文字化では paiddiamond になってしまい ResourceType と合わない
        $this->assertSame(ResourceType::PAID_DIAMOND, ContentType::PAID_DIAMOND->toResourceType());
        $this->assertSame(ResourceType::ALLIANCE_POINTS, ContentType::ALLIANCE_POINTS->toResourceType());
        $this->assertSame('paid_diamond', ContentType::PAID_DIAMOND->toResourceType()->value);
        $this->assertSame('alliance_points', ContentType::ALLIANCE_POINTS->toResourceType()->value);
    }

    #[Test]
    public function カスタム以外は配送ハンドラが居る(): void
    {
        // ハンドラが無いタイプはメールに入れても配送されず、
        // 配送前リストに残ったままメールだけ受取済みになる。
        // Custom は用途がタイトル依存なので、アプリ側で登録するまで対象外
        $supported = app(ResourceDeliveryService::class)->supportedTypes();

        $missing = [];
        foreach (ContentType::cases() as $contentType) {
            if ($contentType === ContentType::CUSTOM) {
                continue;
            }

            if (! in_array($contentType->toResourceType()->value, $supported, true)) {
                $missing[] = $contentType->value;
            }
        }

        $this->assertSame([], $missing, '配送ハンドラの無い種別: '.implode(', ', $missing));
    }

    #[Test]
    public function 全ての種別にラベルとアイコンがある(): void
    {
        foreach (ContentType::cases() as $contentType) {
            $this->assertNotSame('', $contentType->label(), "{$contentType->value} のラベルが空");
            $this->assertNotSame('', $contentType->icon(), "{$contentType->value} のアイコンが空");
        }
    }

    #[Test]
    public function ラベルとアイコンは種別ごとに違う(): void
    {
        $labels = array_map(fn (ContentType $type) => $type->label(), ContentType::cases());
        $icons = array_map(fn (ContentType $type) => $type->icon(), ContentType::cases());

        $this->assertSame($labels, array_unique($labels), 'ラベルが重複している');
        $this->assertSame($icons, array_unique($icons), 'アイコンが重複している');
    }
}
