<?php

namespace App\Domain\Diamond\Services;

use App\Persistence\ApiSession;
use Nexus\Core\ResourceDelivery\Contracts\DefaultPlatformProviderInterface;

/**
 * ApiSessionPlatformProvider
 *
 * ApiSession に載っている X-Platform の値を、パッケージ層（DiamondDeliveryHandler）へ渡す。
 *
 * ApiSession::getPlatform() は未設定だと RuntimeException になる（ジョブ・CLI で暗黙の既定値を
 * 使わせないため）。ここでは hasPlatform() で確かめてから読み、未設定なら null を返して
 * 「例外を投げない」契約を守る。呼び出し側は null のとき Apple にフォールバックする。
 */
class ApiSessionPlatformProvider implements DefaultPlatformProviderInterface
{
    public function findCurrentPlatform(): ?string
    {
        return ApiSession::hasPlatform() ? ApiSession::getPlatform() : null;
    }
}
