<?php

namespace App\Domain\Login\Responses;

use App\Domain\_BaseResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Trx\TrxDiamond;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;
use App\Models\Trx\TrxStamina;
use App\Models\Trx\TrxUnit;
use App\Models\Trx\TrxWallet;
use Nexus\Core\Resource\DataTransferObjects\Resource;

/**
 * HomeResponse
 *
 * ログインAPIのレスポンス
 * プレイヤーの現在値（対象の全テーブル）とログインボーナス情報を返す。現在値を渡す API なので、updated_resources ではなく全量を返す
 */
class HomeResponse extends _BaseResponse
{
    /**
     * @param  SysPlayer  $sysPlayer  プレイヤー情報
     * @param  array<int, TrxUnit>  $trxUnits  所持ユニット一覧
     * @param  array<int, TrxItem>  $trxItems  所持アイテム一覧
     * @param  array<int, TrxWallet>  $trxWallets  ウォレット一覧
     * @param  array<int, TrxDiamond>  $trxDiamonds  ダイヤ（プラットフォームごと）
     * @param  array<int, TrxEquipment>  $trxEquipments  所持装備一覧
     * @param  array<int, TrxStamina>  $trxStaminas  スタミナ（自然回復を加算した現在値）
     * @param  array<int, \Nexus\Core\Resource\DataTransferObjects\Resource>  $loginBonusContents  ログインボーナス内容
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly array $trxUnits,
        public readonly array $trxItems,
        public readonly array $trxWallets,
        public readonly array $trxDiamonds,
        public readonly array $trxEquipments,
        public readonly array $trxStaminas,
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
                fn ($unit) => $unit->toResponseArray(),
                $this->trxUnits
            ),
            'trx_item_list' => array_map(
                fn ($item) => $item->toResponseArray(),
                $this->trxItems
            ),
            'trx_wallet_list' => array_map(
                fn ($wallet) => $wallet->toResponseArray(),
                $this->trxWallets
            ),
            'trx_diamond_list' => array_map(
                fn ($diamond) => $diamond->toResponseArray(),
                $this->trxDiamonds
            ),
            'trx_equipment_list' => array_map(
                fn ($equipment) => $equipment->toResponseArray(),
                $this->trxEquipments
            ),
            'trx_stamina_list' => array_map(
                fn ($stamina) => $stamina->toResponseArray(),
                $this->trxStaminas
            ),
            'login_bonus_list' => array_map(
                fn (Resource $resource) => $resource->toArray(),
                $this->loginBonusContents
            ),
        ];
    }
}
