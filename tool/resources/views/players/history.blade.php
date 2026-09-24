@extends('layouts.dashboard')

@section('title', 'プレイヤー履歴 - Tool')

@section('content')
<style>
    .history-page { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }
    .search, .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    .search { display: flex; gap: 10px; }
    input { flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
    button { padding: 10px 18px; border: 0; border-radius: 6px; background: #667eea; color: #fff; }
    .tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
    .tab { padding: 10px 14px; border-radius: 6px; background: #e5e7eb; color: #374151; text-decoration: none; }
    .tab.active { background: #667eea; color: #fff; }
    table { width: 100%; border-collapse: collapse; } th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
    .empty { color: #6b7280; }
</style>
<div class="history-page">
    <h1>プレイヤー履歴</h1>
    @if ($player)
        <nav class="tabs">
            @foreach ($tabs as $tab => $table)
                <a class="tab {{ $activeTab === $tab ? 'active' : '' }}" href="{{ route('player.log', ['player_id' => $player->id, 'tab' => $tab]) }}">{{ $table }}</a>
            @endforeach
        </nav>
        @forelse ($records as $connection => $rows)
            <section class="card"><h2>{{ $connection }}</h2>
                @if ($rows->isEmpty())<p class="empty">該当ログはありません。</p>@else
                    <table><thead><tr>@foreach (array_keys((array) $rows->first()) as $key)<th>{{ $key }}</th>@endforeach</tr></thead><tbody>
                    @foreach ($rows as $row)<tr>@foreach ((array) $row as $value)<td>{{ is_scalar($value) || $value === null ? ($value ?? '-') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>@endforeach</tr>@endforeach
                    </tbody></table>
                @endif
            </section>
        @empty
            <section class="card"><p class="empty">該当ログまたは対象テーブルがありません。</p></section>
        @endforelse
    @else
        <section class="card"><p class="empty">プレイヤー検索から表示するプレイヤーを選択してください。</p></section>
    @endif
</div>
@endsection
