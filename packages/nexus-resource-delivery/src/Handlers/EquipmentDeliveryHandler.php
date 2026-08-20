<?php

namespace NexusResourceDelivery\Handlers;

use App\Repositories\Trx\TrxEquipmentRepository;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * EquipmentDeliveryHandler
 *
 * 装備配送処理を担当するHandler
 * TrxEquipmentRepositoryを使用して、新規装備を作成
 *
 * 対応リソース:
 * - ResourceType::EQUIPMENT
 * - ResourceType::WEAPON
 * - ResourceType::ARMOR
 * - ResourceType::ACCESSORY
 */
class EquipmentDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {}

    /**
     * 装備配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // 重複所持の変換（DUPLICATED_EQUIPMENT）はここでは行わない。
        // すでに持っている装備をどう扱うか（欠片に変換する・素材にする・そのまま重複させる）は
        // タイトルごとのゲーム仕様のため、Domain層で判定して
        // ResourceDeliveryContent::convertTo() を呼んでから、このHandlerに渡すこと。
        // 実装するときは api/app/Domain 配下に変換ルールを置く（パッケージには入れない）。

        // metadataからlevel/gradeを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $resourceDeliveryContent->getMetadata();
        $level = $metadata['level'] ?? null;
        $grade = $metadata['grade'] ?? null;

        // 指定された数量分の装備を作成
        for ($i = 0; $i < $resourceDeliveryContent->getAmount(); $i++) {
            $this->trxEquipmentRepository->insertEquipment(
                $resourceDeliveryContent->getId(),
                $level,
                $grade
            );
        }
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;

        return in_array($typeValue, [
            ResourceType::EQUIPMENT->value,
            ResourceType::WEAPON->value,
            ResourceType::ARMOR->value,
            ResourceType::ACCESSORY->value,
        ]);
    }
}
