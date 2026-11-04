<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\TransferByExternalAccountResponse;
use App\Domain\Auth\Services\AccountTransferService;
use App\Domain\Auth\Support\ExternalIdTokenExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Sys\SysPlayerExternalAccountRepository;
use NexusAuth\Contracts\ExternalIdTokenVerifierInterface;

/**
 * TransferByExternalAccountUseCase
 *
 * 新端末で外部アカウント（Apple / Google）にサインインし、紐付けておいたアカウントを引き継ぐ
 */
class TransferByExternalAccountUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ExternalIdTokenVerifierInterface $externalIdTokenVerifier,
        private readonly SysPlayerExternalAccountRepository $sysPlayerExternalAccountRepository,
        private readonly AccountTransferService $accountTransferService,
    ) {}

    /**
     * @param  string  $provider  apple / google
     * @param  string  $idToken  外部アカウントでサインインして得たIDトークン
     * @param  array<string, mixed>  $deviceInfo
     *
     * @throws GameException トークンが不正、または紐付いたプレイヤーがいない場合
     */
    public function handle(string $provider, string $idToken, string $deviceId, array $deviceInfo): TransferByExternalAccountResponse
    {
        // 外部への問い合わせを含むため、トランザクションの外で検証する
        $providerUserId = ExternalIdTokenExceptionTranslator::translate(
            fn () => $this->externalIdTokenVerifier->verify($provider, $idToken)
        );

        return TransferByExternalAccountResponse::fromResult($this->executeWithTransaction(function () use ($provider, $providerUserId, $deviceId, $deviceInfo) {
            $linked = $this->sysPlayerExternalAccountRepository->selectByProviderUserId($provider, $providerUserId);

            if ($linked === null) {
                throw new GameException(
                    GameErrorCode::EXTERNAL_ACCOUNT_NOT_LINKED,
                    "No player is linked to the {$provider} account"
                );
            }

            $signInResult = $this->accountTransferService->transfer($linked->getSysPlayerId(), $deviceId, $deviceInfo);

            // レスポンスにトークンIDを含めるため、採番を確定させる
            $this->flushQueue();

            return $signInResult;
        }));
    }
}
