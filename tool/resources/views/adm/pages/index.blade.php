@extends('layouts.dashboard')
@section('title', 'ページ一覧 - Tool')
@section('content')
<div class="page">
    @include('adm.partials.index')
    <div class="toolbar"><h1>ページ一覧</h1><a class="button" href="{{ route('adm.pages.create') }}">作成</a></div>
    <table><thead><tr><th>ID</th><th></th></tr></thead><tbody>
    @foreach ($pages as $page)<tr><td>{{ $page->id }}</td><td><a class="edit" href="{{ route('adm.pages.edit', $page) }}">編集</a></td></tr>@endforeach
    </tbody></table>
</div>
@endsection
