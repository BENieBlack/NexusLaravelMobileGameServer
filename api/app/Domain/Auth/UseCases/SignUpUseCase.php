<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Exceptions\BusinessLogicException;
use App\Http\Responses\Auth\SignUpResponse;

/**
 * SignUpUseCase
 *
 * サインアップのユースケース
 * 新規プレイヤー作成とトークン発行を行う
 * 既存デバイスIDの場合はエラーを返す
 */
class SignUpUseCase extends _BaseUseCase
{

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
            $existing = $this->playerService->selectByDeviceId($deviceId);

            if ($existing !== null) {
                // 既存デバイスの場合はエラー（sign_inを使用すべき）
                throw BusinessLogicException::deviceAlreadyExists($deviceId);
            }

            // 新規プレイヤー作成（Repository内でプレイヤーとデバイスを作成してIDを取得）
            $result = $this->playerService->createPlayer($deviceId, $deviceInfo);
            $sysPlayer = $result['sys_player'];
            $sysPlayerDevice = $result['sys_player_device'];

            // Token DTO生成（Repository内でトークンを作成してIDを取得）
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
