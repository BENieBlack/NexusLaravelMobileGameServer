<?php

namespace App\Http\Responses\Auth;

use NexusResource\DTOs\Resource;
use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysPlayer;

/**
 * LoginResponse
 * 
 * ログインAPIのレスポンス
 * プレイヤー情報、所持ユニット、所持アイテム、ウォレット、ログインボーナス情報を返す
 */
class LoginResponse extends _BaseResponse
{
    /**
     * @param SysPlayer $sysPlayer プレイヤー情報
     * @param array $trxUnits 所持ユニット一覧
     * @param array $trxItems 所持アイテム一覧
     * @param array $trxWallets ウォレット一覧
     * @param array<Resource> $loginBonusContents ログインボーナス内容
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly array $trxUnits,
        public readonly array $trxItems,
        public readonly array $trxWallets,
        public readonly array $loginBonusContents,
    ) {}

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sys_player' => $this->sysPlayer->toResponseArray(),
            'trx_unit_list' => array_map(
                fn($unit) => $unit->toResponseArray(),
                $this->trxUnits
            ),
            'trx_item_list' => array_map(
                fn($item) => $item->toResponseArray(),
                $this->trxItems
            ),
            'trx_wallet_list' => array_map(
                fn($wallet) => $wallet->toResponseArray(),
                $this->trxWallets
            ),
            'login_bonus_list' => array_map(
                fn(Resource $resource) => $resource->toArray(),
                $this->loginBonusContents
            ),
        ];
    }
}
