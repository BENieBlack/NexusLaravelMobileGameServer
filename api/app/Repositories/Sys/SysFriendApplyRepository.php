<?php

namespace App\Repositories\Sys;

use App\Adapters\Friend\FriendApplyAdapter;
use App\Models\Sys\SysFriendApply;
use NexusFriend\Dto\FriendApplyDto;
use NexusFriend\Repositories\FriendApplyRepositoryInterface;

/**
 * SysFriendApplyRepository
 * 
 * フレンド申請情報のRepository実装
 * 
 * @extends _BaseSysRepository<SysFriendApply>
 */
class SysFriendApplyRepository extends _BaseSysRepository implements FriendApplyRepositoryInterface
{
    protected string $modelClass = SysFriendApply::class;

    /**
     * IDでフレンド申請を検索（Interface実装）
     *
     * @param int $friendApplyId フレンド申請ID
     * @return FriendApplyDto|null
     */
    public function findById(int $friendApplyId): ?FriendApplyDto
    {
        $model = $this->selectById($friendApplyId);
        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    /**
     * 申請者と受信者のペアで既存の申請を検索（Interface実装）
     *
     * @param int $senderPlayerId 申請者のプレイヤーID
     * @param int $receiverPlayerId 受信者のプレイヤーID
     * @return FriendApplyDto|null
     */
    public function findByPlayerPair(int $senderPlayerId, int $receiverPlayerId): ?FriendApplyDto
    {
        $model = $this->selectByPlayerPair($senderPlayerId, $receiverPlayerId);
        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    /**
     * プレイヤーIDに関連するフレンド申請一覧を取得（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return array<FriendApplyDto>
     */
    public function findAppliesByPlayerId(int $playerId): array
    {
        $models = $this->selectAppliesByPlayerId($playerId);
        return FriendApplyAdapter::toDtoArray($models);
    }

    /**
     * プレイヤーIDに関連する承認済みフレンド一覧を取得（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return array<FriendApplyDto>
     */
    public function findAcceptedFriendsByPlayerId(int $playerId): array
    {
        $models = $this->selectAcceptedFriendsByPlayerId($playerId);
        return FriendApplyAdapter::toDtoArray($models);
    }

    /**
     * フレンド申請を作成（Interface実装）
     *
     * @param int $senderPlayerId 申請者のプレイヤーID
     * @param int $receiverPlayerId 受信者のプレイヤーID
     * @return FriendApplyDto
     */
    public function create(int $senderPlayerId, int $receiverPlayerId): FriendApplyDto
    {
        $model = $this->createApply($senderPlayerId, $receiverPlayerId);
        return FriendApplyAdapter::toDto($model);
    }

    /**
     * フレンド申請を承認（Interface実装）
     *
     * @param FriendApplyDto $applyDto 承認するフレンド申請
     * @return FriendApplyDto 承認後のDTO
     */
    public function accept(FriendApplyDto $applyDto): FriendApplyDto
    {
        $model = $this->selectById($applyDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Friend apply not found');
        }

        $model->accept();
        $this->setModel($model);

        return FriendApplyAdapter::toDto($model);
    }

    /**
     * フレンド申請を却下（Interface実装）
     *
     * @param FriendApplyDto $applyDto 却下するフレンド申請
     * @return FriendApplyDto 却下後のDTO
     */
    public function reject(FriendApplyDto $applyDto): FriendApplyDto
    {
        $model = $this->selectById($applyDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Friend apply not found');
        }

        $model->reject();
        $this->setModel($model);

        return FriendApplyAdapter::toDto($model);
    }

    /**
     * フレンド関係を削除（Interface実装）
     *
     * @param int $playerId 削除実行者のプレイヤーID
     * @param int $targetPlayerId 削除対象のプレイヤーID
     * @return FriendApplyDto|null 削除されたフレンド関係、見つからない場合null
     */
    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?FriendApplyDto
    {
        $model = $this->deleteFriendRelationModel($playerId, $targetPlayerId);
        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    // ========================================
    // 以下、内部用のModelベースメソッド（既存の実装）
    // ========================================


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
     * フレンド関係を削除（論理削除） - 内部用Modelメソッド
     * 
     * プレイヤーIDと相手プレイヤーIDから、承認済みフレンド関係を削除する
     *
     * @param int $playerId 削除実行者のプレイヤーID
     * @param int $targetPlayerId 削除対象のプレイヤーID
     * @return SysFriendApply|null 削除されたフレンド関係、見つからない場合null
     */
    private function deleteFriendRelationModel(int $playerId, int $targetPlayerId): ?SysFriendApply
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
