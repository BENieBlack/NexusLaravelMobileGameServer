<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Support\ExternalIdTokenExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Sys\SysPlayerExternalAccount;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;
use Nexus\Core\Support\CustomCollection;
use NexusAuth\Contracts\ExternalIdTokenVerifierInterface;

/**
 * LinkExternalAccountUseCase
 *
 * ログイン中のプレイヤーに外部アカウント（Apple / Google）を紐付ける
 */
class LinkExternalAccountUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ExternalIdTokenVerifierInterface $externalIdTokenVerifier,
        private readonly SysPlayerExternalAccountRepository $sysPlayerExternalAccountRepository,
    ) {}

    /**
     * @param  string  $provider  apple / google
     * @param  string  $idToken  外部アカウントでサインインして得たIDトークン
     * @return CustomCollection<int, SysPlayerExternalAccount> 紐付け後の一覧
     *
     * @throws GameException トークンが不正、または紐付けられない場合
     */
    public function exec(int $sysPlayerId, string $provider, string $idToken): CustomCollection
    {
        // 外部への問い合わせを含むため、トランザクションの外で検証する
        $providerUserId = ExternalIdTokenExceptionTranslator::translate(
            fn () => $this->externalIdTokenVerifier->verify($provider, $idToken)
        );

        return $this->executeWithTransaction(function () use ($sysPlayerId, $provider, $providerUserId) {
            $linked = $this->sysPlayerExternalAccountRepository->selectByProviderUserId($provider, $providerUserId);

            if ($linked !== null && $linked->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(
                    GameErrorCode::EXTERNAL_ACCOUNT_LINKED_TO_OTHER_PLAYER,
                    "The {$provider} account is already linked to another player"
                );
            }

            // 同じ外部アカウントを紐付け直すのは何もしない
            if ($linked === null) {
                if ($this->sysPlayerExternalAccountRepository->selectByPlayerIdAndProvider($sysPlayerId, $provider) !== null) {
                    throw new GameException(
                        GameErrorCode::EXTERNAL_PROVIDER_ALREADY_LINKED,
                        "Another {$provider} account is already linked. Unlink it first"
                    );
                }

                $this->sysPlayerExternalAccountRepository->insertExternalAccount($sysPlayerId, $provider, $providerUserId);
            }

            return $this->sysPlayerExternalAccountRepository->selectListByPlayerId($sysPlayerId);
        });
    }
}
