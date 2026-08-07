# nexus-guild

Guild management package for Nexus framework.

## Features

- Guild creation and management
- Guild join request system
- Guild member management
- Guild level and experience system

## Package Structure

```
nexus-guild/
├── src/
│   ├── Constants/
│   │   └── GuildRequestStatus.php    # Guild request status constants
│   ├── Dto/
│   │   ├── GuildDto.php               # Guild data transfer object
│   │   ├── GuildRequestDto.php        # Guild request DTO
│   │   └── GuildMemberDto.php         # Guild member DTO
│   ├── Repositories/
│   │   ├── GuildRepositoryInterface.php
│   │   └── GuildRequestRepositoryInterface.php
│   ├── Services/
│   │   └── GuildService.php           # Business logic
│   └── Exceptions/
│       └── GuildException.php
```

## Design Principles

- DTO pattern for data abstraction
- Repository interface for testability
- Service layer for business logic validation
- Aligned with nexus-friend, nexus-player structure

## Usage

### Application Layer Implementation

```php
// Model
class SysGuild extends Model { ... }
class SysGuildRequest extends Model { ... }

// Repository
class SysGuildRepository implements GuildRepositoryInterface { ... }
class SysGuildRequestRepository implements GuildRequestRepositoryInterface { ... }

// Adapter
class GuildAdapter { 
    public static function toDto(SysGuild $guild): GuildDto { ... }
}

// UseCase
class GuildCreateUseCase {
    public function __construct(
        private GuildService $guildService,
        // ...
    ) {}
}
```

## Database Schema

### sys_guild
- id (autoincrement)
- name (varchar)
- description (text)
- level (int)
- exp (int)
- max_members (int)
- created_at, updated_at

### sys_guild_apply
- id (autoincrement)
- sys_guild_id (bigint)
- sys_player_id (bigint)
- status (enum: applied, accepted, rejected)
- created_at, updated_at

### sys_guild_member
- id (autoincrement)
- sys_guild_id (bigint)
- sys_player_id (bigint)
- role (enum: master, sub_master, member)
- joined_at (datetime)
- created_at, updated_at
