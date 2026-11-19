<?php

namespace NexusGuild\Services;

use Illuminate\Contracts\Events\Dispatcher;
use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Constants\GuildRole;
use NexusGuild\DataTransferObjects\Guild;
use NexusGuild\DataTransferObjects\GuildApply;
use NexusGuild\DataTransferObjects\GuildMember;
use NexusGuild\Events\GuildApplyAccepted;
use NexusGuild\Events\GuildApplyRejected;
use NexusGuild\Events\GuildApplySent;
use NexusGuild\Events\GuildMemberLeft;
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
        private readonly ?Dispatcher $events = null,
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
    public function validatePlayerInGuild(int $playerId): GuildMember
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
     * @param  Guild  $guild  ギルド
     *
     * @throws GuildException ギルドが満員の場合
     */
    public function validateGuildNotFull(Guild $guild): void
    {
        if ($guild->getCurrentMembers() >= $guild->getMaxMembers()) {
            throw GuildException::guildFull($guild->getId());
        }
    }

    /**
     * ギルドマスター/サブマスターの権限をバリデーション
     *
     * @param  GuildMember  $guildMember  メンバー情報
     * @param  string  $action  アクション名（エラーメッセージ用）
     *
     * @throws GuildException 権限がない場合
     */
    public function validateMasterOrSubMasterPermission(GuildMember $guildMember, string $action): void
    {
        if ($guildMember->getRole() !== GuildRole::MASTER && $guildMember->getRole() !== GuildRole::SUB_MASTER) {
            throw GuildException::permissionDenied($guildMember->getSysPlayerId(), $action);
        }
    }

    /**
     * ギルドマスターの権限をバリデーション
     *
     * @param  GuildMember  $guildMember  メンバー情報
     * @param  string  $action  アクション名（エラーメッセージ用）
     *
     * @throws GuildException 権限がない場合
     */
    public function validateMasterPermission(GuildMember $guildMember, string $action): void
    {
        if ($guildMember->getRole() !== GuildRole::MASTER) {
            throw GuildException::permissionDenied($guildMember->getSysPlayerId(), $action);
        }
    }

    /**
     * 申請が承認可能な状態かバリデーション
     *
     * @param  GuildApply  $guildApply  申請DTO
     *
     * @throws GuildException 承認できない状態の場合
     */
    public function validateCanAccept(GuildApply $guildApply): void
    {
        if ($guildApply->getStatus() !== GuildApplyStatus::APPLIED) {
            throw GuildException::invalidStatus($guildApply->getStatus());
        }
    }

    /**
     * 申請が却下可能な状態かバリデーション
     *
     * @param  GuildApply  $guildApply  申請DTO
     *
     * @throws GuildException 却下できない状態の場合
     */
    public function validateCanReject(GuildApply $guildApply): void
    {
        if ($guildApply->getStatus() !== GuildApplyStatus::APPLIED) {
            throw GuildException::invalidStatus($guildApply->getStatus());
        }
    }

    /**
     * ギルドを作成
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function createGuild(string $name, string $description, int $masterPlayerId): Guild
    {
        $this->validateGuildNameUnique($name);

        // 作成者がメンバーとして入る点は承認と同じ。承認と同時に走ると二重所属になるので、
        // 承認側と同じ mutex（プレイヤー行のロック）を取ってから最新の所属を確認する。
        // トランザクション内で呼ぶこと。ロックはこの 1 行だけなので、承認（申請→ギルド→プレイヤー）と逆順にならない
        if ($this->memberRepository->selectByPlayerIdForUpdate($masterPlayerId) !== null) {
            throw GuildException::alreadyInGuild($masterPlayerId);
        }

        return $this->guildRepository->insert($name, $description, $masterPlayerId);
    }

    /**
     * ギルド加入申請を送信
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function sendApply(int $guildId, int $playerId): GuildApply
    {
        $guild = $this->guildRepository->selectById($guildId);
        if ($guild === null) {
            throw GuildException::guildNotFound($guildId);
        }

        $this->validatePlayerNotInGuild($playerId);
        $this->validateNoDuplicateApply($guildId, $playerId);
        $this->validateGuildNotFull($guild);

        $apply = $this->applyRepository->insert($guildId, $playerId);

        if ($this->events !== null) {
            $officerIds = [];
            foreach ($this->memberRepository->selectByGuildId($guildId) as $member) {
                if (in_array($member->getRole(), [GuildRole::MASTER, GuildRole::SUB_MASTER], true)) {
                    $officerIds[] = $member->getSysPlayerId();
                }
            }

            $this->events->dispatch(new GuildApplySent($apply->getId(), $guildId, $playerId, $officerIds));
        }

        return $apply;
    }

    /**
     * ギルド加入申請を承認し、申請者をメンバーに加える
     *
     * 承認と加入は分けられない。承認だけして加入させない状態や、
     * 定員・所属チェックを経ずに加入させる経路を作らないよう、ここで一括で行う。
     *
     * トランザクション内で呼ぶこと。複数の承認者が同じギルド・同じ申請・同じ申請者を
     * 同時に触りうるので、次の順にロックを取り、ロックを取った後の最新の値で判定する。
     *   1. 申請行（承認と却下が同時に来ても、先に取った側だけが進める）
     *   2. ギルド行（満員判定 → メンバー追加を直列にする mutex）
     *   3. 申請者のプレイヤー行（別ギルドが同じ人を同時に入れるのを直列にする）
     * 承認・却下はどちらも「申請行を最初に」取る。順序を固定してデッドロックを避ける。
     *
     * @param  int  $applyId  申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（承認者）
     */
    public function acceptApply(int $applyId, int $currentPlayerId): GuildApply
    {
        // 1. 申請行をロックして読み直す。以降の status 判定はこの値で行う
        $apply = $this->applyRepository->selectByIdForUpdate($applyId);
        if ($apply === null) {
            throw GuildException::applyNotFound($applyId);
        }

        // 2. ギルド行をロックする。同じギルドの承認は、ここで 1 件ずつに並ぶ
        $guild = $this->guildRepository->selectByIdForUpdate($apply->getSysGuildId());
        if ($guild === null) {
            throw GuildException::guildNotFound($apply->getSysGuildId());
        }

        // 承認者がギルドメンバーであることを確認
        $approverMember = $this->memberRepository->selectByGuildAndPlayer(
            $apply->getSysGuildId(),
            $currentPlayerId
        );
        if ($approverMember === null) {
            throw GuildException::memberNotFound($currentPlayerId);
        }

        // マスターまたはサブマスターのみ承認可能
        $this->validateMasterOrSubMasterPermission($approverMember, 'accept guild apply');
        $this->validateCanAccept($apply);

        // ギルドが満員でないか再確認。ギルド行のロックを取った後の最新の人数で数える
        // （DTO の currentMembers はロックより前のスナップショットかもしれないので使わない）
        $memberCount = $this->memberRepository->countByGuildIdForUpdate($guild->getId());
        if ($memberCount >= $guild->getMaxMembers()) {
            throw GuildException::guildFull($guild->getId());
        }

        // 3. 申請後に別のギルドへ入っていることがある。DBの一意制約はギルド単位なので、ここで止める。
        // 別ギルドの承認者とはギルド行のロックでは直列にならないため、申請者の行をロックして
        // から最新の所属を読む（先に入った側がコミットするまで待ち、その後の所属を見る）
        if ($this->memberRepository->selectByPlayerIdForUpdate($apply->getSysPlayerId()) !== null) {
            throw GuildException::alreadyInGuild($apply->getSysPlayerId());
        }

        $accepted = $this->applyRepository->accept($apply);
        $this->memberRepository->insert($apply->getSysGuildId(), $apply->getSysPlayerId(), GuildRole::MEMBER);

        $this->events?->dispatch(new GuildApplyAccepted($accepted->getId(), $accepted->getSysGuildId(), $accepted->getSysPlayerId()));

        return $accepted;
    }

    /**
     * ギルド加入申請を却下
     *
     * トランザクション内で呼ぶこと。承認と同時に来ても、申請行のロックで
     * どちらか一方だけが進み、後から来た側は最新の status（承認済み／却下済み）を読んで
     * 業務エラーになる。却下はメンバーを増減させないので、ギルド行のロックは要らない。
     *
     * @param  int  $applyId  申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（却下者）
     */
    public function rejectApply(int $applyId, int $currentPlayerId): GuildApply
    {
        // 申請行をロックして読み直す（承認と同じく、最初に取る）
        $apply = $this->applyRepository->selectByIdForUpdate($applyId);
        if ($apply === null) {
            throw GuildException::applyNotFound($applyId);
        }

        // 却下者がギルドメンバーであることを確認
        $rejecterMember = $this->memberRepository->selectByGuildAndPlayer(
            $apply->getSysGuildId(),
            $currentPlayerId
        );
        if ($rejecterMember === null) {
            throw GuildException::memberNotFound($currentPlayerId);
        }

        // マスターまたはサブマスターのみ却下可能
        $this->validateMasterOrSubMasterPermission($rejecterMember, 'reject guild apply');
        $this->validateCanReject($apply);

        $rejected = $this->applyRepository->reject($apply);

        $this->events?->dispatch(new GuildApplyRejected($rejected->getId(), $rejected->getSysGuildId(), $rejected->getSysPlayerId()));

        return $rejected;
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

        $this->events?->dispatch(new GuildMemberLeft($member->getSysGuildId(), $playerId));
    }

    /**
     * ギルド申請一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildApply>
     */
    public function findApplyList(int $guildId): array
    {
        return $this->applyRepository->selectAppliesByGuildId($guildId);
    }

    /**
     * ギルドメンバー一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildMember>
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
    public function findPlayerGuild(int $playerId): ?Guild
    {
        $member = $this->memberRepository->selectByPlayerId($playerId);
        if ($member === null) {
            return null;
        }

        return $this->guildRepository->selectById($member->getSysGuildId());
    }

    /**
     * ギルド一覧を取得
     *
     * 並び順はRepositoryの実装（タイトル側）が決める。
     *
     * @param  int  $limit  取得件数
     * @param  int  $offset  読み飛ばす件数
     * @return array<Guild>
     */
    public function findGuildList(int $limit, int $offset): array
    {
        return $this->guildRepository->selectList($limit, $offset);
    }
}
