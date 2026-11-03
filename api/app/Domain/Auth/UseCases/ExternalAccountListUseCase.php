<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Models\Sys\SysPlayerExternalAccount;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;
use Nexus\Core\Support\CustomCollection;

/**
 * ExternalAccountListUseCase
 *
 * ログイン中のプレイヤーに紐付いている外部アカウントの一覧
 */
class ExternalAccountListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerExternalAccountRepository $sysPlayerExternalAccountRepository,
    ) {}

    /**
     * @return CustomCollection<int, SysPlayerExternalAccount>
     */
    public function exec(int $sysPlayerId): CustomCollection
    {
        return $this->sysPlayerExternalAccountRepository->selectListByPlayerId($sysPlayerId);
    }
}
