<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamond;
use NexusBilling\Contracts\DiamondRepositoryInterface;
use NexusBilling\DataTransferObjects\DiamondBalance;

/**
 * DiamondRepositoryAdapter
 *
 * DiamondRepositoryInterfaceの実装クラス
 * TrxDiamondモデル ↔ DiamondBalance の変換を担当
 */
class DiamondRepositoryAdapter implements DiamondRepositoryInterface
{
    public function __construct(
        private readonly TrxDiamondRepository $trxDiamondRepository,
    ) {}

    /**
     * プレイヤーIDとプラットフォームでダイヤモンドを検索
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     */
    public function selectByPlatform(int $sysPlayerId, string $platform): ?DiamondBalance
    {
        $trxDiamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);

        return $trxDiamond ? $this->modelToDto($trxDiamond) : null;
    }

    /**
     * プレイヤーIDで全プラットフォームのダイヤモンドを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return array<DiamondBalance>
     */
    public function selectAllByPlayerId(int $sysPlayerId): array
    {
        $trxDiamonds = $this->trxDiamondRepository->selectByPlayerId($sysPlayerId);

        return $trxDiamonds->map(fn (TrxDiamond $trxDiamond) => $this->modelToDto($trxDiamond))->all();
    }

    /**
     * ダイヤモンド残高を保存（新規作成 or 更新）
     */
    public function persistDiamond(DiamondBalance $diamond): void
    {
        $trxDiamond = $this->trxDiamondRepository->selectByPlatform(
            $diamond->getSysPlayerId(),
            $diamond->getPlatform()
        );

        if ($trxDiamond) {
            // 既存レコードを更新
            $trxDiamond->setPaidAmount($diamond->getPaidAmount());
            $trxDiamond->setFreeAmount($diamond->getFreeAmount());
        } else {
            // 新規レコードを作成
            $trxDiamond = new TrxDiamond([
                'sys_player_id' => $diamond->getSysPlayerId(),
                'platform' => $diamond->getPlatform(),
                'paid_amount' => $diamond->getPaidAmount(),
                'free_amount' => $diamond->getFreeAmount(),
            ]);
            $trxDiamond->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む（トランザクションコミット時にDB反映）
        $this->trxDiamondRepository->setModel($trxDiamond);
    }

    /**
     * TrxDiamondモデルをDiamondBalanceに変換
     */
    private function modelToDto(TrxDiamond $trxDiamond): DiamondBalance
    {
        return new DiamondBalance(
            sysPlayerId: $trxDiamond->getAttribute('sys_player_id'),
            platform: $trxDiamond->getAttribute('platform'),
            paidAmount: $trxDiamond->getPaidAmount(),
            freeAmount: $trxDiamond->getFreeAmount(),
        );
    }
}
