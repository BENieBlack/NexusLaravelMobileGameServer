<?php

namespace App\Repositories\Sys;


use NexusPersistence\Support\CustomCollection;
use App\Models\Sys\SysFriendApply;

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
     * IDでフレンド申請を検索
     *
     * @param int $sysFriendApplyId フレンド申請ID
     * @return SysFriendApply|null
     */
    public function selectById(int $sysFriendApplyId): ?SysFriendApply
    {
        $sysFriendApply = $this->getModel($sysFriendApplyId);
        
        if ($sysFriendApply !== null) {
            /** @var SysFriendApply */
            return $sysFriendApply;
        }
        
        $sysFriendApply = $this->modelClass::find($sysFriendApplyId);
        
        if ($sysFriendApply !== null) {
            $this->setModel($sysFriendApply);
        }
        
        return $sysFriendApply;
    }

    /**
     * 申請者と受信者のペアで既存の申請を検索（双方向チェック）
     *
     * @param int $applyPlayerId 申請者のプレイヤーID
     * @param int $receivePlayerId 受信者のプレイヤーID
     * @return SysFriendApply|null
     */
    public function selectByPlayerPair(int $applyPlayerId, int $receivePlayerId): ?SysFriendApply
    {
        // 申請者→受信者、または受信者→申請者の双方向で検索
        return $this->modelClass::query()
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
                SysFriendApply::STATUS_ACCEPTED
            ])
            ->first();
    }

    /**
     * プレイヤーIDで受信したフレンド申請一覧を取得
     *
     * @param int $playerId プレイヤーID
     * @return \Illuminate\Database\Eloquent\CustomCollection<int, SysFriendApply>
     */
    public function selectReceivedApplies(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->modelClass::query()
            ->where('receiver_sys_player_id', $playerId)
            ->where('status', SysFriendApply::STATUS_APPLIED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * プレイヤーIDで送信したフレンド申請一覧を取得
     *
     * @param int $playerId プレイヤーID
     * @return \Illuminate\Database\Eloquent\CustomCollection<int, SysFriendApply>
     */
    public function selectSentApplies(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->modelClass::query()
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
     * @param int $playerId プレイヤーID
     * @return \Illuminate\Database\Eloquent\CustomCollection<int, SysFriendApply>
     */
    public function selectAppliesByPlayerId(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->modelClass::query()
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
     * @param int $playerId プレイヤーID
     * @return \Illuminate\Database\Eloquent\CustomCollection<int, SysFriendApply>
     */
    public function selectAcceptedFriendsByPlayerId(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->modelClass::query()
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
     * @param int $applyPlayerId 申請者のプレイヤーID
     * @param int $receivePlayerId 受信者のプレイヤーID
     * @return SysFriendApply
     */
    public function createApply(int $applyPlayerId, int $receivePlayerId): SysFriendApply
    {
        $sysFriendApply = new SysFriendApply([
            'sender_sys_player_id' => $applyPlayerId,
            'receiver_sys_player_id' => $receivePlayerId,
            'status' => SysFriendApply::STATUS_APPLIED,
        ]);
        
        $this->setModel($sysFriendApply);
        
        return $sysFriendApply;
    }

    /**
     * フレンド関係を削除（論理削除）
     * 
     * プレイヤーIDと相手プレイヤーIDから、承認済みフレンド関係を削除する
     *
     * @param int $playerId 削除実行者のプレイヤーID
     * @param int $targetPlayerId 削除対象のプレイヤーID
     * @return SysFriendApply|null 削除されたフレンド関係、見つからない場合null
     */
    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?SysFriendApply
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

        if (!$friendRelation) {
            return null;
        }

        // 論理削除
        $friendRelation->delete();

        return $friendRelation;
    }
}
