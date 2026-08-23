<?php

namespace NexusPlayer\Contracts;

/**
 * PlayerModelInterface
 *
 * プレイヤーモデルの抽象インターフェース
 * アプリケーション側のEloquentモデル（SysPlayer等）が実装する
 *
 * このパッケージにはプレイヤーを扱う境界が2つある。
 * - こちら（Contracts/）: Eloquentモデルをそのままやりとりする。
 *   作成直後の採番済みIDが要る経路や、モデルをレスポンスまで持ち回る
 *   認証系（nexus-core-auth）が使う。
 * - Repositories/PlayerRepositoryInterface: DataTransferObjects\Player を
 *   やりとりする。パッケージ側のゲームロジック（レベル進行など）が使う。
 */
interface PlayerModelInterface
{
    /**
     * プレイヤーID取得
     */
    public function getId(): int;

    /**
     * プレイヤーUUID取得
     */
    public function getUuid(): string;

    /**
     * 作成日時取得
     *
     * @return string Y-m-d H:i:s形式
     */
    public function getCreatedAt(): string;
}
