@extends('layouts.dashboard')
@section('title', 'ページ編集 - Tool')
@section('content')
<div class="page"><h1>ページ編集</h1>@include('adm.partials.form-messages')
<form method="POST" action="{{ route('adm.pages.update', $page) }}">@csrf @method('PUT')
<label>ページID<input name="id" value="{{ old('id', $page->id) }}" required></label><button>更新</button></form></div>
@endsection
