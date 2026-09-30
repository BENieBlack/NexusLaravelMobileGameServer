<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(Request $request): View
    {
        $playerId = (int) $request->input('player_id');
        $player = $playerId > 0
            ? DB::connection('sys')->table('sys_player')->where('id', $playerId)->first()
            : null;
        $activeTab = $request->input('tab', 'info');

        $records = [];

        return view('players.index', compact('player', 'activeTab', 'records'));
    }

    public function list(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $players = DB::connection('sys')->table('sys_player')
            ->when($query !== '', fn ($builder) => $builder
                ->where('my_id', 'like', "%{$query}%")
                ->orWhere('uuid', 'like', "%{$query}%"))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('players.list', compact('query', 'players'));
    }

    private function loadTab(int $playerId, string $tab): array
    {
        $tables = [
            'message' => 'trx_message',
            'unit' => 'trx_unit',
            'purchase' => 'trx_in_app_purchase',
        ];

        if (! isset($tables[$tab])) {
            return [];
        }

        $records = [];
        foreach ($this->trxConnections() as $connection) {
            $schema = DB::connection($connection)->getSchemaBuilder();
            if (! $schema->hasTable($tables[$tab])) {
                continue;
            }

            $records[$connection] = DB::connection($connection)
                ->table($tables[$tab])
                ->where('sys_player_id', $playerId)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        return $records;
    }

    private function trxConnections(): array
    {
        $count = max(1, (int) env('DB_SHARD_COUNT', 2));

        return array_map(static fn (int $number): string => "trx{$number}", range(1, $count));
    }
}
