<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\ReceiveResponse;
use App\Repositories\Trx\TrxMailboxRepository;
use NexusPersistence\Support\CustomCollection;

/**
 * MailboxReceiveUseCase
 *
 * 添付配布物受取処理
 */
class MailboxReceiveUseCase extends _BaseUseCase
{

    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
        private ResourceDeliveryService $resourceDeliveryService,
    ) {
    }

    /**
     * 添付配布物を受け取る
     *
     * @param int $sysPlayerId
     * @param int $trxMailboxId
     * @return ReceiveResponse
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
            $contentCollection = $mstMailbox?->contentCollection ?? new CustomCollection();

            // Resource配列に変換
            $resources = $contentCollection->map(function ($content) {
                return ResourceDto::fromTypeString(
                    strtolower($content->getContentType()), // Diamond -> diamond
                    $content->getContentId(),
                    $content->getAmount(),
                );
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
}
