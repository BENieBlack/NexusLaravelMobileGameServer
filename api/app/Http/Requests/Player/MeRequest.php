<?php

namespace App\Http\Requests\Player;

use App\Http\Requests\_BaseRequest;

class MeRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // 認証ミドルウェアで検証済みのため、ここでは常にtrue
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // GETリクエストでクエリパラメータは不要
        return [];
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * ミドルウェアで設定された値を取得
     */
    public function resolveAuthenticatedPlayerId(): ?int
    {
        return $this->attributes->get('authenticated_player_id');
    }
}
