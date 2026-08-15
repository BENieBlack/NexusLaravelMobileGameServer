<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Collection;

/**
 * MstPlayerLevel Model
 *
 * プレイヤーレベルマスターデータ
 * レベル毎に必要な経験値と最大スタミナを定義
 *
 * 【重要】経験値の仕様について
 * ---------------------------------
 * required_expは「レベル1から見て、そのレベルに到達するまでに必要な累積経験値」を表します。
 * レベルアップしても経験値はリセットされません。
 *
 * 例:
 * - level1->level2に50exp必要
 * - level2->level3にさらに91exp必要（累積141exp）
 *
 * この場合、以下のようにデータが登録されます:
 * - level=1, required_exp=0    (初期状態)
 * - level=2, required_exp=50   (level1から50exp獲得でlevel2になる)
 * - level=3, required_exp=141  (level1から累積141exp獲得でlevel3になる)
 *
 * 使用例:
 * ```php
 * // 現在level=2、現在の累積経験値=100のプレイヤー
 * $currentLevel = 2;
 * $currentExp = 100;
 *
 * // level3になるために必要な経験値を確認
 * $nextLevelData = MstPlayerLevel::findByLevel(3);
 * $needExp = $nextLevelData->required_exp; // 141
 *
 * if ($currentExp >= $needExp) {
 *     // レベルアップ可能 (100 < 141 なので、この例ではまだ不可)
 * }
 * ```
 *
 * @property int $max_stamina
 * @property int $required_exp
 */
class MstPlayerLevel extends _BaseMst
{
    protected $table = 'mst_player_level';

    /**
     * 主キーはlevelカラム
     */
    protected $primaryKey = 'level';

    /**
     * auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 主キーの型
     */
    protected $keyType = 'int';

    protected $fillable = [
        'deploy_key',
        'level',
        'required_exp',
        'max_stamina',
    ];

    protected $casts = [
        'level' => 'integer',
        'deploy_key' => 'integer',
        'required_exp' => 'integer',
        'max_stamina' => 'integer',
    ];

    /**
     * 指定レベルのデータを取得
     *
     * @param  int  $level  レベル
     */
    public static function findByLevel(int $level): ?self
    {
        return self::find($level);
    }

    /**
     * 全レベルデータを取得（レベル昇順）
     */
    public static function selectAllLevels(): Collection
    {
        return self::orderBy('level')->get();
    }

    /**
     * レベルを取得
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * 必要経験値を取得
     */
    public function getRequiredExp(): int
    {
        return $this->getAttribute('required_exp');
    }

    /**
     * 最大スタミナを取得
     */
    public function getMaxStamina(): int
    {
        return $this->getAttribute('max_stamina');
    }

    /**
     * デプロイキーを取得
     */
    public function getDeployKey(): int
    {
        return $this->getAttribute('deploy_key');
    }

    /**
     * 指定レベルの最大スタミナを取得
     *
     * @param  int  $level  レベル
     */
    public static function findMaxStaminaForLevel(int $level): ?int
    {
        $data = self::findByLevel($level);

        return $data?->getMaxStamina();
    }

    /**
     * 指定レベルに必要な累積経験値を取得
     *
     * @param  int  $level  レベル
     */
    public static function findRequiredExpForLevel(int $level): ?int
    {
        $data = self::findByLevel($level);

        return $data?->getRequiredExp();
    }

    /**
     * 累積経験値から現在のレベルを計算
     *
     * @param  int  $exp  累積経験値
     * @return int 現在のレベル
     */
    public static function calculateLevelFromExp(int $exp): int
    {
        // 全レベルデータを取得
        $levelCollection = self::orderBy('level', 'desc')->get();

        // 上から順に探索し、required_exp以上のレベルを見つける
        foreach ($levelCollection as $levelData) {
            if ($exp >= $levelData->getRequiredExp()) {
                return $levelData->getLevel();
            }
        }

        // 最小レベル（1）を返す
        return 1;
    }

    /**
     * 最大レベルを取得
     */
    public static function getMaxLevel(): int
    {
        return self::max('level') ?? 100;
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーがlevelカラムであり、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
