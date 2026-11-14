<?php

namespace Nexus\Core\Resource\Services;

use Nexus\Core\Resource\Contracts\DiamondRepositoryInterface;
use Nexus\Core\Resource\DataTransferObjects\DiamondBalance;

/**
 * DiamondService（パッケージ層）
 *
 * 有償ダイヤモンド残高（trx_diamond.paid_amount。platform 別）の管理を担当するサービス。
 * 無償ダイヤは trx_item の free_diamond（mst_item_id）で持つので、ここでは扱わない。
 *
 * Responsibilities:
 * - 有償ダイヤモンド残高の取得
 * - 有償ダイヤモンドの加算
 * - 有償ダイヤモンドの消費（その platform の行だけ）
 *
 * Characteristics:
 * - DTOベースのビジネスロジック
 * - Repository Interfaceに依存（Model非依存）
 * - 他 platform の行は読まず、触らない（有償は端末の platform ごとに分ける）
 */
class DiamondService
{
    public function __construct(
        private readonly DiamondRepositoryInterface $diamondRepository,
    ) {}

    /**
     * 指定 platform の有償ダイヤモンド数を取得
     *
     * 行が無ければ 0 として扱う（サインアップ前・購入前の platform など）。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     */
    public function findPaidAmount(int $sysPlayerId, string $platform): int
    {
        $diamond = $this->diamondRepository->selectByPlatform($sysPlayerId, $platform);

        return $diamond ? $diamond->getPaidAmount() : 0;
    }

    /**
     * 有償ダイヤモンドを加算
     *
     * 行があれば加算し、無ければ作る。amount = 0 でも行を作る（サインアップの初期行）。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  int  $amount  加算する数量
     * @return DiamondBalance 加算後のダイヤモンドDTO
     */
    public function addPaidDiamond(int $sysPlayerId, string $platform, int $amount): DiamondBalance
    {
        $diamond = $this->diamondRepository->selectByPlatform($sysPlayerId, $platform);

        if ($diamond) {
            // 既存レコードがある場合は加算
            $diamond->setPaidAmount($diamond->getPaidAmount() + $amount);
        } else {
            // 新規レコードを作成
            $diamond = new DiamondBalance(
                sysPlayerId: $sysPlayerId,
                platform: $platform,
                paidAmount: $amount,
            );
        }

        $this->diamondRepository->persistDiamond($diamond);

        return $diamond;
    }

    /**
     * 有償ダイヤモンドを消費（その platform の行だけ）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  int  $amount  消費する数量
     *
     * @throws \Exception 残高不足の場合（行が無い場合を含む）
     */
    public function consumePaidDiamond(int $sysPlayerId, string $platform, int $amount): void
    {
        $diamond = $this->diamondRepository->selectByPlatform($sysPlayerId, $platform);
        $current = $diamond ? $diamond->getPaidAmount() : 0;

        if ($diamond === null || $current < $amount) {
            throw new \Exception("有償ダイヤモンド残高が不足しています。必要: {$amount}, 現在: {$current}");
        }

        $diamond->setPaidAmount($current - $amount);
        $this->diamondRepository->persistDiamond($diamond);
    }
}
