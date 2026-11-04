<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\ExternalAccountListResponse;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;

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

    public function handle(int $sysPlayerId): ExternalAccountListResponse
    {
        return ExternalAccountListResponse::from($this->sysPlayerExternalAccountRepository->selectListByPlayerId($sysPlayerId));
    }
}
