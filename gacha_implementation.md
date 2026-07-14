# Gacha System Implementation Guide

## Overview

This document describes the complete gacha (lottery) system implementation for the mobile game. The system supports multiple gacha types including normal gacha, step-up gacha, and pickup gacha with flexible prize distribution.

## Database Schema

### Master Tables (mst_*)

1. **mst_gacha** - Main gacha configuration
   - `id` (PK): Gacha identifier
   - `gacha_type`: Type of gacha (normal, step_up, pickup, etc.)
   - `start_at`, `end_at`: Availability period
   - `daily_limit`: Maximum draws per day (nullable)

2. **mst_gacha_cost** - Cost configuration per draw count
   - `mst_gacha_id`: Reference to gacha
   - `mst_gacha_step_id`: Reference to specific step (nullable, for step-up gacha)
   - `draw_count`: Number of draws (1 for single, 10 for 10-pull)
   - `cost_type`: Type of cost (diamond, paid_diamond, item)
   - `cost_amount`: Amount required
   - `mst_item_id`: Item ID if cost_type is 'item'

3. **mst_gacha_rarity_rate** - Rarity probability configuration
   - `mst_gacha_id`: Reference to gacha
   - `rarity`: Rarity tier (SSR, SR, R)
   - `rate`: Rate in 1/10000 (e.g., 300 = 3%)

4. **mst_gacha_prize** - Prize pool configuration
   - `mst_gacha_id`: Reference to gacha
   - `rarity`: Prize rarity
   - `prize_type`: Type (unit, item, equipment)
   - `prize_target_id`: Target ID
   - `prize_amount`: Quantity given
   - `weight`: Weight for weighted random selection

5. **mst_gacha_step** - Step configuration for step-up gacha
   - `mst_gacha_id`: Reference to gacha
   - `step_number`: Step sequence number

6. **mst_gacha_step_guaranteed** - Guaranteed prize configuration
   - `mst_gacha_step_id`: Reference to step
   - `guaranteed_type`: Type (none, random, choice)
   - `guaranteed_rarity`: Rarity of guaranteed prize
   - `guaranteed_count`: Number of guaranteed prizes
   - `position`: Position in result (0 = random)

7. **mst_gacha_step_guaranteed_candidate** - Candidates for guaranteed prizes
   - `mst_gacha_step_guaranteed_id`: Reference to guaranteed config
   - `prize_type`: Type of prize
   - `prize_target_id`: Target ID
   - `prize_amount`: Quantity

### Transaction Tables (trx_*)

1. **trx_gacha** - Player's gacha progress
   - `sys_player_id`, `mst_gacha_id`: Composite unique key
   - `current_step`: Current step number (for step-up)
   - `daily_draw_count`: Today's draw count
   - `daily_reset_at`: Last daily reset timestamp
   - `total_draw_count`: Total lifetime draws
   - `total_reset_at`: Last total reset timestamp

2. **trx_gacha_history** - Draw history log
   - `sys_player_id`: Player reference
   - `mst_gacha_id`: Gacha reference
   - `step_number`: Step number (nullable)
   - `draw_count`: Number of draws
   - `cost_type`, `cost_amount`: Cost consumed
   - `prize_*`: Prize information
   - `is_guaranteed`: Whether prize was guaranteed

## API Endpoint

### POST /api/gacha/draw

Executes a gacha draw.

**Request Body:**
```json
{
  "mst_gacha_id": "gacha_stepup_001",
  "draw_count": 10,
  "guaranteed_choice_id": "unit_ssr_001"  // Required only for choice-type guaranteed
}
```

**Response:**
```json
{
  "prizes": [
    {
      "prize_type": "unit",
      "prize_target_id": "unit_r_001",
      "prize_amount": 1,
      "rarity": "R",
      "is_guaranteed": false
    },
    // ... more prizes
  ],
  "current_step": 2,
  "daily_draw_count": 10,
  "total_draw_count": 20
}
```

**Error Codes:**
- 10800: Gacha not found
- 10801: Not in availability period
- 10802: Daily limit exceeded
- 10803: Insufficient cost
- 10804: Invalid draw count
- 10805: No cost configuration
- 10806: Step not found
- 10807: Invalid guaranteed choice
- 10808: Guaranteed choice required
- 10809: No available prizes

## Gacha Types

### 1. Normal Gacha
- Standard probability-based gacha
- Can have single pull and 10-pull options
- Optional guaranteed rarity on 10-pull

### 2. Step-Up Gacha
- Multi-step progression system
- Each step can have different costs and guarantees
- Steps advance automatically after each pull
- Three guarantee types:
  - **none**: Standard guaranteed rarity (e.g., "at least 1 SSR")
  - **random**: Random selection from candidate list
  - **choice**: Player selects from candidate list

### 3. Pickup Gacha
- Higher rates for featured items/units
- Controlled via higher weights in prize pool
- Can have daily limits

## Services Architecture

### GachaValidationService
Validates all preconditions before executing a draw:
- Master data existence
- Availability period
- Daily limits
- Cost requirements
- Draw count validity

### GachaProgressService
Manages player's gacha progress:
- Daily reset logic (when `daily_reset_at < today 00:00:00`)
- Total reset logic (when configured)
- Step progression

### GachaDrawService
Handles the lottery logic:
- Rarity determination (weighted random by rate/10000)
- Prize selection (weighted random by prize weight)
- Guaranteed prize processing

### GachaPrizeService
Distributes prizes to player:
- Units → trx_unit
- Items → trx_item
- Equipment → trx_equipment

### GachaCostService
Consumes required costs:
- Diamond (free currency)
- Paid diamond (premium currency)
- Items (gacha tickets, etc.)

### DrawUseCase
Orchestrates the complete draw flow:
1. Validate preconditions
2. Process daily/total resets
3. Consume costs
4. Execute lottery
5. Distribute prizes
6. Update progress
7. Record history
8. Return results

## Setup Instructions

### 1. Run Migrations

```bash
cd api
php artisan migrate --path=database/migrations/mst/2026_04_02_000001_create_gacha_tables.php
php artisan migrate --path=database/migrations/trx/2026_04_02_000001_create_gacha_tables.php
```

### 2. Seed Master Data

```bash
php artisan db:seed --class=MstGachaSeeder
```

This creates three example gachas:
- `gacha_normal_001`: Normal gacha with 3% SSR rate
- `gacha_stepup_001`: 3-step gacha with final choice guarantee
- `gacha_pickup_001`: Pickup gacha with 6% SSR rate, daily limit 10

### 3. Test the API

```bash
# Single pull
curl -X POST http://localhost/api/gacha/draw \
  -H "Content-Type: application/json" \
  -d '{
    "mst_gacha_id": "gacha_normal_001",
    "draw_count": 1
  }'

# 10-pull
curl -X POST http://localhost/api/gacha/draw \
  -H "Content-Type: application/json" \
  -d '{
    "mst_gacha_id": "gacha_normal_001",
    "draw_count": 10
  }'

# Step-up with choice (step 3)
curl -X POST http://localhost/api/gacha/draw \
  -H "Content-Type: application/json" \
  -d '{
    "mst_gacha_id": "gacha_stepup_001",
    "draw_count": 10,
    "guaranteed_choice_id": "unit_ssr_001"
  }'
```

## Implementation Files

### Models (9 files)
- `api/app/Models/Mst/MstGacha.php`
- `api/app/Models/Mst/MstGachaCost.php`
- `api/app/Models/Mst/MstGachaRarityRate.php`
- `api/app/Models/Mst/MstGachaPrize.php`
- `api/app/Models/Mst/MstGachaStep.php`
- `api/app/Models/Mst/MstGachaStepGuaranteed.php`
- `api/app/Models/Mst/MstGachaStepGuaranteedCandidate.php`
- `api/app/Models/Trx/TrxGacha.php`
- `api/app/Models/Trx/TrxGachaHistory.php`

### Repositories (9 files)
- `api/app/Repositories/Mst/MstGacha*Repository.php` (7 files)
- `api/app/Repositories/Trx/TrxGacha*Repository.php` (2 files)

### Services (5 files)
- `api/app/Domain/Gacha/Services/GachaValidationService.php`
- `api/app/Domain/Gacha/Services/GachaProgressService.php`
- `api/app/Domain/Gacha/Services/GachaDrawService.php`
- `api/app/Domain/Gacha/Services/GachaPrizeService.php`
- `api/app/Domain/Gacha/Services/GachaCostService.php`

### UseCase & Controller (3 files)
- `api/app/Domain/Gacha/UseCases/DrawUseCase.php`
- `api/app/Http/Controllers/GachaController.php`
- `api/app/Http/Requests/Gacha/DrawRequest.php`
- `api/app/Http/Responses/Gacha/DrawResponse.php`

### Migrations (2 files)
- `api/database/migrations/mst/2026_04_02_000001_create_gacha_tables.php`
- `api/database/migrations/trx/2026_04_02_000001_create_gacha_tables.php`

### Seeders (1 file)
- `api/database/seeders/MstGachaSeeder.php`

## Key Features

✅ Multiple gacha types (normal, step-up, pickup)
✅ Flexible cost system (diamonds, items)
✅ Weighted probability system (10000-base rates)
✅ Guaranteed prize system with 3 types
✅ Step progression for step-up gacha
✅ Daily and total reset mechanics
✅ Complete draw history logging
✅ Automatic prize distribution
✅ Comprehensive validation

## Next Steps

1. **Testing**: Create unit tests for each service
2. **Admin Tools**: Build admin interface for gacha configuration
3. **Analytics**: Add gacha draw statistics and monitoring
4. **Pity System**: Implement SSR pity counter (e.g., guaranteed SSR after N draws)
5. **Localization**: Add mst_gacha_l10n for multi-language support
6. **Rate Display**: Create API endpoint to display gacha rates (legal requirement in some regions)
