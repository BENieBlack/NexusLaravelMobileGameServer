@extends('layouts.dashboard')

@section('title', 'ロール作成 - Tool')

@section('content')
<div class="page">
    <h1>ロール作成</h1>
    @include('adm.partials.form-messages')
    <form method="POST" action="{{ route('adm.roles.store') }}">
        @csrf
        <label>ロールID<input name="id" value="{{ old('id') }}" required></label>
        <label>ロール名<input name="name" value="{{ old('name') }}" required></label>
        <button type="submit">作成</button>
    </form>
</div>
@endsection
