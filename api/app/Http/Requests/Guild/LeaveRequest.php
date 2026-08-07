<?php

namespace App\Http\Requests\Guild;

use App\Http\Requests\_BaseRequest;

class LeaveRequest extends _BaseRequest
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
        return [];
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
}
