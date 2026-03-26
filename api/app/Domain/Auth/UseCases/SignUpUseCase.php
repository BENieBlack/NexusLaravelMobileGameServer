<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCaseInterface;
use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Exceptions\BusinessLogicException;
use App\Http\Responses\Auth\SignUpResponse;
use App\Traits\UseCaseTrait;

/**
 * SignUpUseCase
 *
 * サインアップのユースケース
 * 新規プレイヤー作成とトークン発行を行う
 * 既存デバイスIDの場合はエラーを返す
 */
class SignUpUseCase implements _BaseUseCaseInterface
{
    use UseCaseTrait;

    public function __construct(
        private readonly PlayerService $playerService,
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * サインアップ処理を実行
     *
     * 新規デバイスIDの場合のみプレイヤーを作成
     * 既存デバイスIDの場合はエラーを返す（sign_inを使用すべき）
     *
     * @param string $deviceId デバイスID
     * @param array $deviceInfo デバイス情報
     * @return SignUpResponse
     * @throws BusinessLogicException|\Throwable 既存デバイスIDの場合
     */
    public function handle(string $deviceId, array $deviceInfo): SignUpResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            // 既存デバイスチェック
            $existing = $this->playerService->findByDeviceId($deviceId);

            if ($existing !== null) {
                // 既存デバイスの場合はエラー（sign_inを使用すべき）
                throw BusinessLogicException::deviceAlreadyExists($deviceId);
            }

            // 新規プレイヤー作成
            [$sysPlayer, $sysPlayerDevice] = $this->playerService->createPlayer($deviceId, $deviceInfo);

            // Token DTO生成
            [$dtoToken, $sysPlayerToken] = $this->tokenService->generateToken($sysPlayer, $sysPlayerDevice);

            // レスポンスを返却（新規作成なので201）
            return new SignUpResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                dtoToken: $dtoToken,
            );
        });
    }
}
