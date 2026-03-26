@extends('layouts.dashboard')

@section('title', 'ダッシュボード - Tool')

@section('content')
<style>
    .dashboard-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .header {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .header h1 {
        margin: 0;
        color: #333;
        font-size: 24px;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .username {
        color: #666;
        font-size: 14px;
    }
    
    .logout-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .welcome-card {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
    }
    
    .welcome-card h2 {
        color: #333;
        margin-bottom: 15px;
        font-size: 28px;
    }
    
    .welcome-card p {
        color: #666;
        font-size: 16px;
        line-height: 1.6;
    }
</style>

<div class="dashboard-container">
    <div class="header">
        <h1>運営ツール</h1>
        <div class="user-info">
            <span class="username">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="logout-btn">ログアウト</button>
            </form>
        </div>
    </div>
    
    <div class="welcome-card">
        <h2>ようこそ、{{ auth()->user()->name }}さん</h2>
        <p>運営ツールにログインしました。<br>今後、ここに各種管理機能が追加される予定です。</p>
    </div>
</div>
@endsection
