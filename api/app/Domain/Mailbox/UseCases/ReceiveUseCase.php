<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Mailbox\Constants\ContentType;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\ReceiveResponse;
use App\Models\Mst\MstMailboxContent;
use App\Repositories\Trx\TrxMailboxRepository;
use Nexus\Core\Support\CustomCollection;
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * ReceiveUseCase
 *
 * 添付配布物受取処理
 */
class ReceiveUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
        private ResourceDeliveryService $resourceDeliveryService,
    ) {}

    /**
     * 添付配布物を受け取る
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $trxMailboxId): ReceiveResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId) {
            // メールボックス取得
            $trxMailbox = $this->trxMailboxRepository->selectById($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 既に受取済みの場合
            if ($trxMailbox->getIsReceived()) {
                throw new GameException(GameErrorCode::MAILBOX_ALREADY_RECEIVED, 'Mailbox already received');
            }

            // マスターデータから添付物を取得
            $mstMailbox = $trxMailbox->mstMailbox;
            $contentCollection = $mstMailbox->contentCollection ?? new CustomCollection;

            // Resource配列に変換
            $resources = $contentCollection->map(function ($content) {
                return $this->toResource($content);
            })->toArray();

            // 配送処理（新しいパターン: addResources + deliver）
            if (count($resources) > 0) {
                $this->resourceDeliveryService->addResources($resources);
                $deliverySummary = $this->resourceDeliveryService->deliver($sysPlayerId);

                if ($deliverySummary->getTotalCount() === 0) {
                    throw new GameException(GameErrorCode::INTERNAL_ERROR, 'Failed to deliver mailbox content');
                }
            }

            // 受取済みにする
            $this->trxMailboxRepository->markAsReceived($trxMailbox);

            return new ReceiveResponse($trxMailbox->getId(), true, $resources);
        });
    }

    /**
     * メールの添付物を配送用のリソースへ変換する
     *
     * 種別はパスカルケースで入っているため、小文字化ではなく
     * ContentType 経由で写す（PaidDiamond → paid_diamond）。
     * 配布量は content_quantity × amount。
     */
    private function toResource(MstMailboxContent $content): Resource
    {
        $contentType = ContentType::fromString($content->getContentType());

        if ($contentType === null) {
            throw new GameException(
                GameErrorCode::MASTER_DATA_NOT_FOUND,
                "Unknown mailbox content type: {$content->getContentType()}"
            );
        }

        return new Resource(
            type: $contentType->toResourceType(),
            id: $content->getContentMstId(),
            amount: $content->getTotalQuantity(),
        );
    }
}
