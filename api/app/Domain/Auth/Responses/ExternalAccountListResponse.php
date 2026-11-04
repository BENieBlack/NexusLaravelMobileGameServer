<?php

namespace App\Domain\Auth\Responses;

use App\Domain\_BaseResponse;
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
    final public function __construct(
        public readonly array $sysPlayerExternalAccounts,
    ) {}

    /**
     * @param  CustomCollection<int, SysPlayerExternalAccount>  $sysPlayerExternalAccounts
     */
    public static function fromResult(CustomCollection $sysPlayerExternalAccounts): static
    {
        return new static(array_values($sysPlayerExternalAccounts->all()));
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
