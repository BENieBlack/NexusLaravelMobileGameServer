<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;
use Illuminate\Database\Eloquent\Collection;

/**
 * ListResponse
 *
 * メールボックス一覧レスポンス
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param array $mailboxArray
     */
    public function __construct(
        private array $mailboxArray,
    ) {
    }

    /**
     * Collectionからレスポンスを生成
     *
     * @param Collection $trxMailboxCollection
     * @return self
     */
    public static function fromCollection(Collection $trxMailboxCollection): self
    {
        $mailboxArray = $trxMailboxCollection->map(function ($trxMailbox) {
            $mstMailbox = $trxMailbox->mstMailbox;
            $mstMessage = $mstMailbox?->message;
            
            // TODO: 言語設定はリクエストヘッダーから取得する必要がある
            // 現時点では固定で'ja'を使用
            $language = 'ja';
            $i18n = $mstMessage?->i18n()->where('language', $language)->first();

            // コンテンツ情報を取得
            $contentArray = $mstMailbox?->contentCollection->map(function ($content) {
                return [
                    'content_type' => $content->content_type,
                    'content_id' => $content->content_id,
                    'amount' => $content->amount,
                    'sort_desc' => $content->sort_desc,
                ];
            })->toArray() ?? [];

            return [
                'trx_mailbox_id' => $trxMailbox->id,
                'mst_mailbox_id' => $trxMailbox->mst_mailbox_id,
                'title' => $i18n?->title ?? '',
                'body' => $i18n?->body ?? '',
                'is_opened' => $trxMailbox->is_opened,
                'is_received' => $trxMailbox->is_received,
                'content_array' => $contentArray,
                'created_at' => $trxMailbox->created_at->toIso8601String(),
            ];
        })->toArray();

        return new self($mailboxArray);
    }

    /**
     * レスポンス配列を取得
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'mailbox_array' => $this->mailboxArray,
        ];
    }
}
