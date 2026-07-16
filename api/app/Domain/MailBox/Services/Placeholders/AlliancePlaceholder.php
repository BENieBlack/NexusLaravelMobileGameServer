<?php

namespace App\Domain\MailBox\Services\Placeholders;

use App\Domain\MailBox\Services\PlaceholderResolverInterface;

/**
 * AlliancePlaceholder
 *
 * アライアンス情報のプレースホルダー
 */
class AlliancePlaceholder implements PlaceholderResolverInterface
{
    /**
     * サポートしているキー
     */
    private const SUPPORTED_KEYS = [
        'alliance_name',
        'alliance_id',
        'alliance_rank',
        'alliance_level',
        'member_count',
        'max_members',
    ];

    /**
     * {@inheritDoc}
     */
    public function supports(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $key, array $context): ?string
    {
        $alliance = $context['alliance'] ?? null;

        if ($alliance === null) {
            return null;
        }

        return match($key) {
            'alliance_name' => $alliance->name ?? $alliance->getName() ?? null,
            'alliance_id' => (string)($alliance->id ?? $alliance->getId() ?? null),
            'alliance_rank' => (string)($alliance->rank ?? $alliance->getRank() ?? null),
            'alliance_level' => (string)($alliance->level ?? $alliance->getLevel() ?? null),
            'member_count' => (string)($alliance->member_count ?? $alliance->getMemberCount() ?? null),
            'max_members' => (string)($alliance->max_members ?? $alliance->getMaxMembers() ?? null),
            default => null,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }
}
