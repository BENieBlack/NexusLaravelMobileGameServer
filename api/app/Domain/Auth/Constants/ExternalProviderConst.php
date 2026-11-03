<?php

namespace App\Domain\Auth\Constants;

/**
 * ExternalProviderConst
 *
 * データ引き継ぎに使える外部アカウントの種類（sys_player_external_account.provider）
 */
class ExternalProviderConst
{
    public const APPLE = 'apple';

    public const GOOGLE = 'google';

    /** @var list<string> */
    public const ALL = [self::APPLE, self::GOOGLE];
}
