<?php

namespace App\Repositories\Mst;

use Nexus\Core\Repositories\Mst\_BaseMstRepository as PersistenceBaseMstRepository;

/**
 * _BaseMstRepository
 *
 * マスターデータのRepository基底クラス
 * キャッシュ機能を含む読み取り専用操作を提供
 *
 * パッケージ側のMstモデル（NexusVip\Models\MstVipLevel など）も扱うため、
 * テンプレートの上限はNexus\Coreのインターフェースに合わせる。
 *
 * @template T of \Nexus\Core\Models\Mst\_BaseMst
 *
 * @extends PersistenceBaseMstRepository<T>
 *
 * @implements _BaseMstRepositoryInterface<T>
 */
abstract class _BaseMstRepository extends PersistenceBaseMstRepository implements _BaseMstRepositoryInterface {}
