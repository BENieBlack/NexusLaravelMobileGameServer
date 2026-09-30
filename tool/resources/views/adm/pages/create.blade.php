@extends('layouts.dashboard')

@section('title', 'ページ作成 - Tool')

@section('content')
<div class="page">
    <h1>ページ作成</h1>
    @include('adm.partials.form-messages')
    <form method="POST" action="{{ route('adm.pages.store') }}">
        @csrf
        <label>ページID<input name="id" value="{{ old('id') }}" required></label>
        <button type="submit">作成</button>
    </form>
</div>
@endsection
