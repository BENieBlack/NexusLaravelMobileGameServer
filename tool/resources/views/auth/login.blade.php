@extends('layouts.app')

@section('title', 'ログイン - Tool')

@section('content')
<div class="container">
    <div class="card">
        <h1>Tool Login</h1>
        
        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="{{ route('login') }}">
            @csrf
            
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">ログイン状態を保持する</label>
            </div>
            
            <button type="submit">ログイン</button>
        </form>
    </div>
</div>
@endsection
