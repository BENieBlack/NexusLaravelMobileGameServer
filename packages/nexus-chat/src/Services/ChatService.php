<?php

namespace NexusChat\Services;

use NexusChat\Constants\ChatRoomMemberLimit;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use NexusChat\Contracts\ChatMessageRepositoryInterface;
use NexusChat\Contracts\ChatRoomMemberRepositoryInterface;
use NexusChat\Contracts\ChatRoomRepositoryInterface;
use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\DataTransferObjects\ChatRoomMember;
use NexusChat\Events\MessageSent;
use NexusChat\Exceptions\ChatException;

/**
 * ChatService
 *
 * チャット機能のビジネスロジック
 *
 * - フレンドDM:     2人固定。フレンド関係の検証はApplication層で行い、
 *                   room_key で一意なルームを管理する
 * - ギルドチャット: ギルド加入・脱退に合わせてApplication層がルームを管理する
 * - グループチャット: Owner/AdminがメンバーをIDで招待。最大20人
 */
class ChatService
{
    // メッセージの最大文字数
    private const MAX_MESSAGE_LENGTH = 500;

    public function __construct(
        private readonly ChatRoomRepositoryInterface $roomRepository,
        private readonly ChatMessageRepositoryInterface $messageRepository,
        private readonly ChatRoomMemberRepositoryInterface $memberRepository,
    ) {}

    // =========================================================
    // メッセージ送信
    // =========================================================

    /**
     * チャットルームへメッセージを送信する
     *
     * @param  int     $chatRoomId     送信先ルームID
     * @param  int     $senderPlayerId 送信者プレイヤーID
     * @param  string  $senderName     送信者表示名
     * @param  string  $body           メッセージ本文
     *
     * @throws ChatException ルームが存在しない、メンバーでない、文字数超過
     */
    public function sendMessage(
        int $chatRoomId,
        int $senderPlayerId,
        string $senderName,
        string $body,
    ): ChatMessage {
        $this->validateMessageBody($body);

        $room = $this->roomRepository->selectById($chatRoomId);
        if ($room === null) {
            throw ChatException::roomNotFound();
        }

        // GUILD以外はメンバーテーブルで参加確認
        if ($room->getType() !== ChatRoomType::GUILD) {
            $this->validateIsMember($chatRoomId, $senderPlayerId);
        }

        $message = $this->messageRepository->insert(
            chatRoomId: $chatRoomId,
            senderPlayerId: $senderPlayerId,
            senderName: $senderName,
            body: $body,
        );

        // Reverbへブロードキャスト
        event(new MessageSent($room, $message));

        return $message;
    }

    /**
     * メッセージ履歴を取得（カーソルページネーション）
     *
     * @param  int       $chatRoomId      ルームID
     * @param  int       $requestPlayerId リクエスト者（メンバー確認用）
     * @param  int       $limit           取得件数
     * @param  int|null  $beforeMessageId このID以前を取得（NULL=最新から）
     * @return array<ChatMessage>
     *
     * @throws ChatException メンバーでない場合
     */
    public function getMessages(
        int $chatRoomId,
        int $requestPlayerId,
        int $limit = 30,
        ?int $beforeMessageId = null,
    ): array {
        $room = $this->roomRepository->selectById($chatRoomId);
        if ($room === null) {
            throw ChatException::roomNotFound();
        }

        if ($room->getType() !== ChatRoomType::GUILD) {
            $this->validateIsMember($chatRoomId, $requestPlayerId);
        }

        return $this->messageRepository->selectByRoomId($chatRoomId, $limit, $beforeMessageId);
    }

    /**
     * メッセージを削除（送信者本人のみ）
     *
     * @throws ChatException 送信者でない場合
     */
    public function deleteMessage(int $messageId, int $requestPlayerId): void
    {
        $message = $this->messageRepository->selectById($messageId);
        if ($message === null) {
            return;
        }

        if ($message->getSenderPlayerId() !== $requestPlayerId) {
            throw ChatException::notMessageOwner();
        }

        $this->messageRepository->softDelete($messageId);
    }

    // =========================================================
    // フレンドDM
    // =========================================================

    /**
     * フレンドDMルームを取得または作成する
     *
     * フレンド関係の検証はApplication層（UseCase）が行うこと
     *
     * @param  int  $playerIdA  プレイヤーID
     * @param  int  $playerIdB  プレイヤーID
     */
    public function findOrCreateFriendRoom(int $playerIdA, int $playerIdB): ChatRoom
    {
        return $this->roomRepository->findOrCreateFriendRoom($playerIdA, $playerIdB);
    }

    // =========================================================
    // ギルドチャット
    // =========================================================

    /**
     * ギルドチャットルームを取得または作成する
     *
     * ギルドメンバーの検証はApplication層（UseCase）が行うこと
     *
     * @param  int  $guildId  ギルドID
     */
    public function findOrCreateGuildRoom(int $guildId): ChatRoom
    {
        return $this->roomRepository->findOrCreateGuildRoom($guildId);
    }

    // =========================================================
    // グループチャット
    // =========================================================

    /**
     * グループチャットルームを作成する
     *
     * 作成者は自動的に OWNER ロールで参加する
     *
     * @param  string  $name            グループ名
     * @param  int     $ownerPlayerId   作成者プレイヤーID
     * @param  string  $ownerName       作成者表示名
     * @return ChatRoom 作成されたルーム
     */
    public function createGroupRoom(string $name, int $ownerPlayerId, string $ownerName): ChatRoom
    {
        $room = $this->roomRepository->createGroupRoom($name);

        $this->memberRepository->insert(
            chatRoomId: $room->getId(),
            playerId: $ownerPlayerId,
            playerName: $ownerName,
            role: ChatRoomRole::OWNER,
        );

        $this->roomRepository->updateMemberCount($room->getId(), 1);

        return $room;
    }

    /**
     * グループチャットへメンバーを招待する
     *
     * @param  int     $chatRoomId       ルームID
     * @param  int     $inviterPlayerId  招待者プレイヤーID
     * @param  int     $targetPlayerId   招待対象プレイヤーID
     * @param  string  $targetName       招待対象表示名
     *
     * @throws ChatException ルームが存在しない、招待権限がない、満員
     */
    public function inviteToGroup(
        int $chatRoomId,
        int $inviterPlayerId,
        int $targetPlayerId,
        string $targetName,
    ): ChatRoomMember {
        $room = $this->roomRepository->selectById($chatRoomId);
        if ($room === null || $room->getType() !== ChatRoomType::GROUP) {
            throw ChatException::roomNotFound();
        }

        // 招待者の権限確認
        $inviterMember = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $inviterPlayerId);
        if ($inviterMember === null) {
            throw ChatException::notRoomMember();
        }
        if (! $inviterMember->canInvite()) {
            throw ChatException::noInvitePermission();
        }

        // 満員チェック
        $currentCount = $this->memberRepository->countByRoomId($chatRoomId);
        if ($currentCount >= ChatRoomMemberLimit::GROUP) {
            throw ChatException::roomFull();
        }

        // 既にメンバーでないか確認
        $existing = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $targetPlayerId);
        if ($existing !== null) {
            throw ChatException::alreadyMember();
        }

        $member = $this->memberRepository->insert(
            chatRoomId: $chatRoomId,
            playerId: $targetPlayerId,
            playerName: $targetName,
            role: ChatRoomRole::MEMBER,
        );

        $this->roomRepository->updateMemberCount($chatRoomId, $currentCount + 1);

        return $member;
    }

    /**
     * グループチャットからメンバーをキックする
     *
     * @param  int  $chatRoomId      ルームID
     * @param  int  $kickerPlayerId  キック実行者プレイヤーID
     * @param  int  $targetPlayerId  キック対象プレイヤーID
     *
     * @throws ChatException 権限がない場合
     */
    public function kickFromGroup(
        int $chatRoomId,
        int $kickerPlayerId,
        int $targetPlayerId,
    ): void {
        $room = $this->roomRepository->selectById($chatRoomId);
        if ($room === null || $room->getType() !== ChatRoomType::GROUP) {
            throw ChatException::roomNotFound();
        }

        $kickerMember = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $kickerPlayerId);
        if ($kickerMember === null || ! $kickerMember->canKick()) {
            throw ChatException::noKickPermission();
        }

        // OWNERはキックできない
        $targetMember = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $targetPlayerId);
        if ($targetMember !== null && $targetMember->getRole() === ChatRoomRole::OWNER) {
            throw ChatException::cannotKickOwner();
        }

        $this->memberRepository->delete($chatRoomId, $targetPlayerId);

        $newCount = $this->memberRepository->countByRoomId($chatRoomId);
        $this->roomRepository->updateMemberCount($chatRoomId, $newCount);
    }

    /**
     * グループチャットを退室する
     *
     * OWNERが退室する場合は他のメンバーへロールを委譲する必要がある
     * （Application層のUseCaseで委譲処理を行ってからこのメソッドを呼ぶこと）
     *
     * @param  int  $chatRoomId  ルームID
     * @param  int  $playerId    退室するプレイヤーID
     */
    public function leaveGroup(int $chatRoomId, int $playerId): void
    {
        $this->validateIsMember($chatRoomId, $playerId);

        $this->memberRepository->delete($chatRoomId, $playerId);

        $newCount = $this->memberRepository->countByRoomId($chatRoomId);
        $this->roomRepository->updateMemberCount($chatRoomId, $newCount);
    }

    /**
     * グループチャットのメンバーロールを変更する（OWNERのみ）
     *
     * @param  int           $chatRoomId      ルームID
     * @param  int           $ownerPlayerId   実行者（OWNER）プレイヤーID
     * @param  int           $targetPlayerId  対象プレイヤーID
     * @param  ChatRoomRole  $newRole         新しいロール
     *
     * @throws ChatException OWNER以外が実行した場合
     */
    public function changeRole(
        int $chatRoomId,
        int $ownerPlayerId,
        int $targetPlayerId,
        ChatRoomRole $newRole,
    ): void {
        $ownerMember = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $ownerPlayerId);
        if ($ownerMember === null || ! $ownerMember->getRole()->canManageRoles()) {
            throw ChatException::noRoleManagePermission();
        }

        $this->memberRepository->updateRole($chatRoomId, $targetPlayerId, $newRole);
    }

    /**
     * プレイヤーが参加しているルーム一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<ChatRoom>
     */
    public function getRoomsByPlayer(int $playerId): array
    {
        return $this->roomRepository->selectRoomsByPlayerId($playerId);
    }

    /**
     * グループチャットのメンバー一覧を取得
     *
     * @param  int  $chatRoomId      ルームID
     * @param  int  $requestPlayerId リクエスト者（メンバー確認用）
     * @return array<ChatRoomMember>
     *
     * @throws ChatException メンバーでない場合
     */
    public function getGroupMembers(int $chatRoomId, int $requestPlayerId): array
    {
        $this->validateIsMember($chatRoomId, $requestPlayerId);

        return $this->memberRepository->selectByRoomId($chatRoomId);
    }

    // =========================================================
    // Private
    // =========================================================

    private function validateMessageBody(string $body): void
    {
        if (trim($body) === '') {
            throw ChatException::messageEmpty();
        }

        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            throw ChatException::messageTooLong();
        }
    }

    private function validateIsMember(int $chatRoomId, int $playerId): void
    {
        $member = $this->memberRepository->selectByRoomAndPlayer($chatRoomId, $playerId);
        if ($member === null) {
            throw ChatException::notRoomMember();
        }
    }
}
