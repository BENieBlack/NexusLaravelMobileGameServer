<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysMaintenance;
use Tests\TestCase;

/**
 * _BaseModelの日付属性テスト
 *
 * 日時はDB取得時からレスポンスまで一貫してstringで扱う。
 * Carbonへのキャストを強制されないことを確認する。
 */
class BaseModelDateCastTest extends TestCase
{
    /**
     * 日時ゲッターがstringを返すことを確認
     */
    public function test_date_getter_returns_string(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('start_at', '2026-01-01 12:00:00');

        $result = $model->getStartAt();

        $this->assertIsString($result);
        $this->assertSame('2026-01-01 12:00:00', $result);
    }

    /**
     * 日時の比較が文字列のまま行えることを確認
     *
     * Y-m-d H:i:s は固定長なので辞書順比較が時系列順比較と一致する
     */
    public function test_string_comparison_matches_chronological_order(): void
    {
        $earlier = '2026-01-01 12:00:00';
        $later = '2026-01-02 09:00:00';

        $this->assertTrue($earlier < $later);
        $this->assertTrue(max([$earlier, $later]) === $later);
    }

    /**
     * 日時ゲッターがnullを正しく扱うことを確認
     */
    public function test_get_date_attribute_handles_null(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('end_at', null);

        $result = $model->getEndAt();

        $this->assertNull($result);
    }

    /**
     * toResponseArray()でcreated_at/updated_atが除外されることを確認
     */
    public function test_to_response_array_excludes_timestamps(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('id', 1);
        $model->setAttribute('title', 'Test Maintenance');
        $model->setAttribute('message', 'Testing');
        $model->setAttribute('start_at', '2026-01-01 12:00:00');
        $model->setAttribute('end_at', '2026-01-02 12:00:00');
        $model->setAttribute('is_active', true);
        $model->setAttribute('created_at', '2026-01-01 00:00:00');
        $model->setAttribute('updated_at', '2026-01-01 00:00:00');
        $model->exists = true;

        $array = $model->toResponseArray();

        // created_at, updated_atは除外される
        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);

        // sys_player_id, uuidも除外される（存在しないが念のため）
        $this->assertArrayNotHasKey('sys_player_id', $array);
        $this->assertArrayNotHasKey('uuid', $array);

        // start_at, end_atはstring型のまま（各モデルでtoDto()等で変換）
        $this->assertIsString($array['start_at']);
        $this->assertIsString($array['end_at']);
    }

    /**
     * DB取得時の属性がstring型のままであることを確認（パフォーマンス最適化）
     */
    public function test_attributes_stored_as_string(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('start_at', '2026-01-01 12:00:00');

        // getAttributeValue()は自動キャストを行うが、attributesプロパティは生の値を保持
        $rawAttributes = $model->getAttributes();

        $this->assertIsString($rawAttributes['start_at']);
        $this->assertEquals('2026-01-01 12:00:00', $rawAttributes['start_at']);
    }
}
