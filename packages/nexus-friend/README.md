# Nexus Friend Package

Friend management package for Nexus mobile game backend framework.

## Features

- Friend apply (send/accept/reject)
- Friend list management
- Friend deletion (logical delete)
- Bidirectional relationship validation
- DTO-based data abstraction
- Repository interface for testability

## Installation

This package is part of the Nexus monorepo. Add to your `composer.json`:

```json
{
    "require": {
        "nexus/friend": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "./packages/nexus-friend"
        }
    ]
}
```

## Usage

### DTOs

```php
use NexusFriend\DataTransferObjects\FriendApply;
use NexusFriend\DataTransferObjects\Friend;

// Friend apply DTO
$applyDto = new FriendApply(
    id: 1,
    senderPlayerId: 100,
    receiverPlayerId: 200,
    status: FriendStatus::APPLIED,
    createdAt: new DateTime(),
    updatedAt: new DateTime()
);

// Friend DTO
$friendDto = new Friend(
    playerId: 200,
    myId: 'player_200',
    name: 'Player Name',
    level: 50
);
```

### Repository Interface

```php
use NexusFriend\Repositories\FriendApplyRepositoryInterface;

class SysFriendApplyRepository implements FriendApplyRepositoryInterface
{
    // Implement interface methods
}
```

### Service

```php
use NexusFriend\Services\FriendService;

$service = new FriendService($repository);

// Validate bidirectional relationship
$service->validateNoDuplicateApply($senderPlayerId, $receiverPlayerId);

// Validate authorization
$service->validateReceiverAuthorization($applyDto, $currentPlayerId);
```

## Architecture

### Package Layer (nexus-friend)
- **Constants:** Status enums
- **DTOs:** Data transfer objects
- **Repository Interface:** Abstraction for persistence
- **Services:** Domain business logic

### Application Layer (api/app)
- **Models:** Eloquent models (SysFriendApply)
- **Repositories:** Interface implementations
- **Adapters:** Model ↔ DTO conversion
- **UseCases:** Application-specific use cases

## Dependencies

- PHP 8.3+
- nexus/core-utilities

## Testing

```bash
cd packages/nexus-friend
vendor/bin/phpunit
```

## License

Proprietary
