<?php

namespace App\Models\Sys;

/**
 * SysPlayerExternalAccount Model
 *
 * 外部アカウント（Apple / Google）との紐付け。
 * 1つの外部アカウントは1人のプレイヤーにだけ、1人のプレイヤーは種類ごとに1つだけ紐付く。
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string $created_at
 * @property string $updated_at
 */
class SysPlayerExternalAccount extends _BaseSys
{
    protected $table = 'sys_player_external_account';

    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'provider',
        'provider_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
    ];

    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    public function getProvider(): string
    {
        return $this->getAttribute('provider');
    }

    public function getProviderUserId(): string
    {
        return $this->getAttribute('provider_user_id');
    }

    /**
     * レスポンス用配列に変換
     *
     * 外部アカウント側のユーザーIDはクライアントに返さず、紐付いている種類だけを返す
     */
    public function toResponseArray(): array
    {
        return [
            'provider' => $this->getProvider(),
        ];
    }
}
