<?php

namespace NexusLogin\Services;

use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Support\CustomCollection;
use NexusLogin\DataTransferObjects\LoginBonusContent;

/**
 * LoginBonusResourceConverter
 *
 * ログインボーナスの報酬の行（mst_*_login_bonus_content）を
 * LoginBonusContent → Resource に変える、状態を持たない変換
 *
 * 配布（_BaseLoginBonusService）・カレンダー表示・VIP の再受け取りが同じ規則を使うための入れ物。
 * 「type の決め方（content_type = wallet は content_mst_id が type）」を 1 か所にして、
 * 配る量と画面に出す量・種類がずれないようにする。DB へは触らない（配布は呼び出し側が行う）。
 */
class LoginBonusResourceConverter
{
    /**
     * 報酬の行（配列）を LoginBonusContent に変換する
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @return CustomCollection<int, LoginBonusContent>
     */
    public static function toContents(iterable $rows): CustomCollection
    {
        $contents = [];
        foreach ($rows as $row) {
            $contents[] = self::toContent($row);
        }

        return new CustomCollection($contents);
    }

    /**
     * 報酬の 1 行を LoginBonusContent に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toContent(array $row): LoginBonusContent
    {
        return new LoginBonusContent(
            contentType: (string) $row['content_type'],
            contentMstId: (string) $row['content_mst_id'],
            contentOption: $row['content_option'] ?? null,
            contentQuantity: (int) $row['content_quantity'],
            amount: (int) $row['amount'],
            isPaid: (bool) ($row['is_paid'] ?? false),
        );
    }

    /**
     * LoginBonusContent を Resource に変換する
     *
     * @throws \ValueError 未知の content_type（ResourceType に無い値）の場合
     */
    public static function toResource(LoginBonusContent $content): Resource
    {
        $metadata = [];
        if ($content->getIsPaid()) {
            $metadata['is_paid'] = true;
        }

        if (! empty($content->getContentOption())) {
            $metadata = [...$metadata, ...$content->getContentOption()];
        }

        // content_type='wallet' の場合、content_mst_id をResourceTypeとして使用する
        // （DBのenum定義は 'wallet' だが、ResourceTypeには 'gold'/'coin' 等が存在する）
        $typeString = $content->getContentType() === 'wallet'
            ? $content->getContentMstId()
            : $content->getContentType();

        return Resource::fromTypeString(
            typeString: $typeString,
            id: $content->getContentMstId(),
            amount: $content->getTotalQuantity(),
            metadata: empty($metadata) ? null : $metadata,
        );
    }
}
