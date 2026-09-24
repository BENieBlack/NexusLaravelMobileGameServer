@extends('layouts.dashboard')
@section('title', 'ロール編集 - Tool')
@section('content')
<div class="page"><h1>ロール編集</h1>@include('adm.partials.form-messages')
<form method="POST" action="{{ route('adm.roles.update', $role) }}">@csrf @method('PUT')
<label>ロールID<input value="{{ $role->id }}" disabled></label>
<label>ロール名<input name="name" value="{{ old('name', $role->name) }}" required></label><button>更新</button></form></div>
@endsection
