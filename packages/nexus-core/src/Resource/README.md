# Resource（nexus-core）

Unified resource representation for game resources. `nexus/core` に含まれる（名前空間 `Nexus\Core\Resource\`）。

## Overview

This package provides a unified way to represent all types of game resources (Diamond, Unit, Equipment, Coin, Item, etc.) with a consistent interface.

## Features

- **Unified Resource DTO**: Single `Resource` class for all resource types
- **Type-Safe Enum**: 27 resource types with helper methods
- **Factory Methods**: Convenient static methods for creating resources
- **Metadata Support**: Flexible metadata field for additional information
- **Expiration Support**: Built-in expiration date handling
- **UUID Generation**: Automatic unique ID generation for each resource

## Installation

`nexus/core` に含まれるので、別にインストールするものはない。

## Usage

### Creating Resources

```php
use Nexus\Core\Resource\DataTransferObjects\Resource;

// Currency resources
$diamond = Resource::paidDiamond(1000);
$gold = Resource::gold(50000);
$coin = Resource::coin(10000);

// Item resources
$item = Resource::item('item_001', 5);
$consumable = Resource::consumable('potion_hp_001', 10);
$material = Resource::material('material_iron_001', 100);

// Unit resources with metadata
$unit = Resource::unit('unit_hero_001', 1, grade: 5, level: 1);

// Equipment resources
$equipment = Resource::equipment('equipment_sword_001', 1);
$weapon = Resource::weapon('weapon_legendary_001', 1);
```

### Resource Types

The package supports 27 resource types:

**Currency**: PAID_DIAMOND, GOLD, COIN

**Resources**: FOOD, WOOD, STONE, IRON, STAMINA, EXPERIENCE

**Items**: ITEM, CONSUMABLE, MATERIAL, TICKET

**Equipment**: UNIT, EQUIPMENT, WEAPON, ARMOR, ACCESSORY

**Points**: ALLIANCE_POINTS, PVP_POINTS, EVENT_POINTS, ACHIEVEMENT_POINTS

**Other**: GACHA_TICKET, VIP_POINTS, CUSTOM

### Resource Type Helpers

```php
use Nexus\Core\Resource\Enums\ResourceType;

// Check resource category
$type = ResourceType::PAID_DIAMOND;
$type->isCurrency();  // true
$type->isResource();  // false
$type->isItem();      // false

// Get label and icon
$type->label();  // "ダイヤ"
$type->icon();   // "💎"

// Convert from string
$type = ResourceType::fromString('paid_diamond');  // ResourceType::PAID_DIAMOND
```

## Resource Properties

```php
$resource = Resource::paidDiamond(1000);

$resource->getType();        // ResourceType::PAID_DIAMOND
$resource->getTypeValue();   // "paid_diamond"
$resource->getId();          // "paid_diamond"
$resource->getAmount();      // 1000
$resource->getExpireAt();    // null
$resource->getMetadata();    // null
$resource->getUniqueId();    // UUID v4 string
$resource->isValid();        // true (amount > 0)
```

## Array Conversion

```php
// To array
$array = $resource->toArray();
// [
//     'unique_id' => '...',
//     'type' => 'paid_diamond',
//     'id' => 'paid_diamond',
//     'amount' => 1000,
//     'expire_at' => null,
//     'metadata' => null,
// ]

// From array
$resource = Resource::fromArray($array);

// From type string
$resource = Resource::fromTypeString(
    'paid_diamond',
    'paid_diamond',
    1000
);
```

## Requirements

- PHP ^8.1
- ramsey/uuid ^4.0

## License

MIT
