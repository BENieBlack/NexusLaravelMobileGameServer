<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildAdapter;
use App\Models\Sys\SysGuild;
use App\Models\Sys\SysGuildMember;
use Illuminate\Database\Eloquent\Collection;
use NexusGuild\Constants\GuildRole;
use NexusGuild\Dto\GuildDto;
use NexusGuild\Repositories\GuildRepositoryInterface;

/**
 * SysGuildRepository
 *
 * ギルド情報のRepository実装
 *
 * @extends _BaseSysRepository<SysGuild>
 */
class SysGuildRepository extends _BaseSysRepository implements GuildRepositoryInterface
{
    protected string $modelClass = SysGuild::class;

    /**
     * IDでギルドを検索（Interface実装）
     *
     * @param  int  $guildId  ギルドID
     */
    public function findById(int $guildId): ?GuildDto
    {
        $model = $this->selectById($guildId);

        return $model ? GuildAdapter::toDto($model) : null;
    }

    /**
     * ギルド名で検索（Interface実装）
     *
     * @param  string  $name  ギルド名
     */
    public function findByName(string $name): ?GuildDto
    {
        $model = $this->selectByName($name);

        return $model ? GuildAdapter::toDto($model) : null;
    }

    /**
     * 全ギルド一覧を取得（Interface実装）
     *
     * @return array<GuildDto>
     */
    public function findAll(): array
    {
        $models = $this->selectAll();

        return GuildAdapter::toDtoArray($models);
    }

    /**
     * ギルドを作成（Interface実装）
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function create(string $name, string $description, int $masterPlayerId): GuildDto
    {
        $model = $this->createGuild($name, $description, $masterPlayerId);

        return GuildAdapter::toDto($model);
    }

    /**
     * ギルド情報を更新（Interface実装）
     *
     * @param  GuildDto  $guildDto  更新するギルド
     * @param  array<string, mixed>  $data  更新データ
     * @return GuildDto 更新後のDTO
     */
    public function update(GuildDto $guildDto, array $data): GuildDto
    {
        $model = $this->selectById($guildDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild not found');
        }

        if (isset($data['name'])) {
            $model->setName($data['name']);
        }
        if (isset($data['description'])) {
            $model->setDescription($data['description']);
        }
        if (isset($data['level'])) {
            $model->setLevel($data['level']);
        }
        if (isset($data['exp'])) {
            $model->setExp($data['exp']);
        }
        if (isset($data['max_members'])) {
            $model->setMaxMembers($data['max_members']);
        }

        $this->setModel($model);

        return GuildAdapter::toDto($model);
    }

    /**
     * ギルドを削除（Interface実装）
     *
     * @param  GuildDto  $guildDto  削除するギルド
     */
    public function delete(GuildDto $guildDto): void
    {
        $model = $this->selectById($guildDto->getId());
        if ($model !== null) {
            $this->deleteModel($model);
        }
    }

    /**
     * ギルド経験値を追加（Interface実装）
     *
     * @param  GuildDto  $guildDto  対象ギルド
     * @param  int  $exp  追加経験値
     * @return GuildDto 更新後のDTO
     */
    public function addExp(GuildDto $guildDto, int $exp): GuildDto
    {
        $model = $this->selectById($guildDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild not found');
        }

        $currentExp = $model->getExp();
        $model->setExp($currentExp + $exp);
        $this->setModel($model);

        return GuildAdapter::toDto($model);
    }

    /**
     * ギルドレベルを更新（Interface実装）
     *
     * @param  GuildDto  $guildDto  対象ギルド
     * @param  int  $level  新しいレベル
     * @param  int  $exp  新しい経験値
     * @return GuildDto 更新後のDTO
     */
    public function updateLevel(GuildDto $guildDto, int $level, int $exp): GuildDto
    {
        $model = $this->selectById($guildDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild not found');
        }

        $model->setLevel($level);
        $model->setExp($exp);
        $this->setModel($model);

        return GuildAdapter::toDto($model);
    }

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * IDでギルドを検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     */
    public function selectById(int $guildId): ?SysGuild
    {
        return SysGuild::find($guildId);
    }

    /**
     * ギルド名で検索（Model返却）
     *
     * @param  string  $name  ギルド名
     */
    public function selectByName(string $name): ?SysGuild
    {
        return SysGuild::where('name', $name)->first();
    }

    /**
     * 全ギルド一覧を取得（Model返却）
     *
     * @return Collection<SysGuild>
     */
    public function selectAll(): Collection
    {
        return SysGuild::all();
    }

    /**
     * ギルドを作成（Model返却）
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function createGuild(string $name, string $description, int $masterPlayerId): SysGuild
    {
        $guild = new SysGuild;
        $guild->setName($name);
        $guild->setDescription($description);
        $guild->setLevel(1);
        $guild->setExp(0);
        $guild->setMaxMembers(30);
        $guild->save();

        // マスターメンバーを作成
        $member = new SysGuildMember;
        $member->setSysGuildId($guild->getId());
        $member->setSysPlayerId($masterPlayerId);
        $member->setRole(GuildRole::MASTER);
        $member->setJoinedAt(now()->format('Y-m-d H:i:s'));
        $member->save();

        return $guild;
    }

    /**
     * Modelを削除
     */
    public function deleteModel(mixed $model): void
    {
        $model->delete();
    }
}
