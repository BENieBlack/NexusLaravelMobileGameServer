@extends('layouts.dashboard')
@section('title', 'ロール一覧 - Tool')
@section('content')
<div class="page">
    @include('adm.partials.index')
    <div class="toolbar"><h1>ロール一覧</h1><a class="button" href="{{ route('adm.roles.create') }}">作成</a></div>
    <table><thead><tr><th>ID</th><th>名前</th><th></th></tr></thead><tbody>
    @foreach ($roles as $role)<tr><td>{{ $role->id }}</td><td>{{ $role->name }}</td><td><a class="edit" href="{{ route('adm.roles.edit', $role) }}">編集</a></td></tr>@endforeach
    </tbody></table>
</div>
@endsection
