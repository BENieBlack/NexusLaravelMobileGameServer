<?php

namespace App\Http\Requests\LoginBonus;

use App\Http\Requests\_BaseRequest;

/**
 * ReceiveRequest
 *
 * POST /api/login_bonus/receive のリクエスト（VIP ログインボーナスの再受け取り）
 */
class ReceiveRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // mst_vip_login_bonus.id は string(64)。今のレベルの ID と一致するかは UseCase が検証する
            'mst_vip_login_bonus_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function getMstVipLoginBonusId(): string
    {
        return (string) $this->input('mst_vip_login_bonus_id');
    }
}
