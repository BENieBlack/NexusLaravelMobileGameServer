<?php

namespace App\Console\Commands\Mailbox;

use App\Domain\Mailbox\Services\TemplateEngine;
use Illuminate\Console\Command;

/**
 * TestTemplateEngineCommand
 *
 * TemplateEngineの動作確認コマンド
 */
class TestTemplateEngineCommand extends Command
{
    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'mailbox:test-template';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'TemplateEngineの動作テスト';

    /**
     * コマンド実行
     */
    public function handle(): int
    {
        $this->info('TemplateEngine 動作テスト開始');
        $this->newLine();

        $engine = app(TemplateEngine::class);

        // テスト1: カスタムパラメータ
        $this->info('[テスト1] カスタムパラメータ');
        $template1 = 'こんにちは、{player_name}様。現在のレベルは{player_level}です。';
        $params1 = [
            'player_name' => 'Commander',
            'player_level' => '50',
        ];
        $result1 = $engine->render($template1, $params1);
        $this->line("テンプレート: {$template1}");
        $this->line("結果: {$result1}");
        $this->newLine();

        // テスト2: システムプレースホルダー
        $this->info('[テスト2] システムプレースホルダー');
        $template2 = 'サーバー: {server_name}, 日時: {date} {time}';
        $result2 = $engine->render($template2, []);
        $this->line("テンプレート: {$template2}");
        $this->line("結果: {$result2}");
        $this->newLine();

        // テスト3: 複数プレースホルダー
        $this->info('[テスト3] 複数プレースホルダー（戦闘レポート）');
        $template3 = '{player_name}様、{alliance_name}との戦闘が終了しました。結果: {battle_result}。獲得報酬: {reward_amount}ゴールド';
        $params3 = [
            'player_name' => 'Commander',
            'alliance_name' => 'Warriors',
            'battle_result' => 'Victory',
            'reward_amount' => '1000',
        ];
        $result3 = $engine->render($template3, $params3);
        $this->line("テンプレート: {$template3}");
        $this->line("結果: {$result3}");
        $this->newLine();

        // テスト4: 未定義プレースホルダー
        $this->info('[テスト4] 未定義プレースホルダー');
        $template4 = 'Hello {unknown_key}, your name is {player_name}';
        $params4 = ['player_name' => 'Alice'];
        $result4 = $engine->render($template4, $params4);
        $this->line("テンプレート: {$template4}");
        $this->line("結果: {$result4}");
        $this->newLine();

        // サポートしているプレースホルダー一覧
        $this->info('[サポートプレースホルダー一覧]');
        $supported = $engine->getSupportedPlaceholders();
        foreach ($supported as $resolverName => $keys) {
            $this->line("{$resolverName}: ".implode(', ', $keys));
        }
        $this->newLine();

        $this->info('テスト完了');

        return Command::SUCCESS;
    }
}
