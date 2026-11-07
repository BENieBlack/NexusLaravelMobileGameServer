<?php

namespace App\Domain\Mailbox\Services;

use App\Domain\Mailbox\Constants\ContentType;
use App\Domain\Mailbox\DataTransferObjects\MailboxItem;
use App\Domain\Mailbox\DataTransferObjects\MailboxItemContent;
use App\Models\Mst\MstMailboxContent;
use App\Models\Mst\MstMessage;
use App\Models\Mst\MstMessageL10n;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Nexus\Core\Support\CustomCollection;

/**
 * MailboxItemAssemblyService
 *
 * メールボックスの行（trx_mailbox）から、表示用の MailboxItem を組み立てる。
 * 文言（多言語）の引き当てとテンプレートのレンダリングは、ここで済ませる。
 */
class MailboxItemAssemblyService
{
    public function __construct(
        private readonly TemplateEngine $templateEngine,
    ) {}

    /**
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection
     * @return list<MailboxItem>
     */
    public function assemble(CustomCollection $trxMailboxCollection): array
    {
        // 言語はAccept-LanguageからResolveLanguageミドルウェアが決めている
        $language = ApiSession::getLanguage();
        $defaultLanguage = (string) config('language.default');

        $items = [];
        foreach ($trxMailboxCollection as $trxMailbox) {
            $items[] = $this->assembleOne($trxMailbox, $language, $defaultLanguage);
        }

        return $items;
    }

    private function assembleOne(TrxMailbox $trxMailbox, string $language, string $defaultLanguage): MailboxItem
    {
        $mstMailbox = $trxMailbox->mstMailbox;
        $mstMessage = $mstMailbox?->message;

        // 該当言語の文言が無い場合は既定言語にフォールバックする（空文字を返さない）。
        // l10n は一覧の取得時にまとめて読んであるので、ここでは SELECT しない
        $l10n = $this->findL10n($mstMessage, $language)
            ?? ($language === $defaultLanguage ? null : $this->findL10n($mstMessage, $defaultLanguage));

        // テンプレートレンダリング用のコンテキスト（alliance, battle などは必要に応じて追加）
        $context = ['player' => $trxMailbox->trxPlayer ?? null];
        $customParams = $trxMailbox->getCustomParams() ?? [];

        $contents = [];
        foreach ($mstMailbox->contentCollection ?? [] as $mstMailboxContent) {
            /** @var MstMailboxContent $mstMailboxContent */
            $contentType = ContentType::fromString($mstMailboxContent->getContentType());

            $contents[] = new MailboxItemContent(
                contentType: $mstMailboxContent->getContentType(),
                contentTypeLabel: $contentType?->label() ?? $mstMailboxContent->getContentType(),
                contentTypeIcon: $contentType?->icon() ?? '',
                contentMstId: $mstMailboxContent->getContentMstId(),
                amount: $mstMailboxContent->getAmount(),
                rarity: $mstMailboxContent->getRarity(),
                isHighlight: $mstMailboxContent->getIsHighlight(),
                sortDesc: $mstMailboxContent->getSortDesc(),
            );
        }

        return new MailboxItem(
            trxMailboxId: $trxMailbox->getId(),
            mstMailboxId: $trxMailbox->getMstMailboxId(),
            title: $this->templateEngine->render($l10n?->getTitle() ?? '', $customParams, $context),
            body: $this->templateEngine->render($l10n?->getBody() ?? '', $customParams, $context),
            category: $mstMailbox?->getCategory(),
            priority: $mstMailbox?->getPriority(),
            senderType: $mstMailbox?->getSenderType(),
            senderId: $mstMailbox?->getSenderId(),
            senderName: $trxMailbox->getSenderName(),
            iconUrl: $mstMailbox?->getIconUrl(),
            isOpened: $trxMailbox->getIsOpened(),
            isReceived: $trxMailbox->getIsReceived(),
            isProtected: $trxMailbox->getIsProtected(),
            isExpired: $trxMailbox->isExpired(),
            isUnread: $trxMailbox->isUnread(),
            expiresAt: $trxMailbox->getExpiresAt(),
            readAt: $trxMailbox->getReadAt(),
            receivedAt: $trxMailbox->getReceivedAt(),
            createdAt: $trxMailbox->getCreatedAt(),
            contents: $contents,
        );
    }

    private function findL10n(?MstMessage $mstMessage, string $language): ?MstMessageL10n
    {
        return $mstMessage?->l10n->first(fn (MstMessageL10n $l10n) => $l10n->getLanguage() === $language);
    }
}
