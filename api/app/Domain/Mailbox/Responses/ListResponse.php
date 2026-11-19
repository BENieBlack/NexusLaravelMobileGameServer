<?php

namespace App\Domain\Mailbox\Responses;

use App\Domain\_BaseResponse;
use App\Domain\Mailbox\Constants\ContentType;
use App\Models\Mst\MstMailboxContent;
use App\Models\Trx\TrxMailbox;
use Nexus\Core\Support\CustomCollection;

/**
 * ListResponse
 *
 * メールボックス一覧レスポンス
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection  メールボックスの行（mst_mailbox・添付を読み込み済み）
     * @param  array<int, array{title: string, body: string}>  $messages  trx_mailbox.id をキーにした、描画済みのタイトル・本文
     * @param  array<string, int>  $unreadCounts  カテゴリ別未読数
     */
    public function __construct(
        private CustomCollection $trxMailboxCollection,
        private array $messages,
        private array $unreadCounts = [],
    ) {}

    /**
     * メールボックスの行・描画済みの文言・未読数からレスポンスを生成
     *
     * $messages は、同じ $trxMailboxCollection から作ったものであること
     * （ID が食い違うと、存在しないキーを参照して未定義キーになる）
     *
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection
     * @param  array<int, array{title: string, body: string}>  $messages
     * @param  array<string, int>  $unreadCounts
     */
    public static function from(CustomCollection $trxMailboxCollection, array $messages, array $unreadCounts = []): self
    {
        return new self($trxMailboxCollection, $messages, $unreadCounts);
    }

    /**
     * レスポンス配列を取得
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $mailboxList = [];
        foreach ($this->trxMailboxCollection as $trxMailbox) {
            $mailboxList[] = $this->mailboxToArray($trxMailbox, $this->messages[$trxMailbox->getId()]);
        }

        return [
            'mailbox_list' => $mailboxList,
            'unread_counts' => $this->unreadCounts,
            'total_unread' => array_sum($this->unreadCounts),
        ];
    }

    /**
     * メール1件を API の形にする（ラベル・アイコンはここで enum から引く）
     *
     * @param  array{title: string, body: string}  $message  描画済みのタイトル・本文
     * @return array<string, mixed>
     */
    private function mailboxToArray(TrxMailbox $trxMailbox, array $message): array
    {
        $mstMailbox = $trxMailbox->mstMailbox;
        $category = $mstMailbox?->getCategory();
        $priority = $mstMailbox?->getPriority();
        $senderType = $mstMailbox?->getSenderType();

        $contentList = [];
        foreach ($mstMailbox->contentCollection ?? [] as $mstMailboxContent) {
            /** @var MstMailboxContent $mstMailboxContent */
            $contentType = ContentType::fromString($mstMailboxContent->getContentType());

            $contentList[] = [
                'content_type' => $mstMailboxContent->getContentType(),
                // 未知の種別はラベルに種別そのものを、アイコンは空文字を返す
                'content_type_label' => $contentType?->label() ?? $mstMailboxContent->getContentType(),
                'content_type_icon' => $contentType?->icon() ?? '',
                'content_mst_id' => $mstMailboxContent->getContentMstId(),
                'amount' => $mstMailboxContent->getAmount(),
                'rarity' => $mstMailboxContent->getRarity(),
                'is_highlight' => $mstMailboxContent->getIsHighlight(),
                'sort_desc' => $mstMailboxContent->getSortDesc(),
            ];
        }

        return [
            'trx_mailbox_id' => $trxMailbox->getId(),
            'mst_mailbox_id' => $trxMailbox->getMstMailboxId(),

            // メール内容（テンプレートレンダリング済み）
            'title' => $message['title'],
            'body' => $message['body'],

            // カテゴリ・優先度
            'category' => $category?->value,
            'category_label' => $category?->label(),
            'category_icon' => $category?->icon(),
            'priority' => $priority?->value,
            'priority_label' => $priority?->label(),
            'priority_color' => $priority?->color(),
            'priority_icon' => $priority?->icon(),

            // 送信者情報
            'sender_type' => $senderType?->value,
            'sender_type_label' => $senderType?->label(),
            'sender_id' => $mstMailbox?->getSenderId(),
            'sender_name' => $trxMailbox->getSenderName(),

            // アイコン
            'icon_url' => $mstMailbox?->getIconUrl(),

            // 状態
            'is_opened' => $trxMailbox->getIsOpened(),
            'is_received' => $trxMailbox->getIsReceived(),
            'is_protected' => $trxMailbox->getIsProtected(),
            'is_expired' => $trxMailbox->isExpired(),
            'is_unread' => $trxMailbox->isUnread(),

            // 日時
            'expires_at' => $trxMailbox->getExpiresAt(),
            'read_at' => $trxMailbox->getReadAt(),
            'received_at' => $trxMailbox->getReceivedAt(),
            'created_at' => $trxMailbox->getCreatedAt(),

            // 添付物
            'content_list' => $contentList,
            'has_content' => count($contentList) > 0,
        ];
    }
}
