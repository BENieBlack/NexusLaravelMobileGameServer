# Guild機能実装完了

## 実装内容

### Package層 (nexus-guild)

#### Constants
- **GuildApplyStatus**: ギルド加入申請のステータス定数 (applied, accepted, rejected)
- **GuildRole**: ギルドメンバーの役職定数 (master, sub_master, member)

#### DTO
- **Guild**: ギルド情報のデータ転送オブジェクト
- **GuildApply**: ギルド加入申請のデータ転送オブジェクト
- **GuildMember**: ギルドメンバー情報のデータ転送オブジェクト

#### Repository Interface
- **GuildRepositoryInterface**: ギルド情報Repository (10メソッド)
- **GuildApplyRepositoryInterface**: ギルド加入申請Repository (9メソッド)
- **GuildMemberRepositoryInterface**: ギルドメンバーRepository (10メソッド)

#### Service
- **GuildService**: ギルド機能のビジネスロジック
  - ギルド作成、加入申請送信、承認、却下、脱退
  - 各種バリデーション（名前重複、所属チェック、満員チェック、権限チェック等）

#### Exception
- **GuildException**: ギルド関連の例外クラス（15種類の静的ファクトリメソッド）

### Application層 (api/)

#### Database
- **Migration**: sys_guild, sys_guild_member, sys_guild_apply テーブル作成
- **Model**: SysGuild, SysGuildMember, SysGuildApply

#### Adapter
- **GuildAdapter**: SysGuild ⇔ Guild 変換
- **GuildApplyAdapter**: SysGuildApply ⇔ GuildApply 変換
- **GuildMemberAdapter**: SysGuildMember ⇔ GuildMember 変換

#### Repository
- **SysGuildRepository**: GuildRepositoryInterface実装
- **SysGuildApplyRepository**: GuildApplyRepositoryInterface実装
- **SysGuildMemberRepository**: GuildMemberRepositoryInterface実装

#### UseCase
- **GuildCreateUseCase**: ギルド作成
- **GuildApplySendUseCase**: ギルド加入申請送信
- **GuildApplyAcceptUseCase**: ギルド加入申請承認
- **GuildApplyRejectUseCase**: ギルド加入申請却下
- **GuildLeaveUseCase**: ギルド脱退

#### Response
- **GuildCreateResponse**: ギルド作成レスポンス
- **GuildApplySendResponse**: 加入申請送信レスポンス
- **GuildApplyAcceptResponse**: 加入申請承認レスポンス
- **GuildApplyRejectResponse**: 加入申請却下レスポンス

#### Controller
- **GuildController**: 5つのエンドポイント実装
  - create, applySend, applyAccept, applyReject, leave

#### Error Codes
- **GameErrorCode**: Guild関連エラーコード16個追加 (10900-10915)

## データベース設計

### sys_guild
```sql
- id (bigint, auto_increment, primary key)
- name (varchar(100), unique) -- ギルド名
- description (text) -- ギルド説明
- level (int, default: 1) -- ギルドレベル
- exp (bigint, default: 0) -- ギルド経験値
- max_members (int, default: 30) -- 最大メンバー数
- created_at, updated_at
```

### sys_guild_member
```sql
- id (bigint, auto_increment, primary key)
- sys_guild_id (bigint) -- ギルドID
- sys_player_id (bigint) -- プレイヤーID
- role (enum: master, sub_master, member) -- 役職
- joined_at (datetime) -- 加入日時
- created_at, updated_at
- unique(sys_guild_id, sys_player_id)
```

### sys_guild_apply
```sql
- id (bigint, auto_increment, primary key)
- sys_guild_id (bigint) -- ギルドID
- sys_player_id (bigint) -- プレイヤーID
- status (enum: applied, accepted, rejected) -- ステータス
- created_at, updated_at
- index(sys_guild_id, sys_player_id, status)
```

## ビジネスルール

1. **ギルド作成**
   - プレイヤーは既にギルドに所属していない
   - ギルド名は一意である
   - 作成者は自動的にマスターになる

2. **加入申請**
   - プレイヤーは既にギルドに所属していない
   - 同じギルドへの重複申請は不可
   - ギルドが満員でない

3. **申請承認**
   - 承認者はマスターまたはサブマスター
   - 申請がAppliedステータスである
   - ギルドが満員でない
   - 承認後、申請者はメンバーとして追加される

4. **申請却下**
   - 却下者はマスターまたはサブマスター
   - 申請がAppliedステータスである

5. **ギルド脱退**
   - マスターは脱退できない（ギルド譲渡機能が必要）
   - 脱退後、全ての申請レコードが削除される

## 命名規則

- **統一ルール**: すべてのクラス（Service/UseCase/Repository/Model/DTO/Controller）はドメイン名を含む
- **Package Service**: GuildService
- **Application UseCase**: GuildCreateUseCase, GuildApplySendUseCase, etc.
- **Repository**: SysGuildRepository, SysGuildApplyRepository, SysGuildMemberRepository
- **Model**: SysGuild, SysGuildMember, SysGuildApply
- **DTO**: Guild, GuildApply, GuildMember
- **Adapter**: GuildAdapter, GuildApplyAdapter, GuildMemberAdapter
- **Controller**: GuildController
- **Response**: GuildCreateResponse, GuildApplySendResponse, etc.

## テスト

- **ConstantsTest**: 6テスト、18アサーション、全てパス

## 次のステップ（未実装）

1. **Controller用Requestクラス作成**（バリデーション）
2. **Routes定義追加**（api.php）
3. **一覧取得UseCase**: ギルド一覧、メンバー一覧、申請一覧
4. **ギルド詳細取得UseCase**
5. **メンバー役職変更UseCase**（マスターのみ）
6. **メンバーキックUseCase**（マスター/サブマスターのみ）
7. **ギルドマスター譲渡UseCase**
8. **ギルド情報更新UseCase**（名前、説明変更）
9. **統合テスト**（Feature Test）
10. **Service層のUnit Test**

## ファイル一覧

### Package層 (12ファイル)
```
packages/nexus-guild/
├── composer.json
├── README.md
├── src/
│   ├── Constants/
│   │   ├── GuildApplyStatus.php
│   │   └── GuildRole.php
│   ├── Dto/
│   │   ├── Guild.php
│   │   ├── GuildApply.php
│   │   └── GuildMember.php
│   ├── Repositories/
│   │   ├── GuildRepositoryInterface.php
│   │   ├── GuildApplyRepositoryInterface.php
│   │   └── GuildMemberRepositoryInterface.php
│   ├── Services/
│   │   └── GuildService.php
│   └── Exceptions/
│       └── GuildException.php
└── tests/
    └── ConstantsTest.php
```

### Application層 (18ファイル)
```
api/
├── database/migrations/sys/
│   └── 2026_08_07_000001_create_guild_tables.php
├── app/
│   ├── Models/Sys/
│   │   ├── SysGuild.php
│   │   ├── SysGuildMember.php
│   │   └── SysGuildApply.php
│   ├── Adapters/Guild/
│   │   ├── GuildAdapter.php
│   │   ├── GuildApplyAdapter.php
│   │   └── GuildMemberAdapter.php
│   ├── Repositories/Sys/
│   │   ├── SysGuildRepository.php
│   │   ├── SysGuildApplyRepository.php
│   │   └── SysGuildMemberRepository.php
│   ├── Domain/Guild/UseCases/
│   │   ├── GuildCreateUseCase.php
│   │   ├── GuildApplySendUseCase.php
│   │   ├── GuildApplyAcceptUseCase.php
│   │   ├── GuildApplyRejectUseCase.php
│   │   └── GuildLeaveUseCase.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── GuildController.php
│   │   └── Responses/Guild/
│   │       ├── GuildCreateResponse.php
│   │       ├── GuildApplySendResponse.php
│   │       ├── GuildApplyAcceptResponse.php
│   │       └── GuildApplyRejectResponse.php
│   └── Exceptions/
│       └── GameErrorCode.php (更新)
└── composer.json (更新)
```

## 実装状況
- ✅ Package層完成（12ファイル）
- ✅ Application層完成（18ファイル）
- ✅ Migration実行成功
- ✅ Constants Test 全パス
- ⏳ Routes定義（未実装）
- ⏳ Request Validation（未実装）
- ⏳ 統合テスト（未実装）
