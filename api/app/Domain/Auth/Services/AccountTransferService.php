<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DataTransferObjects\SignInResult;
use App\Domain\Auth\Traits\BuildsSysPlayerToken;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use NexusAuth\Services\TokenService;

/**
 * AccountTransferService
 *
 * 引き継ぎ先の端末をプレイヤーに付け替え、新しいトークンを発行する。
 * 引き継ぎコードと外部アカウントのどちらで本人確認しても、ここから先は同じ。
 *
 * - 引き継ぎ先の端末が別のプレイヤーで遊んでいたら、その端末を引き継ぐプレイヤーへ付け替える
 *   （元のプレイヤーはその端末からはサインインできなくなる）
 * - 引き継ぐプレイヤーの他の端末は削除し、発行済みのトークンも全て消す。
 *   旧端末から同じアカウントで遊び続けられないようにするため
 */
class AccountTransferService
{
    use BuildsSysPlayerToken;

    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
        private readonly SysPlayerTokenRepository $sysPlayerTokenRepository,
        private readonly TokenService $tokenService,
    ) {}

    /**
     * 端末をプレイヤーに付け替えてトークンを発行する
     *
     * 呼び出し側は、レスポンスにトークンIDを含めるため flushQueue() で採番を確定させること
     *
     * @param  string  $deviceId  引き継ぎ先の端末のデバイスID
     * @param  array<string, mixed>  $deviceInfo  引き継ぎ先の端末の情報
     *
     * @throws GameException プレイヤーが存在しない場合
     */
    public function transfer(int $sysPlayerId, string $deviceId, array $deviceInfo): SignInResult
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        if ($sysPlayer === null) {
            throw new GameException(GameErrorCode::PLAYER_NOT_FOUND, "Player not found: {$sysPlayerId}");
        }

        $sysPlayerDevice = $this->sysPlayerDeviceRepository->selectByDeviceId($deviceId);

        // 旧端末を外す。引き継ぎ先の端末が既にこのプレイヤーのものなら残す
        foreach ($this->sysPlayerDeviceRepository->selectListByPlayerId($sysPlayerId) as $oldDevice) {
            if ($sysPlayerDevice !== null && $oldDevice->getId() === $sysPlayerDevice->getId()) {
                continue;
            }

            $this->sysPlayerDeviceRepository->hardDeleteModel($oldDevice);
        }

        $this->sysPlayerTokenRepository->deleteByPlayerId($sysPlayerId);

        if ($sysPlayerDevice === null) {
            $sysPlayerDevice = $this->sysPlayerDeviceRepository->insertDevice($sysPlayerId, $deviceId, $deviceInfo);
        } else {
            if ($sysPlayerDevice->getSysPlayerId() !== $sysPlayerId) {
                // 端末で遊んでいた別のプレイヤーのトークンは使えなくする
                $this->sysPlayerTokenRepository->revokeDeviceTokens($sysPlayerDevice->getId());
                $sysPlayerDevice->setSysPlayerId($sysPlayerId);
            }

            $sysPlayerDevice->setDeviceInfo($deviceInfo);
            $sysPlayerDevice->markLastLoginAt();
            $this->sysPlayerDeviceRepository->setModel($sysPlayerDevice);
        }

        [$token, $sysPlayerToken] = $this->tokenService->generateToken(
            $sysPlayer,
            $sysPlayerDevice,
            fn ($playerId, $deviceId, $tokenHash, $expiresAt) => $this->newSysPlayerToken(
                $playerId,
                $deviceId,
                $tokenHash,
                $expiresAt
            )
        );

        return new SignInResult(
            sysPlayer: $sysPlayer,
            sysPlayerDevice: $sysPlayerDevice,
            sysPlayerToken: $sysPlayerToken,
            token: $token,
        );
    }
}
