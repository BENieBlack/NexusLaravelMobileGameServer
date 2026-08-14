<?php

namespace NexusGuild\Services;

use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Constants\GuildRole;
use NexusGuild\Dto\GuildApplyDto;
use NexusGuild\Dto\GuildDto;
use NexusGuild\Dto\GuildMemberDto;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;
use NexusGuild\Repositories\GuildRepositoryInterface;

/**
 * GuildService
 *
 * ギルド機能のビジネスロジック
 */
class GuildService
{
    public function __construct(
        private readonly GuildRepositoryInterface $guildRepository,
        private readonly GuildApplyRepositoryInterface $applyRepository,
        private readonly GuildMemberRepositoryInterface $memberRepository,
    ) {}

    /**
     * ギルド名の重複をバリデーション
     *
     * @param  string  $name  ギルド名
     *
     * @throws GuildException ギルド名が既に存在する場合
     */
    public function validateGuildNameUnique(string $name): void
    {
        $existingGuild = $this->guildRepository->selectByName($name);

        if ($existingGuild !== null) {
            throw GuildException::guildNameAlreadyExists($name);
        }
    }

    /**
     * プレイヤーがギルドに所属していないことをバリデーション
     *
     * @param  int  $playerId  プレイヤーID
     *
     * @throws GuildException 既にギルドに所属している場合
     */
    public function validatePlayerNotInGuild(int $playerId): void
    {
        $member = $this->memberRepository->selectByPlayerId($playerId);

        if ($member !== null) {
            throw GuildException::alreadyInGuild($playerId);
        }
    }

    /**
     * プレイヤーがギルドに所属していることをバリデーション
     *
     * @param  int  $playerId  プレイヤーID
     *
     * @throws GuildException ギルドに所属していない場合
     */
    public function validatePlayerInGuild(int $playerId): GuildMemberDto
    {
        $member = $this->memberRepository->selectByPlayerId($playerId);

        if ($member === null) {
            throw GuildException::notInGuild($playerId);
        }

        return $member;
    }

    /**
     * 重複申請がないかバリデーション
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     *
     * @throws GuildException 既に申請済みの場合
     */
    public function validateNoDuplicateApply(int $guildId, int $playerId): void
    {
        $existingApply = $this->applyRepository->selectByGuildAndPlayer($guildId, $playerId);

        if ($existingApply !== null) {
            throw GuildException::applyAlreadyExists($guildId, $playerId);
        }
    }

    /**
     * ギルドが満員でないかバリデーション
     *
     * @param  GuildDto  $guildDto  ギルド
     *
     * @throws GuildException ギルドが満員の場合
     */
    public function validateGuildNotFull(GuildDto $guildDto): void
    {
        if ($guildDto->getCurrentMembers() >= $guildDto->getMaxMembers()) {
            throw GuildException::guildFull($guildDto->getId());
        }
    }

    /**
     * ギルドマスター/サブマスターの権限をバリデーション
     *
     * @param  GuildMemberDto  $guildMemberDto  メンバー情報
     * @param  string  $action  アクション名（エラーメッセージ用）
     *
     * @throws GuildException 権限がない場合
     */
    public function validateMasterOrSubMasterPermission(GuildMemberDto $guildMemberDto, string $action): void
    {
        if ($guildMemberDto->getRole() !== GuildRole::MASTER && $guildMemberDto->getRole() !== GuildRole::SUB_MASTER) {
            throw GuildException::permissionDenied($guildMemberDto->getSysPlayerId(), $action);
        }
    }

    /**
     * ギルドマスターの権限をバリデーション
     *
     * @param  GuildMemberDto  $guildMemberDto  メンバー情報
     * @param  string  $action  アクション名（エラーメッセージ用）
     *
     * @throws GuildException 権限がない場合
     */
    public function validateMasterPermission(GuildMemberDto $guildMemberDto, string $action): void
    {
        if ($guildMemberDto->getRole() !== GuildRole::MASTER) {
            throw GuildException::permissionDenied($guildMemberDto->getSysPlayerId(), $action);
        }
    }

    /**
     * 申請が承認可能な状態かバリデーション
     *
     * @param  GuildApplyDto  $guildApplyDto  申請DTO
     *
     * @throws GuildException 承認できない状態の場合
     */
    public function validateCanAccept(GuildApplyDto $guildApplyDto): void
    {
        if ($guildApplyDto->getStatus() !== GuildApplyStatus::APPLIED) {
            throw GuildException::invalidStatus($guildApplyDto->getStatus());
        }
    }

    /**
     * 申請が却下可能な状態かバリデーション
     *
     * @param  GuildApplyDto  $guildApplyDto  申請DTO
     *
     * @throws GuildException 却下できない状態の場合
     */
    public function validateCanReject(GuildApplyDto $guildApplyDto): void
    {
        if ($guildApplyDto->getStatus() !== GuildApplyStatus::APPLIED) {
            throw GuildException::invalidStatus($guildApplyDto->getStatus());
        }
    }

    /**
     * ギルドを作成
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function createGuild(string $name, string $description, int $masterPlayerId): GuildDto
    {
        $this->validateGuildNameUnique($name);
        $this->validatePlayerNotInGuild($masterPlayerId);

        return $this->guildRepository->insert($name, $description, $masterPlayerId);
    }

    /**
     * ギルド加入申請を送信
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function sendApply(int $guildId, int $playerId): GuildApplyDto
    {
        $guild = $this->guildRepository->selectById($guildId);
        if ($guild === null) {
            throw GuildException::guildNotFound($guildId);
        }

        $this->validatePlayerNotInGuild($playerId);
        $this->validateNoDuplicateApply($guildId, $playerId);
        $this->validateGuildNotFull($guild);

        return $this->applyRepository->insert($guildId, $playerId);
    }

    /**
     * ギルド加入申請を承認
     *
     * @param  int  $applyId  申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（承認者）
     */
    public function acceptApply(int $applyId, int $currentPlayerId): GuildApplyDto
    {
        $applyDto = $this->applyRepository->selectById($applyId);
        if ($applyDto === null) {
            throw GuildException::applyNotFound($applyId);
        }

        // 承認者がギルドメンバーであることを確認
        $approverMember = $this->memberRepository->selectByGuildAndPlayer(
            $applyDto->getSysGuildId(),
            $currentPlayerId
        );
        if ($approverMember === null) {
            throw GuildException::memberNotFound($currentPlayerId);
        }

        // マスターまたはサブマスターのみ承認可能
        $this->validateMasterOrSubMasterPermission($approverMember, 'accept guild apply');
        $this->validateCanAccept($applyDto);

        // ギルドが満員でないか再確認
        $guild = $this->guildRepository->selectById($applyDto->getSysGuildId());
        if ($guild === null) {
            throw GuildException::guildNotFound($applyDto->getSysGuildId());
        }
        $this->validateGuildNotFull($guild);

        return $this->applyRepository->accept($applyDto);
    }

    /**
     * ギルド加入申請を却下
     *
     * @param  int  $applyId  申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（却下者）
     */
    public function rejectApply(int $applyId, int $currentPlayerId): GuildApplyDto
    {
        $applyDto = $this->applyRepository->selectById($applyId);
        if ($applyDto === null) {
            throw GuildException::applyNotFound($applyId);
        }

        // 却下者がギルドメンバーであることを確認
        $rejecterMember = $this->memberRepository->selectByGuildAndPlayer(
            $applyDto->getSysGuildId(),
            $currentPlayerId
        );
        if ($rejecterMember === null) {
            throw GuildException::memberNotFound($currentPlayerId);
        }

        // マスターまたはサブマスターのみ却下可能
        $this->validateMasterOrSubMasterPermission($rejecterMember, 'reject guild apply');
        $this->validateCanReject($applyDto);

        return $this->applyRepository->reject($applyDto);
    }

    /**
     * ギルドから脱退
     *
     * @param  int  $playerId  プレイヤーID
     *
     * @throws GuildException マスターは脱退できない
     */
    public function leaveGuild(int $playerId): void
    {
        $member = $this->validatePlayerInGuild($playerId);

        // マスターは脱退できない
        if ($member->getRole() === GuildRole::MASTER) {
            throw GuildException::masterCannotLeave($playerId);
        }

        $this->memberRepository->delete($member);
        $this->applyRepository->deleteByPlayerId($playerId);
    }

    /**
     * ギルド申請一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildApplyDto>
     */
    public function findApplyList(int $guildId): array
    {
        return $this->applyRepository->selectAppliesByGuildId($guildId);
    }

    /**
     * ギルドメンバー一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildMemberDto>
     */
    public function findMemberList(int $guildId): array
    {
        return $this->memberRepository->selectByGuildId($guildId);
    }

    /**
     * プレイヤーの所属ギルドを取得
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function findPlayerGuild(int $playerId): ?GuildDto
    {
        $member = $this->memberRepository->selectByPlayerId($playerId);
        if ($member === null) {
            return null;
        }

        return $this->guildRepository->selectById($member->getSysGuildId());
    }

    /**
     * 全ギルド一覧を取得
     *
     * @return array<GuildDto>
     */
    public function getAllGuilds(): array
    {
        return $this->guildRepository->selectAll();
    }
}
