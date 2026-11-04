<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\DataTransferObjects\SignInResult;
use App\Domain\Auth\Services\AccountTransferService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Sys\SysPlayerTransferCodeRepository;
use Nexus\Core\Utilities\ClockUtility;

/**
 * TransferByCodeUseCase
 *
 * 新端末で引き継ぎコードとパスワードを入力し、アカウントを引き継ぐ。
 * 使った引き継ぎコードは削除する
 */
class TransferByCodeUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerTransferCodeRepository $sysPlayerTransferCodeRepository,
        private readonly AccountTransferService $accountTransferService,
    ) {}

    /**
     * @param  array<string, mixed>  $deviceInfo
     *
     * @throws GameException コードが無い・パスワードが違う・期限切れ・ロック中の場合
     */
    public function handle(string $transferCode, string $password, string $deviceId, array $deviceInfo): SignInResult
    {
        $result = $this->executeWithTransaction(function () use ($transferCode, $password, $deviceId, $deviceInfo) {
            $sysPlayerTransferCode = $this->sysPlayerTransferCodeRepository->selectByTransferCode(strtoupper($transferCode));

            if ($sysPlayerTransferCode === null) {
                throw new GameException(GameErrorCode::TRANSFER_CODE_INVALID, 'Transfer code or password is invalid');
            }

            if (ClockUtility::isFuture($sysPlayerTransferCode->getLockedUntil())) {
                throw new GameException(
                    GameErrorCode::TRANSFER_CODE_LOCKED,
                    "Transfer code is locked until {$sysPlayerTransferCode->getLockedUntil()}"
                );
            }

            if (ClockUtility::isPast($sysPlayerTransferCode->getExpiresAt())) {
                throw new GameException(GameErrorCode::TRANSFER_CODE_EXPIRED, 'Transfer code has expired');
            }

            if (! password_verify($password, $sysPlayerTransferCode->getPasswordHash())) {
                // 間違えた回数は残したいので、例外で巻き戻さずにコミットしてから知らせる
                $this->sysPlayerTransferCodeRepository->recordFailure(
                    $sysPlayerTransferCode,
                    (int) config('account_transfer.transfer_code.max_failed_attempts'),
                    ClockUtility::now()
                        ->addMinutes((int) config('account_transfer.transfer_code.lock_minutes'))
                        ->format('Y-m-d H:i:s'),
                );

                return null;
            }

            $this->sysPlayerTransferCodeRepository->deleteTransferCode($sysPlayerTransferCode);

            $signInResult = $this->accountTransferService->transfer(
                $sysPlayerTransferCode->getSysPlayerId(),
                $deviceId,
                $deviceInfo,
            );

            // レスポンスにトークンIDを含めるため、採番を確定させる
            $this->flushQueue();

            return $signInResult;
        });

        if ($result === null) {
            throw new GameException(GameErrorCode::TRANSFER_CODE_INVALID, 'Transfer code or password is invalid');
        }

        return $result;
    }
}
