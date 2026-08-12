<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysMaintenance;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * _BaseModelの日付型キャスト最適化テスト
 *
 * DB取得時はstring型で保持し、必要に応じてCarbonImmutable型に変換する
 * パフォーマンス最適化のための実装が正しく動作することを確認
 */
class BaseModelDateCastTest extends TestCase
{
    /**
     * getDateAttribute()がstring→CarbonImmutable変換を正しく行うことを確認
     */
    public function test_get_date_attribute_converts_string_to_carbon(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('start_at', '2026-01-01 12:00:00');

        $result = $model->getStartAt();

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertEquals('2026-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    /**
     * getDateAttribute()がnullを正しく扱うことを確認
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
