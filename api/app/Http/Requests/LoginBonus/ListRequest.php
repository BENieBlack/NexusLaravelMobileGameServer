<?php

namespace App\Http\Requests\LoginBonus;

use App\Http\Requests\_BaseRequest;

/**
 * ListRequest
 *
 * POST /api/login_bonus/list のリクエスト
 */
class ListRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ボディは空（プレイヤーは Bearer トークンから決まる）
        ];
    }
}
