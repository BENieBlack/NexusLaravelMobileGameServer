<?php

namespace App\Domain\Mailbox\Responses;

use App\Domain\_BaseResponse;
use App\Domain\Mailbox\DataTransferObjects\MailboxItem;
use App\Domain\Mailbox\DataTransferObjects\MailboxItemContent;

/**
 * ListResponse
 *
 * メールボックス一覧レスポンス
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  list<MailboxItem>  $mailboxItems  メール1件ずつの表示内容
     * @param  array<string, int>  $unreadCounts  カテゴリ別未読数
     */
    public function __construct(
        private array $mailboxItems,
        private array $unreadCounts = [],
    ) {}

    /**
     * UseCaseの結果（メールの表示内容と未読数）からレスポンスを生成
     *
     * @param  list<MailboxItem>  $mailboxItems
     * @param  array<string, int>  $unreadCounts
     */
    public static function from(array $mailboxItems, array $unreadCounts = []): self
    {
        return new self($mailboxItems, $unreadCounts);
    }

    /**
     * レスポンス配列を取得
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mailbox_list' => array_map(fn (MailboxItem $item) => $this->itemToArray($item), $this->mailboxItems),
            'unread_counts' => $this->unreadCounts,
            'total_unread' => array_sum($this->unreadCounts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemToArray(MailboxItem $item): array
    {
        $contentList = array_map(fn (MailboxItemContent $content) => [
            'content_type' => $content->contentType,
            'content_type_label' => $content->contentTypeLabel,
            'content_type_icon' => $content->contentTypeIcon,
            'content_mst_id' => $content->contentMstId,
            'amount' => $content->amount,
            'rarity' => $content->rarity,
            'is_highlight' => $content->isHighlight,
            'sort_desc' => $content->sortDesc,
        ], $item->contents);

        return [
            'trx_mailbox_id' => $item->trxMailboxId,
            'mst_mailbox_id' => $item->mstMailboxId,

            // メール内容（テンプレートレンダリング済み）
            'title' => $item->title,
            'body' => $item->body,

            // カテゴリ・優先度
            'category' => $item->category?->value,
            'category_label' => $item->category?->label(),
            'category_icon' => $item->category?->icon(),
            'priority' => $item->priority?->value,
            'priority_label' => $item->priority?->label(),
            'priority_color' => $item->priority?->color(),
            'priority_icon' => $item->priority?->icon(),

            // 送信者情報
            'sender_type' => $item->senderType?->value,
            'sender_type_label' => $item->senderType?->label(),
            'sender_id' => $item->senderId,
            'sender_name' => $item->senderName,

            // アイコン
            'icon_url' => $item->iconUrl,

            // 状態
            'is_opened' => $item->isOpened,
            'is_received' => $item->isReceived,
            'is_protected' => $item->isProtected,
            'is_expired' => $item->isExpired,
            'is_unread' => $item->isUnread,

            // 日時
            'expires_at' => $item->expiresAt,
            'read_at' => $item->readAt,
            'received_at' => $item->receivedAt,
            'created_at' => $item->createdAt,

            // 添付物
            'content_list' => $contentList,
            'has_content' => count($contentList) > 0,
        ];
    }
}
