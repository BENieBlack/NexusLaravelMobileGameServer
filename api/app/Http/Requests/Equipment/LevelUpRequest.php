<?php

namespace App\Http\Requests\Equipment;

use App\Http\Requests\_BaseRequest;

/**
 * LevelUpRequest
 * 
 * 装備レベルアップAPIのリクエスト
 * 指定したafter_levelまで装備をレベルアップする
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
            'trx_equipment_id' => ['required', 'integer', 'min:1'],
            'after_level' => ['required', 'integer', 'min:1', 'max:999'],
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
            'trx_equipment_id.required' => '装備IDは必須です',
            'trx_equipment_id.integer' => '装備IDは整数である必要があります',
            'trx_equipment_id.min' => '装備IDは1以上である必要があります',
            'after_level.required' => '目標レベルは必須です',
            'after_level.integer' => '目標レベルは整数である必要があります',
            'after_level.min' => '目標レベルは1以上である必要があります',
            'after_level.max' => '目標レベルは999以下である必要があります',
        ];
    }

    /**
     * トランザクション装備IDを取得
     * 
     * @return int trx_equipment.id（プレイヤー所有装備）
     */
    public function getTrxEquipmentId(): int
    {
        return (int) $this->input('trx_equipment_id');
    }

    /**
     * 目標レベルを取得
     * 
     * @return int 目標レベル
     */
    public function getAfterLevel(): int
    {
        return (int) $this->input('after_level');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * @return int|null
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        return $this->attributes->get('sys_player_id');
    }
}
