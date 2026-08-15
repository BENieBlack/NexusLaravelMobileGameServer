<?php

namespace NexusGacha\Repositories;

use NexusGacha\DataTransferObjects\GachaProgress;

/**
 * GachaProgressRepositoryInterface
 * 
 * ガチャ進行状況データへのアクセスを抽象化
 */
interface GachaProgressRepositoryInterface
{
    /**
     * プレイヤーとガチャIDで進行状況を取得
     * 
     * @param int $sysPlayerId
     * @param string $mstGachaId
     * @return GachaProgress|null
     */
    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgress;

    /**
     * 進行状況を保存
     * 
     * @param GachaProgress $gachaProgressDto
     * @return void
     */
    public function persist(GachaProgress $gachaProgressDto): void;

    /**
     * 新規進行状況を作成
     * 
     * @param GachaProgress $gachaProgressDto
     * @return GachaProgress
     */
    public function insert(GachaProgress $gachaProgressDto): GachaProgress;
}
