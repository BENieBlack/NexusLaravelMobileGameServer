<?php

namespace App\Http\Requests\Friend;

use App\Http\Requests\_BaseRequest;

class ApplyListRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // 認証済みユーザーのみ
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
            // 申請リスト取得には追加パラメータ不要
        ];
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * ミドルウェアで設定された値を取得
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        $playerId = $this->input('authenticated_player_id');

        return $playerId ? (int) $playerId : null;
    }
}
