<?php

namespace App\Http\Responses\Mailbox;

use App\Domain\Mailbox\Constants\ContentType;
use App\Domain\Mailbox\Services\TemplateEngine;
use App\Http\Responses\_BaseResponse;
use App\Models\Mst\MstMailboxContent;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Nexus\Core\Support\CustomCollection;

/**
 * ListResponse
 *
 * メールボックス一覧レスポンス
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $mailboxArray  メール1件ずつの表示内容
     * @param  array<string, int>  $unreadCounts  カテゴリ別未読数
     */
    public function __construct(
        private array $mailboxArray,
        private array $unreadCounts = [],
    ) {}

    /**
     * CustomCollectionからレスポンスを生成
     *
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection
     * @param  array<string, int>  $unreadCounts
     */
    public static function fromCollection(
        CustomCollection $trxMailboxCollection,
        array $unreadCounts = []
    ): self {
        // TemplateEngineをインスタンス化
        $templateEngine = app(TemplateEngine::class);

        // 言語はAccept-LanguageからResolveLanguageミドルウェアが決めている
        $language = ApiSession::getLanguage();
        $defaultLanguage = (string) config('language.default');

        $mailboxArray = $trxMailboxCollection->map(function (TrxMailbox $trxMailbox) use ($templateEngine, $language, $defaultLanguage) {
            $mstMailbox = $trxMailbox->mstMailbox;
            $mstMessage = $mstMailbox?->message;

            // 該当言語の文言が無い場合は既定言語にフォールバックする（空文字を返さない）
            $l10n = $mstMessage?->l10n()->where('language', $language)->first()
                ?? ($language === $defaultLanguage
                    ? null
                    : $mstMessage?->l10n()->where('language', $defaultLanguage)->first());

            // テンプレートレンダリング用のコンテキスト
            $context = [
                'player' => $trxMailbox->trxPlayer ?? null,
                // alliance, battle などは必要に応じて追加
            ];

            // カスタムパラメータ取得
            $customParams = $trxMailbox->getCustomParams() ?? [];

            // タイトル・本文をレンダリング
            $title = $templateEngine->render(
                $l10n->title ?? '',
                $customParams,
                $context
            );

            $body = $templateEngine->render(
                $l10n->body ?? '',
                $customParams,
                $context
            );

            // コンテンツ情報を取得
            $contentArray = $mstMailbox?->contentCollection->map(function (MstMailboxContent $content) {
                $contentType = ContentType::fromString($content->content_type);

                return [
                    'content_type' => $content->content_type,
                    'content_type_label' => $contentType?->label() ?? $content->content_type,
                    'content_type_icon' => $contentType?->icon() ?? '',
                    'content_mst_id' => $content->content_mst_id,
                    'amount' => $content->amount,
                    'rarity' => $content->rarity,
                    'is_highlight' => $content->is_highlight ?? false,
                    'sort_desc' => $content->sort_desc,
                ];
            })->toArray() ?? [];

            // カテゴリ・優先度情報
            $category = $mstMailbox?->category;
            $priority = $mstMailbox?->priority;

            return [
                'trx_mailbox_id' => $trxMailbox->id,
                'mst_mailbox_id' => $trxMailbox->mst_mailbox_id,

                // メール内容（テンプレートレンダリング済み）
                'title' => $title,
                'body' => $body,

                // カテゴリ・優先度
                'category' => $category->value ?? null,
                'category_label' => $category?->label() ?? null,
                'category_icon' => $category?->icon() ?? null,
                'priority' => $priority->value ?? null,
                'priority_label' => $priority?->label() ?? null,
                'priority_color' => $priority?->color() ?? null,
                'priority_icon' => $priority?->icon() ?? null,

                // 送信者情報
                'sender_type' => $mstMailbox?->sender_type->value ?? null,
                'sender_type_label' => $mstMailbox?->sender_type?->label() ?? null,
                'sender_id' => $mstMailbox->sender_id ?? null,
                'sender_name' => $trxMailbox->sender_name ?? null,

                // アイコン
                'icon_url' => $mstMailbox->icon_url ?? null,

                // 状態
                'is_opened' => $trxMailbox->is_opened,
                'is_received' => $trxMailbox->is_received,
                'is_protected' => $trxMailbox->is_protected ?? false,
                'is_expired' => $trxMailbox->isExpired(),
                'is_unread' => $trxMailbox->isUnread(),

                // 日時
                'expires_at' => $trxMailbox->getExpiresAt(),
                'read_at' => $trxMailbox->getReadAt(),
                'received_at' => $trxMailbox->getReceivedAt(),
                'created_at' => (string) $trxMailbox->getAttribute('created_at'),

                // 添付物
                'content_array' => $contentArray,
                'has_content' => count($contentArray) > 0,
            ];
        })->toArray();

        return new self($mailboxArray, $unreadCounts);
    }

    /**
     * レスポンス配列を取得
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mailbox_array' => $this->mailboxArray,
            'unread_counts' => $this->unreadCounts,
            'total_unread' => array_sum($this->unreadCounts),
        ];
    }
}
