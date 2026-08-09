<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\BusinessLogicException;
use App\Http\Responses\Auth\SignUpResponse;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use Throwable;

/**
 * AuthSignUpUseCase
 *
 * サインアップのユースケース
 * 新規プレイヤー作成とトークン発行を行う
 * 既存デバイスIDの場合はエラーを返す
 */
class AuthSignUpUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly PlayerAuthService $playerAuthService,
        private readonly TokenService $tokenService,
        private readonly DeviceRepositoryInterface $deviceRepository,
        private readonly TokenRepositoryInterface $tokenRepository,
    ) {}

    /**
     * サインアップ処理を実行
     *
     * 新規デバイスIDの場合のみプレイヤーを作成
     * 既存デバイスIDの場合はエラーを返す（sign_inを使用すべき）
     *
     * @param  string  $deviceId  デバイスID
     * @param  array  $deviceInfo  デバイス情報
     *
     * @throws BusinessLogicException|Throwable 既存デバイスIDの場合
     */
    public function exec(string $deviceId, array $deviceInfo): SignUpResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            // 既存デバイスチェック
            $existingDevice = $this->deviceRepository->selectByDeviceId($deviceId);

            if ($existingDevice !== null) {
                // 既存デバイスの場合はエラー（sign_inを使用すべき）
                throw BusinessLogicException::deviceAlreadyExists($deviceId);
            }

            // 新規プレイヤー作成（PlayerAuthService使用）
            $player = $this->playerAuthService->createPlayer($deviceId, $deviceInfo);

            // デバイスを作成（アプリケーション層で直接作成）
            $sysPlayerDevice = SysPlayerDevice::create([
                'sys_player_id' => $player->getId(),
                'uuid' => $deviceId,
                'device_info' => $deviceInfo,
                'last_login_at' => now(),
            ]);

            // Token DTO生成（NexusAuth\Services\TokenService使用）
            [$tokenDto, $sysPlayerToken] = $this->tokenService->generateToken(
                $player,
                $sysPlayerDevice,
                fn ($playerId, $deviceId, $tokenHash, $expiresAt) => SysPlayerToken::create([
                    'sys_player_id' => $playerId,
                    'sys_player_device_id' => $deviceId,
                    'refresh_token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ])
            );

            // レスポンスを返却（新規作成なので201）
            return new SignUpResponse(
                sysPlayer: $player,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                tokenDto: $tokenDto,
            );
        });
    }
}
