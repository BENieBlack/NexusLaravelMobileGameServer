<?php

namespace App\Domain\Mailbox\Services;

use App\Models\Mst\MstMessage;
use App\Models\Mst\MstMessageL10n;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Nexus\Core\Support\CustomCollection;

/**
 * MailboxMessageService
 *
 * メールの表示文言（タイトル・本文）を決める。
 * 多言語の文言の引き当てと、テンプレートのレンダリングだけを担う（業務・表示ロジック）。
 * API の形（キー名・ラベル・アイコンの付与）に整えるのは Response の責務なので、ここでは行わない。
 */
class MailboxMessageService
{
    public function __construct(
        private readonly TemplateEngine $templateEngine,
    ) {}

    /**
     * メールごとのタイトルと本文を、リクエストの言語でレンダリングして返す
     *
     * @param  CustomCollection<int, TrxMailbox>  $trxMailboxCollection
     * @return array<int, array{title: string, body: string}> trx_mailbox.id をキーにした文言
     */
    public function renderMessages(CustomCollection $trxMailboxCollection): array
    {
        // 言語はAccept-LanguageからResolveLanguageミドルウェアが決めている
        $language = ApiSession::getLanguage();
        $defaultLanguage = (string) config('language.default');

        $messages = [];
        foreach ($trxMailboxCollection as $trxMailbox) {
            $messages[$trxMailbox->getId()] = $this->renderOne($trxMailbox, $language, $defaultLanguage);
        }

        return $messages;
    }

    /**
     * @return array{title: string, body: string}
     */
    private function renderOne(TrxMailbox $trxMailbox, string $language, string $defaultLanguage): array
    {
        $mstMessage = $trxMailbox->mstMailbox?->message;

        // 該当言語の文言が無い場合は既定言語にフォールバックする（空文字を返さない）。
        // l10n は一覧の取得時にまとめて読んであるので、ここでは SELECT しない
        $l10n = $this->findL10n($mstMessage, $language)
            ?? ($language === $defaultLanguage ? null : $this->findL10n($mstMessage, $defaultLanguage));

        // テンプレートレンダリング用のコンテキスト（alliance, battle などは必要に応じて追加）
        $context = ['player' => $trxMailbox->trxPlayer ?? null];
        $customParams = $trxMailbox->getCustomParams() ?? [];

        return [
            'title' => $this->templateEngine->render($l10n?->getTitle() ?? '', $customParams, $context),
            'body' => $this->templateEngine->render($l10n?->getBody() ?? '', $customParams, $context),
        ];
    }

    private function findL10n(?MstMessage $mstMessage, string $language): ?MstMessageL10n
    {
        return $mstMessage?->l10n->first(fn (MstMessageL10n $l10n) => $l10n->getLanguage() === $language);
    }
}
