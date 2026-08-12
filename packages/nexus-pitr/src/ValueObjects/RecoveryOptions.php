<?php

namespace NexusPitr\ValueObjects;

/**
 * PITR復旧オプション Value Object
 *
 * 復旧処理の実行条件を保持する不変オブジェクト
 */
final class RecoveryOptions
{
    /**
     * @param  string  $shard  対象シャード接続名（例: trx1）
     * @param  string  $snapshotTime  スナップショット時刻 (Y-m-d H:i:s)
     * @param  string  $targetTime  復旧目標時刻 (Y-m-d H:i:s)
     * @param  int|null  $playerId  対象プレイヤーID（nullの場合は全プレイヤー）
     * @param  string|null  $tableName  対象テーブル（nullの場合は全テーブル）
     * @param  bool  $dryRun  実際には書き込まず内容だけ確認するか
     * @param  bool  $verify  復旧後に検証を行うか
     * @param  int  $batchSize  一度に処理する件数
     *
     * @throws \InvalidArgumentException 値が不正な場合
     */
    public function __construct(
        private readonly string $shard,
        private readonly string $snapshotTime,
        private readonly string $targetTime,
        private readonly ?int $playerId = null,
        private readonly ?string $tableName = null,
        private readonly bool $dryRun = false,
        private readonly bool $verify = false,
        private readonly int $batchSize = 100
    ) {
        if ($shard === '') {
            throw new \InvalidArgumentException('シャード接続名は必須です');
        }

        if ($batchSize < 1) {
            throw new \InvalidArgumentException("バッチサイズは1以上である必要があります: {$batchSize}");
        }

        if ($snapshotTime > $targetTime) {
            throw new \InvalidArgumentException(
                "復旧目標時刻はスナップショット時刻以降である必要があります: {$snapshotTime} > {$targetTime}"
            );
        }
    }

    public function getShard(): string
    {
        return $this->shard;
    }

    public function getSnapshotTime(): string
    {
        return $this->snapshotTime;
    }

    public function getTargetTime(): string
    {
        return $this->targetTime;
    }

    public function getPlayerId(): ?int
    {
        return $this->playerId;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function getDryRun(): bool
    {
        return $this->dryRun;
    }

    public function getVerify(): bool
    {
        return $this->verify;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * 全プレイヤーが対象か
     */
    public function isForAllPlayers(): bool
    {
        return $this->playerId === null;
    }

    /**
     * 全テーブルが対象か
     */
    public function isForAllTables(): bool
    {
        return $this->tableName === null;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->shard === $other->shard
            && $this->snapshotTime === $other->snapshotTime
            && $this->targetTime === $other->targetTime
            && $this->playerId === $other->playerId
            && $this->tableName === $other->tableName
            && $this->dryRun === $other->dryRun
            && $this->verify === $other->verify
            && $this->batchSize === $other->batchSize;
    }
}
