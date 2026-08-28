<?php

namespace NexusTidb\Concerns;

use Illuminate\Support\Str;
use NexusTidb\Support\TidbMode;

/**
 * UsesUuidPrimaryKey
 *
 * TiDB利用時に、単一主キー id をUUIDで払い出すtrait
 *
 * TiDBは分散DBのため AUTO_INCREMENT が単調増加を保証せず、
 * 連番キーは書き込みが特定リージョンへ集中する原因にもなる。
 * ランダムなUUID（v4）にすることで書き込みを分散させる。
 * 時系列順のUUIDは連番と同じく偏るため使わない。
 *
 * 効くのは次の条件を全て満たす場合のみ:
 * - config('nexus-tidb.is_tidb') が true
 * - 主キーが単一で、名前が 'id'
 *
 * 複合主キーのモデル（TrxStamina等）や、主キーが sys_player_id のモデル
 * （TrxPlayer等）は対象外。idカラムを持たないテーブルへ
 * 余計な属性を差し込まないための判定でもある。
 *
 * UUIDはコンストラクタで入れる。INSERTはUnitOfWorkがEloquentを介さず
 * 直接実行するため、Eloquentのcreatingイベントでは間に合わない。
 */
trait UsesUuidPrimaryKey
{
    /**
     * Eloquentがコンストラクタで呼ぶ初期化フック
     *
     * すでに値が入っている場合（DBから読んだ行など）は触らない
     */
    public function initializeUsesUuidPrimaryKey(): void
    {
        if (! $this->usesUuidPrimaryKey()) {
            return;
        }

        $keyName = $this->getKeyName();

        if ($this->getAttribute($keyName) === null) {
            $this->setAttribute($keyName, $this->generateUuidPrimaryKey());
        }
    }

    /**
     * UUIDを使う場合はAUTO_INCREMENTではない
     *
     * UnitOfWorkのBatchExecutorはこの値を見て、
     * INSERT後にLAST_INSERT_ID()でIDを上書きするかを決める。
     */
    public function getIncrementing(): bool
    {
        if ($this->usesUuidPrimaryKey()) {
            return false;
        }

        return parent::getIncrementing();
    }

    /**
     * UUIDを使う場合は主キーの型が文字列になる
     */
    public function getKeyType(): string
    {
        if ($this->usesUuidPrimaryKey()) {
            return 'string';
        }

        return parent::getKeyType();
    }

    /**
     * キャストの一覧
     *
     * モデル側が 'id' => 'integer' を宣言していると、
     * UUIDが数値に潰れてしまうため文字列に上書きする
     *
     * @return array<string, string>
     */
    public function getCasts()
    {
        $casts = parent::getCasts();

        if ($this->usesUuidPrimaryKey()) {
            $casts[$this->getKeyName()] = 'string';
        }

        return $casts;
    }

    /**
     * このモデルがUUIDの主キーを使うか
     */
    public function usesUuidPrimaryKey(): bool
    {
        if (! TidbMode::isEnabled()) {
            return false;
        }

        // 複合主キーのモデルは getKeyName() が配列を返す（Laravel本体はstring前提）
        /** @var string|array<int, string> $keyName */
        $keyName = $this->getKeyName();

        if (! is_string($keyName)) {
            return false;
        }

        return $keyName === 'id';
    }

    /**
     * 払い出すUUIDを生成する
     *
     * 別の採番方式にしたい場合はモデル側でオーバーライドする
     */
    protected function generateUuidPrimaryKey(): string
    {
        return (string) Str::uuid();
    }
}
