@extends('layouts.dashboard')
@section('title', 'アカウント編集 - Tool')
@section('content')
<div class="page"><h1>アカウント編集</h1>@include('adm.partials.form-messages')
<form method="POST" action="{{ route('adm.accounts.update', $account) }}">@csrf @method('PUT')
<label>名前<input name="name" value="{{ old('name', $account->name) }}" required></label>
<label>メールアドレス<input type="email" name="email" value="{{ old('email', $account->email) }}" required></label>
<label>新しいパスワード<input type="password" name="password"></label>
<label>新しいパスワード確認<input type="password" name="password_confirmation"></label><button>更新</button></form></div>
@endsection
