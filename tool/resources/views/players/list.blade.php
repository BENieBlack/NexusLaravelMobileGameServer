@extends('layouts.dashboard')
@section('title', 'プレイヤー検索 - Tool')
@section('content')
<style>
    .player-page { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }
    .search, .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    .search { display: flex; gap: 10px; } input { flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
    button { padding: 10px 18px; border: 0; border-radius: 6px; background: #667eea; color: #fff; }
    table { width: 100%; border-collapse: collapse; } th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    tr:hover { background: #f8fafc; }
</style>
<div class="player-page"><h1>プレイヤー検索</h1>
    <form class="search" method="GET" action="{{ route('player.list') }}"><input name="q" value="{{ $query }}" placeholder="my_id または UUID"><button>検索</button></form>
    <section class="card"><table><thead><tr><th>ID</th><th>my_id</th><th>UUID</th><th>名前</th><th>作成日時</th></tr></thead><tbody>
    @forelse ($players as $player)<tr onclick="location.href='{{ route('player.index', ['player_id' => $player->id]) }}'" style="cursor:pointer"><td>{{ $player->id }}</td><td>{{ $player->my_id }}</td><td>{{ $player->uuid }}</td><td>{{ $player->name }}</td><td>{{ $player->created_at }}</td></tr>@empty<tr><td colspan="5">プレイヤーが見つかりません。</td></tr>@endforelse
    </tbody></table></section>
</div>
@endsection
