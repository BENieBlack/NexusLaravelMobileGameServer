<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Sys\SysPlayerExternalAccount;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;
use Nexus\Core\Support\CustomCollection;

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
     * @return CustomCollection<int, SysPlayerExternalAccount> 解除後の一覧
     *
     * @throws GameException 紐付いていない場合
     */
    public function handle(int $sysPlayerId, string $provider): CustomCollection
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $provider) {
            $linked = $this->sysPlayerExternalAccountRepository->selectByPlayerIdAndProvider($sysPlayerId, $provider);

            if ($linked === null) {
                throw new GameException(GameErrorCode::EXTERNAL_ACCOUNT_NOT_LINKED, "No {$provider} account is linked");
            }

            $this->sysPlayerExternalAccountRepository->deleteExternalAccount($linked);

            return $this->sysPlayerExternalAccountRepository->selectListByPlayerId($sysPlayerId);
        });
    }
}
