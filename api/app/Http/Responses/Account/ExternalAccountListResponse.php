<?php

namespace App\Http\Responses\Account;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysPlayerExternalAccount;
use Nexus\Core\Support\CustomCollection;

/**
 * ExternalAccountListResponse
 *
 * 紐付いている外部アカウントの一覧（一覧・紐付け・解除で共通）
 */
class ExternalAccountListResponse extends _BaseResponse
{
    /**
     * @param  list<SysPlayerExternalAccount>  $sysPlayerExternalAccounts
     */
    public function __construct(
        public readonly array $sysPlayerExternalAccounts,
    ) {}

    /**
     * @param  CustomCollection<int, SysPlayerExternalAccount>  $sysPlayerExternalAccounts
     */
    public static function fromCollection(CustomCollection $sysPlayerExternalAccounts): self
    {
        return new self(array_values($sysPlayerExternalAccounts->all()));
    }

    public function toArray(): array
    {
        return [
            'sys_player_external_account_list' => array_map(
                fn (SysPlayerExternalAccount $account) => $account->toResponseArray(),
                $this->sysPlayerExternalAccounts,
            ),
        ];
    }
}
