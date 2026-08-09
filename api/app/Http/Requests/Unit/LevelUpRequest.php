<?php

namespace App\Http\Requests\Unit;

use App\Http\Requests\_BaseRequest;

/**
 * LevelUpRequest
 *
 * ユニットレベルアップAPIのリクエスト
 */
class LevelUpRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trx_unit_id' => ['required', 'integer', 'min:1'],
            'mst_item_id' => ['required', 'string', 'max:255'],
            'use_count' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trx_unit_id.required' => 'ユニットIDは必須です',
            'trx_unit_id.integer' => 'ユニットIDは整数である必要があります',
            'trx_unit_id.min' => 'ユニットIDは1以上である必要があります',
            'mst_item_id.required' => 'アイテムIDは必須です',
            'mst_item_id.string' => 'アイテムIDは文字列である必要があります',
            'use_count.required' => '使用個数は必須です',
            'use_count.integer' => '使用個数は整数である必要があります',
            'use_count.min' => '使用個数は1以上である必要があります',
            'use_count.max' => '使用個数は9999以下である必要があります',
        ];
    }

    /**
     * トランザクションユニットIDを取得
     *
     * @return int trx_unit.id（プレイヤー所有ユニット）
     */
    public function getTrxUnitId(): int
    {
        return (int) $this->input('trx_unit_id');
    }

    /**
     * マスターアイテムIDを取得
     *
     * @return string mst_item.id（マスター定義アイテム）
     */
    public function getMstItemId(): string
    {
        return (string) $this->input('mst_item_id');
    }

    /**
     * 使用個数を取得
     */
    public function getUseCount(): int
    {
        return (int) $this->input('use_count');
    }

    /**
     * 認証済みプレイヤーIDを取得
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        return $this->attributes->get('sys_player_id');
    }
}
