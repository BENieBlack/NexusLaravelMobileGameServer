<?php

namespace NexusAgreement\DataTransferObjects;

/**
 * Agreement
 *
 * 利用規約のバージョン1件（sys_agreement の1行）
 */
class Agreement
{
    public function __construct(
        private readonly int $id,
        private readonly string $version,
        private readonly string $title,
        private readonly string $url,
        private readonly bool $isRequired,
        private readonly string $publishedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getIsRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * 公開日時（Y-m-d H:i:s形式）
     */
    public function getPublishedAt(): string
    {
        return $this->publishedAt;
    }
}
