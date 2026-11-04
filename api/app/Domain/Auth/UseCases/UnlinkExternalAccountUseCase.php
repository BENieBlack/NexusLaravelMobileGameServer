<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\UnlinkExternalAccountResponse;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;

/**
 * UnlinkExternalAccountUseCase
 *
 * ログイン中のプレイヤーから外部アカウントの紐付けを外す
 */
class UnlinkExternalAccountUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerExternalAccountRepository $sysPlayerExternalAccountRepository,
    ) {}

    /**
     * @param  string  $provider  apple / google
     *
     * @throws GameException 紐付いていない場合
     */
    public function handle(int $sysPlayerId, string $provider): UnlinkExternalAccountResponse
    {
        $sysPlayerExternalAccounts = $this->executeWithTransaction(function () use ($sysPlayerId, $provider) {
            $linked = $this->sysPlayerExternalAccountRepository->selectByPlayerIdAndProvider($sysPlayerId, $provider);

            if ($linked === null) {
                throw new GameException(GameErrorCode::EXTERNAL_ACCOUNT_NOT_LINKED, "No {$provider} account is linked");
            }

            $this->sysPlayerExternalAccountRepository->deleteExternalAccount($linked);

            return $this->sysPlayerExternalAccountRepository->selectListByPlayerId($sysPlayerId);
        });

        return UnlinkExternalAccountResponse::from($sysPlayerExternalAccounts);
    }
}
