@extends('layouts.dashboard')
@section('title', 'アカウント一覧 - Tool')
@section('content')
<div class="page">
    @include('adm.partials.index')
    <div class="toolbar"><h1>アカウント一覧</h1><a class="button" href="{{ route('adm.accounts.create') }}">作成</a></div>
    <table><thead><tr><th>ID</th><th>名前</th><th>メールアドレス</th><th></th></tr></thead><tbody>
    @foreach ($accounts as $account)<tr><td>{{ $account->id }}</td><td>{{ $account->name }}</td><td>{{ $account->email }}</td><td><a class="edit" href="{{ route('adm.accounts.edit', $account) }}">編集</a></td></tr>@endforeach
    </tbody></table>
</div>
@endsection
