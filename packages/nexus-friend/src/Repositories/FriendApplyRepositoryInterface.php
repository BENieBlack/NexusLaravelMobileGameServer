<?php

namespace NexusFriend\Repositories;

use NexusFriend\Dto\FriendApplyDto;

/**
 * FriendApplyRepositoryInterface
 *
 * フレンド申請Repositoryのインターフェース
 */
interface FriendApplyRepositoryInterface
{
    /**
     * IDでフレンド申請を検索
     *
     * @param  int  $friendApplyId  フレンド申請ID
     */
    public function selectById(int $friendApplyId): ?FriendApplyDto;

    /**
     * 申請者と受信者のペアで既存の申請を検索（双方向チェック）
     *
     * Applied または Accepted のステータスのみを検索対象とする
     *
     * @param  int  $senderPlayerId  申請者のプレイヤーID
     * @param  int  $receiverPlayerId  受信者のプレイヤーID
     */
    public function selectByPlayerPair(int $senderPlayerId, int $receiverPlayerId): ?FriendApplyDto;

    /**
     * プレイヤーIDに関連するフレンド申請一覧を取得
     *
     * sender_player_idまたはreceiver_player_idが指定プレイヤーで、
     * statusがAppliedのものを取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<FriendApplyDto>
     */
    public function selectAppliesByPlayerId(int $playerId): array;

    /**
     * プレイヤーIDに関連する承認済みフレンド一覧を取得
     *
     * sender_player_idまたはreceiver_player_idが指定プレイヤーで、
     * statusがAcceptedのものを取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<FriendApplyDto>
     */
    public function selectAcceptedFriendsByPlayerId(int $playerId): array;

    /**
     * フレンド申請を作成
     *
     * @param  int  $senderPlayerId  申請者のプレイヤーID
     * @param  int  $receiverPlayerId  受信者のプレイヤーID
     */
    public function create(int $senderPlayerId, int $receiverPlayerId): FriendApplyDto;

    /**
     * フレンド申請を承認
     *
     * @param  FriendApplyDto  $friendApplyDto  承認するフレンド申請
     * @return FriendApplyDto 承認後のDTO
     */
    public function accept(FriendApplyDto $friendApplyDto): FriendApplyDto;

    /**
     * フレンド申請を却下
     *
     * @param  FriendApplyDto  $friendApplyDto  却下するフレンド申請
     * @return FriendApplyDto 却下後のDTO
     */
    public function reject(FriendApplyDto $friendApplyDto): FriendApplyDto;

    /**
     * フレンド関係を削除（論理削除）
     *
     * プレイヤーIDと相手プレイヤーIDから、承認済みフレンド関係を削除する
     *
     * @param  int  $playerId  削除実行者のプレイヤーID
     * @param  int  $targetPlayerId  削除対象のプレイヤーID
     * @return FriendApplyDto|null 削除されたフレンド関係、見つからない場合null
     */
    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?FriendApplyDto;
}
