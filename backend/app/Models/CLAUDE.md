# Data Models Guide
## `app/Models/` — Collective Football

> The player data model is the most important design decision in this codebase. Read this before touching any player-related model.

---

## The Four-Layer Player Model

A player in Collective Football is represented by **four separate Eloquent models**. They are never collapsed into one. This is not optional.

```
Player              ← Core identity (name, DOB, nationality, team, position)
  │
  ├── PlayerCeiling            ← Immutable potential (set once at creation)
  ├── PlayerCharacter          ← Slow-changing attributes (109 columns)
  ├── PlayerForm               ← Weekly-changing state
  └── PlayerClimateAdaptation  ← Career-wide climate adaptation
```

Plus two additional supporting models:
```
PlayerMatchState        ← In-match only. NEVER persisted to DB during a match.
PlayerCareerEvent       ← Audit log of significant career events (Phase 3+)
```

---

## Why Four Models, Not One

This is the most common question. The answer is architectural necessity, not preference.

**1. Immutability (Ceiling)**
`PlayerCeiling` must never change after creation. Putting it in the same table as form data means any save on the Player model risks accidentally updating ceiling values. Separation makes immutability enforced at the database level (`guarded = ['*']`) and structurally obvious.

**2. Change frequency (performance)**
`PlayerForm` is updated after every match for every participating player (potentially hundreds of players simultaneously after a matchday). `PlayerCharacter` is updated rarely — a few attributes, a few times per season. `PlayerCeiling` is never updated. If they were one table, every form update would lock the row that contains ceiling data, creating unnecessary contention at scale.

**3. Query cost (read path)**
Pre-match context loads all players with `with('ceiling', 'character', 'form', 'climateAdaptation')`. This is 4 JOIN operations for a complete player. If all data were in one table with 120+ columns, every pre-match context query would pull enormous rows even when only form data is needed.

**4. Semantic clarity**
The separation communicates the game's core design philosophy in code: ceiling is destiny (immutable), character is effort + experience (slow change), form is recent performance (fast change). A developer reading the model knows immediately what changes and what doesn't.

**If you are ever tempted to add a column to `Player` instead of to the correct layer model, ask yourself: how often does this data change?**

---

## Model Conventions (All Models)

### Always use `casts()`

Every date, boolean, float, and JSON column must have a cast. Never access raw database values.

```php
// CORRECT
protected function casts(): array
{
    return [
        'date_of_birth'       => 'date',
        'position_ceilings'   => 'array',   // JSONB → PHP array
        'adaptation_history'  => 'array',
        'identity'            => 'array',
        'weather_snapshot'    => 'array',
        'scheduled_at'        => 'datetime',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
        'bias_score'          => 'float',
        'covered'             => 'boolean',
    ];
}

// WRONG — accessing raw JSON string
$player->ceiling->position_ceilings['ST'] // works only by accident if cast is missing
```

### Soft deletes everywhere

Every model uses `SoftDeletes`. Game data is never permanently deleted. A player transferred away is soft-deleted from their old club context, not dropped. A match abandoned is soft-deleted, not erased.

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use SoftDeletes;
    // ...
}
```

The corresponding migration always includes `$table->softDeletes()`.

### Always scope queries

Never write `Player::where('team_id', $teamId)` outside a repository. Define named scopes on the model:

```php
// On the model:
public function scopeForTeam(Builder $query, int $teamId): Builder
{
    return $query->where('team_id', $teamId);
}

public function scopeByPosition(Builder $query, string $position): Builder
{
    return $query->where('primary_position', $position);
}

public function scopeFit(Builder $query): Builder
{
    return $query->whereHas('form', fn($q) => $q->where('injury_status', 'fit'));
}

// Usage in repository (the only place queries happen):
Player::forTeam($teamId)->byPosition('ST')->fit()->with('character', 'form')->get();
```

### Fillable via PHP 8 attributes (Laravel 13 style)

```php
#[Fillable(['name', 'date_of_birth', 'nationality', 'team_id', 'primary_position'])]
class Player extends Model { ... }
```

### No business logic in models

Models contain: casts, fillable/guarded, relationships, scopes.
Models do NOT contain: calculations, strategy calls, HTTP requests, event dispatching, queue dispatching.

```php
// WRONG — business logic in model
class Player extends Model
{
    public function effectiveFinishing(): float
    {
        return $this->character->finishing
            * ($this->form->current_form / 100)
            * ($this->state->concentration / 100);
    }
}

// CORRECT — calculation belongs in EffectiveValueService or a Strategy
class EffectiveValueService
{
    public function finishing(Player $player, PlayerMatchState $state): float { ... }
}
```

---

## Individual Model Reference

### `Player` (Core identity — changes rarely)

```php
// Primary key: UUID (gen_random_uuid())
// Table: players
// Key columns:
//   id UUID, name, date_of_birth, nationality, team_id, primary_position
// Relationships:
//   hasOne: ceiling, character, form, climateAdaptation
//   hasMany: positionHistories, careerEvents
//   belongsTo: team
```

### `PlayerCeiling` (Immutable — set once)

```php
// Table: player_ceilings
// $guarded = ['*']   ← No mass assignment, ever
// No update() ever called on this model after creation
// Key columns:
//   overall_ceiling INTEGER (0-100)
//   position_ceilings JSONB  {"ST": 85, "CAM": 72, "GK": 8}
//   development_speed VARCHAR (early|normal|late)
//   development_style VARCHAR (training|match_experience|mentorship)
//
// RULE: PlayerCeiling::create() is called exactly once, in RandomPlayerFactory.
// There is no updateCeiling() anywhere in the codebase.
// If you find yourself calling $player->ceiling->update(), you are wrong.
```

### `PlayerCharacter` (Changes over years — ~109 attribute columns)

```php
// Table: player_characters
// 109 integer columns across 6 categories:
//   Technical (34): ball_control, first_touch, dribbling, finishing, shot_accuracy,
//     shot_power, long_shots, volleys, heading_shot, composed_finishing,
//     short_passing, medium_passing, long_passing, pass_timing, vision, crossing,
//     set_piece_delivery, penalty_taking, tackling, hard_tackling, aerial_defending,
//     interception, marking, shot_blocking, ball_shielding, close_control_dribbling,
//     pace_dribbling, dribbling_style(*), gk_reflexes, gk_positioning, gk_handling,
//     gk_kicking, gk_one_on_one, gk_communication
//
//   Physical (23): acceleration, pace, stamina, strength, agility, balance,
//     jumping_reach, body_control, flexibility, injury_resistance, injury_proneness,
//     recovery_rate, strong_foot(*), weak_foot, heading_power, heading_accuracy,
//     kick_power, upper_body_strength, knee_strength, running_efficiency,
//     warmup_time, cold_weather_performance, hot_weather_performance
//
//   Mental/Tactical (11): game_reading, spatial_awareness, positioning_instinct,
//     tactical_intelligence, decision_speed, risk_assessment, opportunity_sensing,
//     poaching_instinct, timing_of_runs, pressing_awareness, behind_defense_runs
//
//   Personality (14): competitive_fire, leadership, loyalty, confidence_resilience,
//     flash_temper, self_control, arrogance, provocation_resistance, sacrifice,
//     ambition, discipline, composure, professionalism, adaptability
//
//   Social/Human (10): team_communication, language_proficiency, cultural_fit,
//     new_transfer_weeks, manager_trust, board_trust, fan_connection, peer_respect,
//     media_resilience, religious_belief_strength
//
//   Moral/Ethics (7): honesty, sportsmanship, dirty_play, provocation_tendency,
//     complaint_tendency, religious_motivation_effect, fasting_performance_impact
//
// (*) dribbling_style and strong_foot are VARCHAR, all others INTEGER 0-100.
//
// All columns default to 50 (average), except personality extremes:
//   flash_temper DEFAULT 30, self_control DEFAULT 70, arrogance DEFAULT 30,
//   provocation_resistance DEFAULT 60, dirty_play DEFAULT 20, honesty DEFAULT 70,
//   sportsmanship DEFAULT 70, religious_belief_strength DEFAULT 30
//
// updated_at is written on every character change. This is intentional —
// it allows querying "which players had character changes this season".
```

### `PlayerForm` (Changes weekly)

```php
// Table: player_forms
// Key columns:
//   current_form INTEGER (0-100) — last 5 match average
//   confidence_streak INTEGER — consecutive good performances
//   insecurity_load INTEGER — recent mistake burden
//   fatigue_cumulative INTEGER (0-100) — season fatigue
//   injury_status VARCHAR (fit|minor|moderate|severe|out)
//   psychological_base INTEGER (0-100)
//   goal_motivation INTEGER (0-100) — top scorer race, national team pressure
//   media_pressure INTEGER (0-100)
//   updated_at — always updated; use this to detect stale form data
```

### `PlayerMatchState` (In-match only — NOT persisted during match)

```php
// This is NOT an Eloquent model in the traditional sense.
// During a match:
//   Backend sends initial state values in the MatchContext JSON package.
//   Frontend engine updates state each tick.
//   After the match: frontend sends final state values back via POST /api/v1/matches/{id}/result.
//   Backend then writes character deltas from fracture events and updates PlayerForm.
//
// Key fields (sent in JSON, NOT a database table during match):
//   current_tempo, concentration, morale, anger_accumulation, stress_level,
//   excitement, insecurity_spike, focus_loss, fatigue_current
//
// There IS a PlayerMatchState table for historical snapshots (post-match).
// Rows are inserted AFTER the match for analytics. Never during.
```

### `PlayerClimateAdaptation` (Changes by career seasons abroad)

```php
// Table: player_climate_adaptations
// Key columns:
//   cold_comfort INTEGER DEFAULT 50 (CHECK 0-100)
//   heat_comfort INTEGER DEFAULT 50
//   humidity_comfort INTEGER DEFAULT 50
//   altitude_comfort INTEGER DEFAULT 50
//   adaptation_history JSONB — array of {season, league, climate_zone, delta}
//
// Updated once per season in Phase 2 (PlayerClimateAdaptationJob).
// Starting values come from nationality profiles (see docs/climate-system.md).
```

---

## How to Add a New Attribute to `PlayerCharacter`

Example: adding `set_piece_awareness` (mental attribute, how well a player reads set piece situations defensively).

**Step 1: Write the migration:**
```bash
php artisan make:migration add_set_piece_awareness_to_player_characters
```

```php
public function up(): void
{
    Schema::table('player_characters', function (Blueprint $table): void {
        $table->integer('set_piece_awareness')->default(50)->after('pressing_awareness');
        // Always: integer, CHECK 0-100 (add this via raw SQL in the migration), default 50
    });

    // Add PostgreSQL CHECK constraint
    DB::statement('ALTER TABLE player_characters ADD CONSTRAINT chk_set_piece_awareness
        CHECK (set_piece_awareness BETWEEN 0 AND 100)');
}

public function down(): void
{
    Schema::table('player_characters', function (Blueprint $table): void {
        $table->dropColumn('set_piece_awareness');
    });
}
```

**Step 2: Add to `PlayerCharacter` model** (`app/Models/PlayerCharacter.php`):
```php
// Add to #[Fillable([...])] attribute:
'set_piece_awareness',

// No cast needed (it's already INTEGER — cast is implicit for integers in Eloquent).
// If this were JSONB, add: 'set_piece_awareness' => 'array' to casts().
```

**Step 3: Update the RandomPlayerFactory** (`app/Factories/RandomPlayerFactory.php`):
```php
// Add to the Mental/Tactical section of generateCharacterAttributes():
'set_piece_awareness' => $this->generateMentalAttribute($nationality, 'set_piece_awareness'),
```

**Step 4: Update Sofifa/FBref scraper mappings (Phase 2)** in `app/Services/PlayerAttributeMapper.php` — map the external data field to `set_piece_awareness`.

**Step 5: Update the strategy that reads this attribute** (if any). In this case, add it to the relevant DuelPipeline or MovementPipeline strategy.

**Step 6: Update the PlayerCharacter model docs** — add it to the comment block above in this file, under Mental/Tactical.

**Step 7: Run tests:**
```bash
php artisan test --filter PlayerCharacter
php artisan test --filter RandomPlayerFactory
```

---

## Other Models

### `Team`

```php
// Table: teams
// Key columns: name, short_name, country_id, stadium_id, reputation, financial_power, identity (JSONB)
// identity JSONB is the TeamIdentity data (Phase 1: stored as JSON, Phase 3+: normalized)
// TeamIdentity fields: pressing_culture, possession_culture, counter_culture,
//   discipline_culture, family_culture, winning_culture, community_connection,
//   crisis_resilience, big_match_experience, team_chemistry,
//   collective_confidence, tactical_understanding
```

### `Match` (use App\Models\FootballMatch to avoid PHP reserved word conflict)

```php
// Table: matches
// Primary key: UUID
// Key columns: home_team_id, away_team_id, stadium_id, referee_id, match_type,
//   scheduled_at, started_at, finished_at, home_score, away_score,
//   weather_snapshot (JSONB), status, mode
// status: scheduled|in_progress|finished|abandoned|simulation_failed
// mode: single_vs_computer|computer_vs_computer|multiplayer
```

### `Stadium`

```php
// Table: stadiums
// PostGIS column: location GEOMETRY(POINT, 4326) — use ->spatialIndex()
// Key columns: altitude_meters, surface, surface_quality, covered, capacity,
//   avg_temp (JSONB), avg_humidity (JSONB), avg_precip (JSONB)
// The avg_temp/humidity/precip JSONBs are {"jan": 14, "feb": 16, ...} — 12 months
```

### `Country`

```php
// Table: countries
// PostGIS column: geom GEOMETRY(MULTIPOLYGON, 4326)
// Use the GiST index for geographic queries:
//   Country::whereRaw('ST_Contains(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lon, $lat])
```

### `Referee`

```php
// Table: referees
// Key columns: bias_score DECIMAL(-1.0 to 1.0, positive = home bias),
//   home_pressure_susceptibility, card_tendency, advantage_tendency,
//   team_affinities JSONB — {team_id: affinity_score} (Phase 3+)
```

---

## Forbidden Patterns

```php
// 1. Collapsing layers — never put ceiling, character, form in one model
class Player extends Model {
    protected $fillable = ['finishing', 'overall_ceiling', 'current_form']; // NEVER
}

// 2. Updating PlayerCeiling after creation
$player->ceiling->update(['overall_ceiling' => 99]); // NEVER. Ceiling is immutable.

// 3. Writing PlayerMatchState to DB during a match
PlayerMatchState::updateOrCreate(['player_id' => $id], $state); // mid-match. NEVER.

// 4. Accessing a relationship without eager loading in a loop (N+1)
foreach ($team->players as $player) {
    echo $player->character->finishing; // NEVER outside a repository with with()
}

// 5. Model without SoftDeletes
class MatchEvent extends Model { // no SoftDeletes. NEVER.

// 6. JSON column without a cast
class Stadium extends Model {
    // avg_temp is JSONB but no cast defined — returns string. NEVER.
}

// 7. Business logic in a model method
class PlayerForm extends Model {
    public function isInGoodForm(): bool {
        return $this->current_form > 70 && $this->confidence_streak > 2; // NEVER. Use FormService.
    }
}
```
