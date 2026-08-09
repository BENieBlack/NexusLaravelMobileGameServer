<?php

namespace App\Http\Requests\Guild;

use App\Http\Requests\_BaseRequest;

class ApplySendRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
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
            'guild_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * ギルドIDを取得
     */
    public function getGuildId(): int
    {
        return (int) $this->input('guild_id');
    }

    /**
     * 認証済みプレイヤーIDを取得
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
            'guild_id.required' => 'Guild ID is required',
            'guild_id.integer' => 'Guild ID must be an integer',
            'guild_id.min' => 'Guild ID must be at least 1',
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
            'guild_id' => 'Guild ID',
        ];
    }
}
