<?php

namespace Tests\Unit\Adapters\Mailbox;

use App\Adapters\Mailbox\MailboxAdapter;
use App\Models\Trx\TrxMailbox;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MailboxAdapter の Model→DTO 変換テスト
 *
 * 真偽値カラムの名前がModelとDTOでずれている（is_opened→isRead、
 * is_protected→isLocked）ため、取り違えが起きていないことを検証する。
 */
class MailboxAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $model = $this->makeMailbox();

        $dto = MailboxAdapter::toDto($model);

        $this->assertSame(11, $dto->getId());
        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('mailbox_welcome_001', $dto->getMstMailboxId());
        $this->assertSame('2026-01-31 23:59:59', $dto->getExpiresAt());
        $this->assertSame('2026-01-01 00:00:00', $dto->getCreatedAt());
    }

    #[Test]
    public function test_maps_model_flags_to_dto_flags(): void
    {
        // is_opened→isRead / is_received→isReceived / is_protected→isLocked
        // の3つが1つずつずれても気付けるよう、値をすべて違える
        $dto = MailboxAdapter::toDto($this->makeMailbox([
            'is_opened' => true,
            'is_received' => false,
            'is_protected' => true,
        ]));

        $this->assertTrue($dto->isRead());
        $this->assertFalse($dto->isReceived());
        $this->assertTrue($dto->isLocked());
    }

    #[Test]
    public function test_expires_at_can_be_null(): void
    {
        $dto = MailboxAdapter::toDto($this->makeMailbox(['expires_at' => null]));

        $this->assertNull($dto->getExpiresAt());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = MailboxAdapter::toDtoArray([
            $this->makeMailbox(['id' => 1]),
            $this->makeMailbox(['id' => 2]),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame([1, 2], array_map(fn ($dto) => $dto->getId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], MailboxAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMailbox(array $overrides = []): TrxMailbox
    {
        $model = new TrxMailbox;
        $model->forceFill(array_merge([
            'id' => 11,
            'sys_player_id' => 501,
            'mst_mailbox_id' => 'mailbox_welcome_001',
            'is_opened' => false,
            'is_received' => false,
            'is_protected' => false,
            'expires_at' => '2026-01-31 23:59:59',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides));

        return $model;
    }
}
