<?php

namespace NexusAgreement\DataTransferObjects;

/**
 * PendingAgreement
 *
 * 未同意の必須規約1件を表すDTO
 */
readonly class PendingAgreement
{
    public function __construct(
        private int $sysAgreementId,
        private string $version,
        private string $title,
        private string $url,
        private string $publishedAt,
    ) {}

    public function getSysAgreementId(): int
    {
        return $this->sysAgreementId;
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

    /**
     * 公開日時（Y-m-d H:i:s形式）
     */
    public function getPublishedAt(): string
    {
        return $this->publishedAt;
    }
}
