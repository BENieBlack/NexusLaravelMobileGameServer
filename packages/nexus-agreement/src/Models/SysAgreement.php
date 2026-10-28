<?php

namespace NexusAgreement\Models;

use Nexus\Core\Models\Sys\_BaseSys;

/**
 * SysAgreement Model
 *
 * 利用規約バージョン管理テーブル（sys_agreement）
 * 読み取り専用（setter なし）
 *
 * @property int $id
 * @property string $version
 * @property string $title
 * @property string $url
 * @property bool $is_required
 * @property string $published_at
 * @property ?string $created_at
 * @property ?string $updated_at
 */
class SysAgreement extends _BaseSys
{
    protected $table = 'sys_agreement';

    /** @var list<string> */
    protected array $selectKeys = ['id'];

    /** @var list<string> */
    protected $fillable = [
        'version',
        'title',
        'url',
        'is_required',
        'published_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_required' => 'boolean',
    ];

    /** @var list<string> */
    protected $dates = ['published_at'];

    public function getId(): int
    {
        return (int) $this->getAttribute('id');
    }

    public function getVersion(): string
    {
        return (string) $this->getAttribute('version');
    }

    public function getTitle(): string
    {
        return (string) $this->getAttribute('title');
    }

    public function getUrl(): string
    {
        return (string) $this->getAttribute('url');
    }

    public function getIsRequired(): bool
    {
        return (bool) $this->getAttribute('is_required');
    }

    public function getPublishedAt(): string
    {
        return (string) $this->getAttribute('published_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return [
            'sys_agreement_id' => $this->getId(),
            'version' => $this->getVersion(),
            'title' => $this->getTitle(),
            'url' => $this->getUrl(),
            'published_at' => $this->getPublishedAt(),
        ];
    }
}
