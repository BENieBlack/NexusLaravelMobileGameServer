<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerTransferCode;

/**
 * SysPlayerTransferCodeRepository
 *
 * 引き継ぎコードの永続化のみを担当
 * 発行・照合のビジネスロジックは App\Domain\Auth の UseCase に実装
 *
 * @extends _BaseSysRepository<SysPlayerTransferCode>
 */
class SysPlayerTransferCodeRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayerTransferCode::class;

    /**
     * ログイン中プレイヤーの引き継ぎコードを取得（まだ発行していなければnull）
     */
    public function selectByPlayerId(int $sysPlayerId): ?SysPlayerTransferCode
    {
        /** @var SysPlayerTransferCode|null */
        return $this->queryOrMemory()
            ->first(fn (SysPlayerTransferCode $code) => $code->getSysPlayerId() === $sysPlayerId);
    }

    /**
     * 引き継ぎコードから取得（キャッシュを通さない）
     *
     * 引き継ぎは認証前に行うため、自分スコープを使えない
     */
    public function selectByTransferCode(string $transferCode): ?SysPlayerTransferCode
    {
        /** @var SysPlayerTransferCode|null */
        return $this->selectWithoutCache()->where('transfer_code', $transferCode)->first();
    }

    /**
     * 引き継ぎコードが既に使われているか
     */
    public function existsTransferCode(string $transferCode): bool
    {
        return $this->selectWithoutCache()->where('transfer_code', $transferCode)->exists();
    }

    /**
     * 引き継ぎコードを発行する（発行済みなら上書きし、失敗回数とロックを解く）
     *
     * @param  string  $expiresAt  有効期限（Y-m-d H:i:s形式）
     */
    public function upsertTransferCode(
        int $sysPlayerId,
        string $transferCode,
        string $passwordHash,
        string $expiresAt,
    ): SysPlayerTransferCode {
        $sysPlayerTransferCode = $this->selectByPlayerId($sysPlayerId);

        if ($sysPlayerTransferCode === null) {
            $sysPlayerTransferCode = new SysPlayerTransferCode(['sys_player_id' => $sysPlayerId]);
            $sysPlayerTransferCode->exists = false;
        }

        $sysPlayerTransferCode->setTransferCode($transferCode);
        $sysPlayerTransferCode->setPasswordHash($passwordHash);
        $sysPlayerTransferCode->setFailedCount(0);
        $sysPlayerTransferCode->setLockedUntil(null);
        $sysPlayerTransferCode->setExpiresAt($expiresAt);

        $this->setModel($sysPlayerTransferCode);

        return $sysPlayerTransferCode;
    }

    /**
     * パスワードの間違いを数える。上限に達したらロックし、回数を数え直す
     *
     * @param  string  $lockedUntil  上限に達したときのロック解除日時（Y-m-d H:i:s形式）
     */
    public function recordFailure(
        SysPlayerTransferCode $sysPlayerTransferCode,
        int $maxFailedAttempts,
        string $lockedUntil,
    ): void {
        $failedCount = $sysPlayerTransferCode->getFailedCount() + 1;

        if ($failedCount >= $maxFailedAttempts) {
            $sysPlayerTransferCode->setLockedUntil($lockedUntil);
            $failedCount = 0;
        }

        $sysPlayerTransferCode->setFailedCount($failedCount);

        $this->setModel($sysPlayerTransferCode);
    }

    /**
     * 使い終わった引き継ぎコードを削除する
     */
    public function deleteTransferCode(SysPlayerTransferCode $sysPlayerTransferCode): void
    {
        $this->hardDeleteModel($sysPlayerTransferCode);
    }
}
