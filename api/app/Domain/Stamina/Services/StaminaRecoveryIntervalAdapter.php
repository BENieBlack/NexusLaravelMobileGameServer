<?php

namespace App\Domain\Stamina\Services;

use App\Domain\MstConfig\Contracts\MstConfigReaderInterface;
use NexusStamina\Services\StaminaRecoveryIntervalProviderInterface;

/**
 * StaminaRecoveryIntervalAdapter
 *
 * MstConfig の回復間隔を nexus-stamina の読み出し口に適合させるアダプタ。
 *
 * 既定値・型検査・警告ログは MstConfig 側（MstConfigReaderInterface の実装）が持つので、ここでは値を取るだけ。
 * 1 リクエスト内では最初の値を保持する（scoped で束縛）。同じリクエストで
 * 何度呼ばれても警告ログは 1 回で、MstConfig の読み出しも増えない。
 */
class StaminaRecoveryIntervalAdapter implements StaminaRecoveryIntervalProviderInterface
{
    private ?int $intervalSeconds = null;

    public function __construct(
        private readonly MstConfigReaderInterface $mstConfigReader,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findRecoveryIntervalSeconds(): int
    {
        return $this->intervalSeconds ??= $this->mstConfigReader->findStaminaRecoveryIntervalSeconds();
    }
}
