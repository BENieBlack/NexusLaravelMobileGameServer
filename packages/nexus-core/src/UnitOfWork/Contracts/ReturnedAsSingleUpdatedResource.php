<?php

namespace Nexus\Core\UnitOfWork\Contracts;

/**
 * ReturnedAsSingleUpdatedResource
 *
 * プレイヤーに1行しかないテーブル（sys_player）の目印。
 * UpdatedResources では、配列（{テーブル名}_list）ではなく、オブジェクト1件（{テーブル名}）で返す。
 * 現在値を全部返す API（Home）でも、同じ形で返す。
 */
interface ReturnedAsSingleUpdatedResource extends ReturnedAsUpdatedResource {}
