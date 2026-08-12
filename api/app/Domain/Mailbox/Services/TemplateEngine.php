<?php

namespace App\Domain\Mailbox\Services;

use App\Domain\Mailbox\Services\Placeholders\AlliancePlaceholder;
use App\Domain\Mailbox\Services\Placeholders\BattlePlaceholder;
use App\Domain\Mailbox\Services\Placeholders\PlayerPlaceholder;
use NexusMailbox\Services\Template\_BaseTemplateEngine;
use NexusMailbox\Services\Template\Resolvers\SystemPlaceholder;

/**
 * TemplateEngine
 *
 * メールテンプレートのプレースホルダー置換エンジン
 *
 * Package層の_BaseTemplateEngineを継承し、ゲーム固有のResolverを登録
 *
 * 登録Resolver:
 * - SystemPlaceholder (Package層) - システム情報
 * - PlayerPlaceholder (Game固有) - プレイヤー情報
 * - AlliancePlaceholder (Game固有) - アライアンス情報
 * - BattlePlaceholder (Game固有) - バトル情報
 */
class TemplateEngine extends _BaseTemplateEngine
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        // ゲーム固有のResolverを登録
        $this->registerResolver(new SystemPlaceholder(
            serverName: config('app.name', 'Game Server'),
            version: config('app.version', '1.0.0')
        ));
        $this->registerResolver(new PlayerPlaceholder);
        $this->registerResolver(new AlliancePlaceholder);
        $this->registerResolver(new BattlePlaceholder);
    }
}
