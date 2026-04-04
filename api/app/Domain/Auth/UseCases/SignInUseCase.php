<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;
use App\Http\Responses\Auth\SignInResponse;

/**
 * SignInUseCase
 *
 * サインインのユースケース
 * 既存デバイスIDでのログイン処理
 * 新しいアクセストークンとリフレッシュトークンを発行
 */
class SignInUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly PlayerService $playerService,
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * サインイン処理を実行
     *
     * 既存デバイスIDで新しいトークンを発行
     *
     * @param string $deviceId デバイスID
     * @param array $deviceInfo デバイス情報（現在未使用だが将来的な拡張のため保持）
     * @return SignInResponse
     * @throws \App\Exceptions\GameException デバイスIDが存在しない場合
     * @throws \Exception|\Throwable
     */
    public function handle(string $deviceId, array $deviceInfo): SignInResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId) {
            // デバイスとプレイヤーを取得
            $sysPlayerDevice = $this->playerService->selectByDeviceId($deviceId);

            if ($sysPlayerDevice === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    "Device ID not found: {$deviceId}. Please use sign_up endpoint."
                );
            }

            // プレイヤー情報を取得
            $sysPlayer = $this->playerService->selectById($sysPlayerDevice->sys_player_id);

            if ($sysPlayer === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    "Player not found for device: {$deviceId}"
                );
            }

            // 古いトークンを無効化
            $this->tokenService->revokeDeviceTokens($sysPlayerDevice);

            // Token DTO生成
            [$dtoToken, $sysPlayerToken] = $this->tokenService->generateToken($sysPlayer, $sysPlayerDevice);

            // 最終ログイン日時を更新
            $this->playerService->updateLastLogin($sysPlayerDevice);

            return new SignInResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                dtoToken: $dtoToken,
            );
        });
    }
}
