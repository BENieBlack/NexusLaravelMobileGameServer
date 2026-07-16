<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use NexusResource\DTOs\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use App\Domain\MailBox\Constants\Category;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\ReceiveAllResponse;
use App\Repositories\Trx\TrxMailboxRepository;
use Carbon\Carbon;

/**
 * ReceiveAllUseCase
 *
 * 複数メールの添付物一括受取処理
 */
class ReceiveAllUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
        private ResourceDeliveryService $resourceDeliveryService,
    ) {
    }

    /**
     * 複数メールの添付物を一括で受け取る
     *
     * @param int $sysPlayerId
     * @param array<int>|null $trxMailboxIds 受取対象のメールID配列（nullの場合は全て）
     * @param string|null $category カテゴリフィルタ
     * @return ReceiveAllResponse
     * @throws GameException
     */
    public function handle(
        int $sysPlayerId, 
        ?array $trxMailboxIds = null,
        ?string $category = null
    ): ReceiveAllResponse {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxIds, $category) {
            // カテゴリEnum変換
            $categoryEnum = $category !== null ? Category::fromString($category) : null;

            // 受取対象メール一覧を取得
            if ($trxMailboxIds !== null && count($trxMailboxIds) > 0) {
                // 指定IDのメールを取得
                $trxMailboxCollection = collect($trxMailboxIds)
                    ->map(fn($id) => $this->trxMailboxRepository->selectById($id))
                    ->filter(fn($m) => $m !== null && $m->getSysPlayerId() === $sysPlayerId);
            } else {
                // 全ての未受取メールを取得
                $trxMailboxCollection = $this->trxMailboxRepository->selectByPlayerId(
                    $sysPlayerId,
                    $categoryEnum,
                    null,
                    false,
                    false
                )->filter(fn($m) => !$m->getIsReceived() && !$m->isExpired());
            }

            if ($trxMailboxCollection->isEmpty()) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'No mailbox to receive');
            }

            // 全ての添付物を集約
            $allResources = [];
            $receivedMailboxIds = [];
            $totalMailCount = 0;
            $skippedCount = 0;

            foreach ($trxMailboxCollection as $trxMailbox) {
                // 既に受取済みならスキップ
                if ($trxMailbox->getIsReceived()) {
                    $skippedCount++;
                    continue;
                }

                // 期限切れならスキップ
                if ($trxMailbox->isExpired()) {
                    $skippedCount++;
                    continue;
                }

                // マスターデータから添付物を取得
                $mstMailbox = $trxMailbox->mstMailbox;
                $contentCollection = $mstMailbox?->contentCollection ?? collect();

                if ($contentCollection->isEmpty()) {
                    // 添付物がなくても受取済みにする
                    $this->trxMailboxRepository->markAsReceived($trxMailbox);
                    $receivedMailboxIds[] = $trxMailbox->getId();
                    $totalMailCount++;
                    continue;
                }

                // Resource配列に変換して集約
                foreach ($contentCollection as $content) {
                    $resource = Resource::fromTypeString(
                        strtolower($content->getContentType()),
                        $content->getContentId(),
                        $content->getAmount(),
                    );
                    $allResources[] = $resource;
                }

                $receivedMailboxIds[] = $trxMailbox->getId();
                $totalMailCount++;
            }

            // 配送処理
            $deliverySummary = null;
            if (count($allResources) > 0) {
                $this->resourceDeliveryService->addResources($allResources);
                $deliverySummary = $this->resourceDeliveryService->deliver($sysPlayerId);

                if ($deliverySummary->getTotalCount() === 0) {
                    throw new GameException(GameErrorCode::INTERNAL_ERROR, 'Failed to deliver mailbox contents');
                }
            }

            // 全て受取済みにする
            foreach ($trxMailboxCollection as $trxMailbox) {
                if (in_array($trxMailbox->getId(), $receivedMailboxIds)) {
                    $this->trxMailboxRepository->markAsReceived($trxMailbox);
                }
            }

            return new ReceiveAllResponse(
                receivedMailboxIds: $receivedMailboxIds,
                totalCount: $totalMailCount,
                skippedCount: $skippedCount,
                deliveryContents: $allResources,
                deliverySummary: $deliverySummary
            );
        });
    }
}
