<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * MstEquipmentLevel Model
 *
 * 装備のレベルアップに必要な経験値を管理するマスターデータ
 * レアリティとレベルごとに必要経験値が定義される
 *
 * 【重要】経験値の仕様について
 * ---------------------------------
 * required_expは「レベル1から見て、そのレベルに到達するまでに必要な累積経験値」を表します。
 * レベルアップしても経験値はリセットされません。
 *
 * 例:
 * - level1->level2に100exp必要
 * - level2->level3にさらに200exp必要
 *
 * この場合、以下のようにデータを登録します:
 * - level=1, required_exp=100  (level1から100exp獲得でlevel2になる)
 * - level=2, required_exp=300  (level1から累積300exp獲得でlevel3になる)
 * - level=3, required_exp=600  (level1から累積600exp獲得でlevel4になる)
 *
 * 使用例:
 * ```php
 * // 現在level=2、現在の累積経験値=250の装備がある場合
 * $currentLevel = 2;
 * $currentExp = 250;
 *
 * // level3になるために必要な経験値を確認
 * $nextLevelData = MstEquipmentLevel::findByRarityAndLevel('UR', 3);
 * $needExp = $nextLevelData->required_exp; // 300
 *
 * if ($currentExp >= $needExp) {
 *     // レベルアップ可能 (250 < 300 なので、この例ではまだ不可)
 * }
 * ```
 *
 * @property string $rarity
 * @property int $level
 * @property int $required_exp
 * @property int $deploy_key
 */
class MstEquipmentLevel extends _BaseMst
{
    protected $table = 'mst_equipment_level';

    /**
     * 複合主キーのため、auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 複合主キーの指定
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['rarity', 'level'];

    /**
     * 主キーの型
     */
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'rarity',
        'level',
        'required_exp',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'rarity' => 'string',
        'level' => 'integer',
        'required_exp' => 'integer',
    ];

    /**
     * 複合主キーを設定
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (! is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     *
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * 指定したレアリティとレベルの経験値データを取得
     */
    public static function findByRarityAndLevel(string $rarity, int $level): ?self
    {
        return self::where('rarity', $rarity)
            ->where('level', $level)
            ->first();
    }

    /**
     * 指定したレアリティの全レベルデータを取得
     *
     * @return Collection
     */
    public static function selectAllLevelsByRarity(string $rarity)
    {
        return self::where('rarity', $rarity)
            ->orderBy('level')
            ->get();
    }

    /**
     * 最大レベルを取得
     */
    public static function selectMaxLevel(string $rarity): ?int
    {
        return self::where('rarity', $rarity)
            ->max('level');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(rarity, level)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
