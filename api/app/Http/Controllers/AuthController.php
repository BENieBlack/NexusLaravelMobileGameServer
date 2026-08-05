<?php

namespace App\Http\Controllers;

use App\Domain\Login\UseCases\LoginHomeUseCase;
use App\Domain\Auth\UseCases\AuthRefreshTokenUseCase;
use App\Domain\Auth\UseCases\AuthSignInUseCase;
use App\Domain\Auth\UseCases\AuthSignUpUseCase;
use App\Domain\Version\UseCases\VersionCheckUseCase;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Requests\Auth\VersionRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

class AuthController extends _BaseController
{
    /**
     * サインイン処理（既存デバイスIDでのログイン）
     */
    public function signIn(SignInRequest $request, AuthSignInUseCase $useCase): JsonResponse
    {
        $deviceId = $request->getDeviceId();
        $deviceInfo = $request->getDeviceInfo();
        return $this->execute(fn() => $useCase->exec($deviceId, $deviceInfo));
    }

    /**
     * サインアップ処理（新規プレイヤー作成）
     */
    public function signUp(SignUpRequest $request, AuthSignUpUseCase $useCase): JsonResponse
    {
        \Log::info('AuthController::signUp called', [
            'device_id' => $request->input('device_id'),
        ]);
        
        $deviceId = $request->getDeviceId();
        $deviceInfo = $request->getDeviceInfo();
        
        \Log::info('AuthController::signUp executing use case');
        
        return $this->execute(fn() => $useCase->exec($deviceId, $deviceInfo));
    }

    /**
     * トークンリフレッシュ処理
     */
    public function refreshToken(RefreshTokenRequest $request, AuthRefreshTokenUseCase $useCase): JsonResponse
    {
        $refreshToken = $request->getRefreshToken();
        return $this->execute(fn() => $useCase->exec($refreshToken));
    }

    /**
     * バージョンチェック処理
     */
    public function version(VersionRequest $request, VersionCheckUseCase $useCase): JsonResponse
    {
        $deployVersion = $request->getDeployVersion();
        return $this->execute(fn() => $useCase->exec($deployVersion));
    }

    /**
     * ログイン処理（認証済みプレイヤーのログインボーナス配布とユーザー情報取得）
     */
    public function login(LoginRequest $request, LoginHomeUseCase $useCase): JsonResponse
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        return $this->execute(fn() => $useCase->exec($sysPlayerId));
    }
}
