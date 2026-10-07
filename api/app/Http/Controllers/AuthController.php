<?php

namespace App\Http\Controllers;

use App\Domain\Auth\UseCases\RefreshTokenUseCase;
use App\Domain\Auth\UseCases\SignInUseCase;
use App\Domain\Auth\UseCases\SignUpUseCase;
use App\Domain\Login\UseCases\HomeUseCase;
use App\Domain\Version\UseCases\CheckUseCase;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Requests\Auth\VersionRequest;
use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Http\Responses\Auth\SignInResponse;
use App\Http\Responses\Auth\SignUpResponse;
use App\Http\Responses\Auth\VersionResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

class AuthController extends _BaseController
{
    /**
     * サインイン処理（既存デバイスIDでのログイン）
     */
    public function signIn(SignInRequest $request, SignInUseCase $useCase): JsonResponse
    {
        $deviceId = $request->getDeviceId();
        $deviceInfo = $request->getDeviceInfo();

        return $this->execute(fn () => SignInResponse::fromResult($useCase->exec($deviceId, $deviceInfo)));
    }

    /**
     * サインアップ処理（新規プレイヤー作成）
     */
    public function signUp(SignUpRequest $request, SignUpUseCase $useCase): JsonResponse
    {
        \Log::info('AuthController::signUp called', [
            'device_id' => $request->input('device_id'),
        ]);

        $deviceId = $request->getDeviceId();
        $deviceInfo = $request->getDeviceInfo();

        \Log::info('AuthController::signUp executing use case');

        return $this->execute(fn () => SignUpResponse::fromResult($useCase->exec($deviceId, $deviceInfo)));
    }

    /**
     * トークンリフレッシュ処理
     */
    public function refreshToken(RefreshTokenRequest $request, RefreshTokenUseCase $useCase): JsonResponse
    {
        $refreshToken = $request->getRefreshToken();

        return $this->execute(fn () => new RefreshTokenResponse(token: $useCase->exec($refreshToken)));
    }

    /**
     * バージョンチェック処理
     */
    public function version(VersionRequest $request, CheckUseCase $useCase): JsonResponse
    {
        $deployVersion = $request->resolveDeployVersion();

        return $this->execute(fn () => VersionResponse::fromResult($useCase->exec($deployVersion)));
    }

    /**
     * ログイン処理（認証済みプレイヤーのログインボーナス配布とユーザー情報取得）
     */
    public function login(LoginRequest $request, HomeUseCase $useCase): JsonResponse
    {
        $sysPlayerId = ApiSession::getSysPlayerId();

        return $this->execute(fn () => LoginResponse::fromResult($useCase->exec($sysPlayerId)));
    }
}
