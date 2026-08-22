<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SysGuild Model
 *
 * ギルド情報テーブル
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $level
 * @property int $exp
 * @property int $max_members
 * @property string $created_at
 * @property string $updated_at
 */
class SysGuild extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_guild';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'level',
        'exp',
        'max_members',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'level' => 'integer',
        'exp' => 'integer',
        'max_members' => 'integer',
    ];

    /**
     * メンバーとのリレーション
     */
    /**
     * @return HasMany<SysGuildMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(SysGuildMember::class, 'sys_guild_id');
    }

    /**
     * 申請とのリレーション
     */
    /**
     * @return HasMany<SysGuildApply, $this>
     */
    public function applies(): HasMany
    {
        return $this->hasMany(SysGuildApply::class, 'sys_guild_id');
    }

    /**
     * IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * ギルド名を取得
     */
    public function getName(): string
    {
        return $this->getAttribute('name');
    }

    /**
     * ギルド説明を取得
     */
    public function getDescription(): ?string
    {
        return $this->getAttribute('description');
    }

    /**
     * レベルを取得
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * 経験値を取得
     */
    public function getExp(): int
    {
        return $this->getAttribute('exp');
    }

    /**
     * 最大メンバー数を取得
     */
    public function getMaxMembers(): int
    {
        return $this->getAttribute('max_members');
    }

    /**
     * ギルド名を設定
     */
    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    /**
     * ギルド説明を設定
     */
    public function setDescription(?string $description): void
    {
        $this->setAttribute('description', $description);
    }

    /**
     * レベルを設定
     */
    public function setLevel(int $level): void
    {
        $this->setAttribute('level', $level);
    }

    /**
     * 経験値を設定
     */
    public function setExp(int $exp): void
    {
        $this->setAttribute('exp', $exp);
    }

    /**
     * 最大メンバー数を設定
     */
    public function setMaxMembers(int $maxMembers): void
    {
        $this->setAttribute('max_members', $maxMembers);
    }

    /**
     * 現在のメンバー数を取得
     */
    public function countMembers(): int
    {
        return $this->members()->count();
    }
}
