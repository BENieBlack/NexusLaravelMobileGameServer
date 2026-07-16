# SignInUseCase Test Documentation

## Overview
SignInUseCaseのNexusAuth統合版のテスト実装完了。

## Test File
`tests/Unit/UseCases/Auth/SignInUseCaseUnitTest.php`

## Test Cases

### 1. test_handle_signs_in_existing_player_successfully
**目的**: 既存プレイヤーが正常にサインインできることを確認

**準備**:
- デバイスID: `test-device-uuid`
- モックデバイス、プレイヤー、トークンを設定

**実行**:
- `SignInUseCase::handle()` を呼び出し

**検証**:
- SignInResponseが返されること
- 正しいプレイヤー、デバイス、トークン情報が含まれること

### 2. test_handle_throws_exception_when_device_not_found
**目的**: デバイスが見つからない場合に例外がスローされること

**準備**:
- 存在しないデバイスID

**実行**:
- `SignInUseCase::handle()` を呼び出し

**検証**:
- GameExceptionがスローされること
- エラーメッセージに「Device ID not found」が含まれること

### 3. test_handle_throws_exception_when_player_not_found
**目的**: プレイヤーが見つからない場合に例外がスローされること

**準備**:
- デバイスは存在するがプレイヤーIDが無効

**実行**:
- `SignInUseCase::handle()` を呼び出し

**検証**:
- GameExceptionがスローされること
- エラーメッセージに「Player not found for device」が含まれること

### 4. test_handle_deletes_old_tokens
**目的**: 古いトークンが削除されること

**準備**:
- プレイヤーに古いトークンが3つ存在

**実行**:
- `SignInUseCase::handle()` を呼び出し

**検証**:
- `TokenRepositoryInterface::deleteByPlayerId()` が呼ばれること
- 3つのトークンが削除されること

### 5. test_handle_updates_last_login_time
**目的**: 最終ログイン日時が更新されること

**準備**:
- プレイヤーとデバイスが存在

**実行**:
- `SignInUseCase::handle()` を呼び出し

**検証**:
- `PlayerAuthService::updateLastLogin()` が呼ばれること
- デバイスIDが正しく渡されること

## NexusAuth Integration Points

### Services Used
1. **TokenService** (NexusAuth\Services\TokenService)
   - `generateToken(int $playerId)`: 新しいトークンを生成

2. **PlayerAuthService** (NexusAuth\Services\PlayerAuthService)
   - `updateLastLogin(string $deviceUuid)`: 最終ログイン更新

### Repositories Used
1. **DeviceRepositoryInterface**
   - `selectByDeviceId(string $deviceUuid)`: デバイス検索

2. **PlayerRepositoryInterface**
   - `selectById(int $id)`: プレイヤー検索

3. **TokenRepositoryInterface**
   - `deleteByPlayerId(int $playerId)`: 古いトークン削除
   - `selectByRefreshToken(string $refreshToken)`: トークン取得

## Test Limitations

### Current Issue
- トランザクション処理により、DB接続が必要
- `UseCaseTrait::executeWithTransaction()` がDB接続を要求
- 完全なユニットテストには、トランザクションのモック化が必要

### Alternative Approach
統合テスト (`SignInUseCaseTest.php`) を使用:
- 実際のDBを使用
- トランザクション処理を含む完全なテスト
- RefreshMultipleDatabasesトレイトを使用

## Test Execution

```bash
# ユニットテスト（モックベース、トランザクション問題あり）
./vendor/bin/phpunit tests/Unit/UseCases/Auth/SignInUseCaseUnitTest.php

# 統合テスト（DB必要、推奨）
./vendor/bin/phpunit tests/Unit/UseCases/Auth/SignInUseCaseTest.php
```

## Next Steps

1. Docker環境を起動してDB接続を確保
2. 統合テスト (SignInUseCaseTest.php) をNexusAuth対応に更新
3. すべてのテストケースを実行して検証

## Code Coverage

テスト対象:
- ✅ 正常系: 既存プレイヤーのサインイン
- ✅ 異常系: デバイスが見つからない
- ✅ 異常系: プレイヤーが見つからない
- ✅ 正常系: 古いトークンの削除
- ✅ 正常系: 最終ログイン時刻の更新
