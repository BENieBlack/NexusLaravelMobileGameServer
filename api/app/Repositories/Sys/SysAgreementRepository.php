<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysAgreement;
use Nexus\Core\Support\CustomCollection;

/**
 * SysAgreementRepository
 *
 * 利用規約のバージョン（sys_agreement）の読み取りを担当
 * ビジネスロジックは NexusAgreement\Services\AgreementService に実装
 *
 * @extends _BaseSysRepository<SysAgreement>
 */
class SysAgreementRepository extends _BaseSysRepository
{
    protected string $modelClass = SysAgreement::class;

    /**
     * 必須規約のうち、指定日時までに公開されたものを取得する（キャッシュを通さない）
     *
     * @param  string  $now  基準日時（Y-m-d H:i:s形式）
     * @return CustomCollection<int, SysAgreement>
     */
    public function selectRequiredPublishedUntil(string $now): CustomCollection
    {
        /** @var CustomCollection<int, SysAgreement> */
        return new CustomCollection(
            $this->selectWithoutCache()
                ->where('is_required', true)
                ->where('published_at', '<=', $now)
                ->get()
                ->all()
        );
    }
}
