<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysFriendApply;
use Illuminate\Database\Eloquent\Collection;

/**
 * SysFriendApplyRepository
 *
 * フレンド申請情報のRepository実装
 *
 * @extends _BaseSysRepository<SysFriendApply>
 */
class SysFriendApplyRepository extends _BaseSysRepository
{
    protected string $modelClass = SysFriendApply::class;

    /**
     * 自分が出した申請と、自分宛の申請の両方が「自分の行」
     *
     * @var list<string>
     */
    protected array $selfScopeKeys = ['sender_sys_player_id', 'receiver_sys_player_id'];

    // ========================================
    // 以下、内部用のModelベースメソッド（既存の実装）
    // ========================================

    /**
     * 申請者と受信者のペアで既存の申請を検索（双方向チェック）
     *
     * @param  int  $applyPlayerId  申請者のプレイヤーID
     * @param  int  $receivePlayerId  受信者のプレイヤーID
     */
    public function selectByPlayerPair(int $applyPlayerId, int $receivePlayerId): ?SysFriendApply
    {
        // 申請者→受信者、または受信者→申請者の双方向で検索
        if ($this->isSessionPlayer($applyPlayerId) || $this->isSessionPlayer($receivePlayerId)) {
            /** @var SysFriendApply|null */
            return $this->queryOrMemory()->first(
                fn (SysFriendApply $apply) => $this->isPair($apply, $applyPlayerId, $receivePlayerId)
                    && in_array($apply->getStatus(), [
                        SysFriendApply::STATUS_APPLIED,
                        SysFriendApply::STATUS_ACCEPTED,
                    ], true)
            );
        }

        return $this->selectWithoutCache()
            ->where(function ($query) use ($applyPlayerId, $receivePlayerId) {
                $query->where('sender_sys_player_id', $applyPlayerId)
                    ->where('receiver_sys_player_id', $receivePlayerId);
            })
            ->orWhere(function ($query) use ($applyPlayerId, $receivePlayerId) {
                $query->where('sender_sys_player_id', $receivePlayerId)
                    ->where('receiver_sys_player_id', $applyPlayerId);
            })
            ->whereIn('status', [
                SysFriendApply::STATUS_APPLIED,
                SysFriendApply::STATUS_ACCEPTED,
            ])
            ->first();
    }

    /**
     * プレイヤーIDで受信したフレンド申請一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<int, SysFriendApply>
     */
    public function selectReceivedApplies(int $playerId): Collection
    {
        return $this->selectWithoutCache()
            ->where('receiver_sys_player_id', $playerId)
            ->where('status', SysFriendApply::STATUS_APPLIED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * プレイヤーIDで送信したフレンド申請一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<int, SysFriendApply>
     */
    public function selectSentApplies(int $playerId): Collection
    {
        return $this->selectWithoutCache()
            ->where('sender_sys_player_id', $playerId)
            ->where('status', SysFriendApply::STATUS_APPLIED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * プレイヤーIDに関連するフレンド申請一覧を取得
     * sender_sys_player_idまたはreceiver_sys_player_idが自分で、
     * statusがAppliedのものを取得
     *
     * 申請行そのものは自分の行だが、表示に相手プレイヤーを
     * まとめて引くためキャッシュは通さない。
     * 相手の行は自分スコープの外なので更新してはいけない。
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<int, SysFriendApply>
     */
    public function selectAppliesByPlayerId(int $playerId): Collection
    {
        return $this->selectWithoutCache()
            ->with(['sendPlayer', 'receivePlayer'])
            ->where(function ($query) use ($playerId) {
                $query->where('sender_sys_player_id', $playerId)
                    ->orWhere('receiver_sys_player_id', $playerId);
            })
            ->where('status', SysFriendApply::STATUS_APPLIED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * プレイヤーIDに関連する承認済みフレンド一覧を取得
     * sender_sys_player_idまたはreceiver_sys_player_idが自分で、
     * statusがAcceptedのものを取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<int, SysFriendApply>
     */
    public function selectAcceptedFriendsByPlayerId(int $playerId): Collection
    {
        return $this->selectWithoutCache()
            ->with(['sendPlayer', 'receivePlayer'])
            ->where(function ($query) use ($playerId) {
                $query->where('sender_sys_player_id', $playerId)
                    ->orWhere('receiver_sys_player_id', $playerId);
            })
            ->where('status', SysFriendApply::STATUS_ACCEPTED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * フレンド申請を作成
     *
     * @param  int  $applyPlayerId  申請者のプレイヤーID
     * @param  int  $receivePlayerId  受信者のプレイヤーID
     */
    public function insertApply(int $applyPlayerId, int $receivePlayerId): SysFriendApply
    {
        $sysFriendApply = new SysFriendApply([
            'sender_sys_player_id' => $applyPlayerId,
            'receiver_sys_player_id' => $receivePlayerId,
            'status' => SysFriendApply::STATUS_APPLIED,
        ]);
        $sysFriendApply->exists = false;

        $this->setModel($sysFriendApply);

        // 呼び出し元が採番済みのIDとタイムスタンプを参照するため、ここでフラッシュする
        $this->flushQueue();

        return $sysFriendApply;
    }

    /**
     * フレンド関係を削除（論理削除） - 内部用Modelメソッド
     *
     * プレイヤーIDと相手プレイヤーIDから、承認済みフレンド関係を削除する
     *
     * @param  int  $playerId  削除実行者のプレイヤーID
     * @param  int  $targetPlayerId  削除対象のプレイヤーID
     * @return SysFriendApply|null 削除されたフレンド関係、見つからない場合null
     */
    public function deleteFriendRelationModel(int $playerId, int $targetPlayerId): ?SysFriendApply
    {
        // 双方向で検索（自分がsenderまたはreceiver）
        $friendRelation = $this->modelClass::query()
            ->where(function ($query) use ($playerId, $targetPlayerId) {
                $query->where('sender_sys_player_id', $playerId)
                    ->where('receiver_sys_player_id', $targetPlayerId);
            })
            ->orWhere(function ($query) use ($playerId, $targetPlayerId) {
                $query->where('sender_sys_player_id', $targetPlayerId)
                    ->where('receiver_sys_player_id', $playerId);
            })
            ->where('status', SysFriendApply::STATUS_ACCEPTED)
            ->first();

        if (! $friendRelation) {
            return null;
        }

        // sys_friend_applyにis_deleteカラムはないため物理削除する
        $this->hardDeleteModel($friendRelation);

        return $friendRelation;
    }

    /**
     * 申請がこの2人の間のものか（向きは問わない）
     */
    private function isPair(SysFriendApply $apply, int $onePlayerId, int $otherPlayerId): bool
    {
        $sender = $apply->getSenderSysPlayerId();
        $receiver = $apply->getReceiverSysPlayerId();

        return ($sender === $onePlayerId && $receiver === $otherPlayerId)
            || ($sender === $otherPlayerId && $receiver === $onePlayerId);
    }
}
