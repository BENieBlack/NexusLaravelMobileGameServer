<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Repositories\Trx\TrxUnitRepository;

/**
 * UnitDeliveryHandler
 * 
 * ユニット配送処理を担当するHandler
 * TrxUnitRepositoryを使用して、新規ユニットを作成
 */
class UnitDeliveryHandler implements _BaseDeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxUnitRepository $trxUnitRepository,
    ) {
    }

    /**
     * ユニット配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID（後方互換性のため保持、ApiSessionから自動取得）
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // metadataからgradeとlevelを取得（指定がない場合はnull = デフォルト値を使用）
        $grade = $content->metadata['grade'] ?? null;
        $level = $content->metadata['level'] ?? null;

        // 指定された数量分のユニットを作成
        for ($i = 0; $i < $content->amount; $i++) {
            $this->trxUnitRepository->createUnit(
                $content->id,
                $grade,
                $level
            );
        }
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ
     * @return bool
     */
    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_UNIT;
    }
}
