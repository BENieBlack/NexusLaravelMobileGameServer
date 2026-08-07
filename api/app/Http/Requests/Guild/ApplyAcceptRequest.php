<?php

namespace App\Http\Requests\Guild;

use App\Http\Requests\_BaseRequest;

class ApplyAcceptRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'apply_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * 申請IDを取得
     *
     * @return int
     */
    public function getApplyId(): int
    {
        return (int) $this->input('apply_id');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * @return int|null
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        $playerId = $this->input('authenticated_player_id');
        return $playerId ? (int) $playerId : null;
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'apply_id.required' => 'Apply ID is required',
            'apply_id.integer' => 'Apply ID must be an integer',
            'apply_id.min' => 'Apply ID must be at least 1',
        ];
    }

    /**
     * バリデーション属性名のカスタマイズ
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'apply_id' => 'Apply ID',
        ];
    }
}
