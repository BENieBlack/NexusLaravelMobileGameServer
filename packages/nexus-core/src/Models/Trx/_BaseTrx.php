<?php

namespace Nexus\Core\Models\Trx;

use Nexus\Core\Models\_BaseModel;

/**
 * _BaseTrx
 *
 * Trxデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるトランザクションデータ
 */
abstract class _BaseTrx extends _BaseModel implements _BaseTrxInterface
{
    /**
     * データベース接続名（trx1, trx2など）
     *
     * 既定はnull。プレイヤーの割り当てシャードから解決するため、
     * ここで固定してしまうと全プレイヤーが同じシャードを向いてしまう。
     * setConnection() や ::on() で明示された場合はそちらが優先される。
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * 既定のデータベース接続名（シャードを解決できない場合の退避先）
     */
    protected string $fallbackConnection = 'trx1';

    /**
     * 使用するデータベース接続名を返す
     *
     * 優先順位:
     * 1. setConnection() / ::on() で明示された接続
     * 2. ログイン中プレイヤーの割り当てシャード
     * 3. $fallbackConnection（コンソール等、プレイヤーが居ない文脈）
     */
    public function getConnectionName(): string
    {
        return $this->connection ?? static::resolveShardConnection() ?? $this->fallbackConnection;
    }

    /**
     * ログイン中プレイヤーの割り当てシャードを返す
     *
     * アプリケーション層でオーバーライドして接続名を返す。
     * 解決できない場合はnullを返し、$fallbackConnection が使われる。
     */
    protected static function resolveShardConnection(): ?string
    {
        return null;
    }

    /**
     * Unit of Workパターンを使用
     */
    protected bool $usesUnitOfWork = true;

    /**
     * 相対的な変更を記録する配列
     * 競合状態を避けるため、SET amount = amount + 10 のような相対的な更新を記録
     *
     * @var array<string, int> カラム名 => 増減値
     */
    protected array $relativeChanges = [];

    /**
     * SELECTキーを取得
     */
    public function getSelectKey(): string
    {
        return $this->getSelectKeys()[0] ?? 'sys_player_id';
    }

    /**
     * ユニークキーを取得
     *
     * @return list<string>
     */

    /**
     * 相対的な変更を記録（内部用）
     *
     * @param  string  $column  カラム名
     * @param  int  $value  増減値（正の値で増加、負の値で減少）
     */
    protected function addRelativeChange(string $column, int $value): void
    {
        if (! isset($this->relativeChanges[$column])) {
            $this->relativeChanges[$column] = 0;
        }
        $this->relativeChanges[$column] += $value;
    }

    /**
     * 相対的な変更を取得
     *
     * @return array<string, int>
     */
    public function getRelativeChanges(): array
    {
        return $this->relativeChanges;
    }

    /**
     * 相対的な変更をクリア
     */
    public function clearRelativeChanges(): void
    {
        $this->relativeChanges = [];
    }

    /**
     * 相対的な変更があるかチェック
     */
    public function hasRelativeChanges(): bool
    {
        return ! empty($this->relativeChanges);
    }

    /**
     * 属性を設定（オーバーライド）
     * 数値カラムの変更を検知して、相対的な変更として記録
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        // 既存モデルで、数値カラムの場合は相対的な変更を検知
        if ($this->exists && is_numeric($value)) {
            // 現在の値を取得（getAttribute()で現在設定されている値を取得）
            $currentValue = $this->getAttribute($key);

            // 現在の値が存在し、かつ数値の場合のみ相対的な変更を記録
            if ($currentValue !== null && is_numeric($currentValue)) {
                $diff = $value - $currentValue;

                // 変更がある場合のみ記録
                if ($diff != 0) {
                    $this->addRelativeChange($key, $diff);
                }
            }
        }

        // 通常の属性設定を実行
        return parent::setAttribute($key, $value);
    }
}
