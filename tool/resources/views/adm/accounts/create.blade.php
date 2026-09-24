@extends('layouts.dashboard')

@section('title', 'アカウント作成 - Tool')

@section('content')
<div class="page">
    <h1>アカウント作成</h1>
    @include('adm.partials.form-messages')
    <form method="POST" action="{{ route('adm.accounts.store') }}">
        @csrf
        <label>名前<input name="name" value="{{ old('name') }}" required></label>
        <label>メールアドレス<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>パスワード<input type="password" name="password" required></label>
        <label>パスワード確認<input type="password" name="password_confirmation" required></label>
        <button type="submit">作成</button>
    </form>
</div>
@endsection
