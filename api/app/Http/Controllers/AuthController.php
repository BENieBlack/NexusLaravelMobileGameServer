<?php

namespace App\Http\Controllers;

use App\Domain\Auth\UseCases\RefreshTokenUseCase;
use App\Domain\Auth\UseCases\SignInUseCase;
use App\Domain\Auth\UseCases\SignUpUseCase;
use App\Domain\Auth\UseCases\VersionUseCase;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Requests\Auth\VersionRequest;
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
        return $this->execute(fn() => $useCase->handle($deviceId, $deviceInfo));
    }

    /**
     * サインアップ処理（新規プレイヤー作成）
     */
    public function signUp(SignUpRequest $request, SignUpUseCase $useCase): JsonResponse
    {
        $deviceId = $request->getDeviceId();
        $deviceInfo = $request->getDeviceInfo();
        return $this->execute(fn() => $useCase->handle($deviceId, $deviceInfo));
    }

    /**
     * トークンリフレッシュ処理
     */
    public function refreshToken(RefreshTokenRequest $request, RefreshTokenUseCase $useCase): JsonResponse
    {
        $refreshToken = $request->getRefreshToken();
        return $this->execute(fn() => $useCase->handle($refreshToken));
    }

    /**
     * バージョンチェック処理
     */
    public function version(VersionRequest $request, VersionUseCase $useCase): JsonResponse
    {
        $deployVersion = $request->getDeployVersion();
        return $this->execute(fn() => $useCase->handle($deployVersion));
    }
}
