<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamond;
use NexusBilling\Contracts\DiamondRepositoryInterface;
use NexusBilling\DTOs\DiamondBalanceDto;

/**
 * DiamondRepositoryAdapter
 *
 * DiamondRepositoryInterfaceの実装クラス
 * TrxDiamondモデル ↔ DiamondBalanceDto の変換を担当
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
    public function selectByPlatform(int $sysPlayerId, string $platform): ?DiamondBalanceDto
    {
        $trxDiamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);

        return $trxDiamond ? $this->modelToDto($trxDiamond) : null;
    }

    /**
     * プレイヤーIDで全プラットフォームのダイヤモンドを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return array<DiamondBalanceDto>
     */
    public function selectAllByPlayerId(int $sysPlayerId): array
    {
        $trxDiamonds = $this->trxDiamondRepository->selectByPlayerId($sysPlayerId);

        return $trxDiamonds->map(fn (TrxDiamond $trxDiamond) => $this->modelToDto($trxDiamond))->all();
    }

    /**
     * ダイヤモンド残高を保存（新規作成 or 更新）
     */
    public function persistDiamond(DiamondBalanceDto $diamondDto): void
    {
        $trxDiamond = $this->trxDiamondRepository->selectByPlatform(
            $diamondDto->getSysPlayerId(),
            $diamondDto->getPlatform()
        );

        if ($trxDiamond) {
            // 既存レコードを更新
            $trxDiamond->setPaidAmount($diamondDto->getPaidAmount());
            $trxDiamond->setFreeAmount($diamondDto->getFreeAmount());
        } else {
            // 新規レコードを作成
            $trxDiamond = new TrxDiamond([
                'sys_player_id' => $diamondDto->getSysPlayerId(),
                'platform' => $diamondDto->getPlatform(),
                'paid_amount' => $diamondDto->getPaidAmount(),
                'free_amount' => $diamondDto->getFreeAmount(),
            ]);
            $trxDiamond->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む（トランザクションコミット時にDB反映）
        $this->trxDiamondRepository->setModel($trxDiamond);
    }

    /**
     * TrxDiamondモデルをDiamondBalanceDtoに変換
     */
    private function modelToDto(TrxDiamond $trxDiamond): DiamondBalanceDto
    {
        return new DiamondBalanceDto(
            sysPlayerId: $trxDiamond->getAttribute('sys_player_id'),
            platform: $trxDiamond->getAttribute('platform'),
            paidAmount: $trxDiamond->getPaidAmount(),
            freeAmount: $trxDiamond->getFreeAmount(),
        );
    }
}
