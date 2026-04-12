# Match Engine Guide
## `app/Engine/` — Collective Football

> **The Strategy Pattern requirement here is non-negotiable.** Every calculation is a Strategy class. Adding a new match factor = adding one new class. Nothing else changes. If you find yourself writing an `if` statement that modifies a match outcome directly inside a Pipeline or Engine class, stop and create a Strategy.

---

## What This Module Does

The Match Engine is the core simulation heart of Collective Football. It has two execution contexts:

1. **Frontend-driven (human matches):** The PHP engine provides a `MatchContext` package. The Vue.js `MatchEngine.js` runs the simulation tick-by-tick in the browser. After the match, the frontend sends results back to the backend.

2. **Headless (background Computer-vs-Computer):** `HeadlessMatchEngine.php` runs the full 90-minute simulation server-side, invoked by `SimulateMatchJob`. No rendering. Must complete in under 8 seconds for 90 minutes. Target: under 5 seconds.

Both contexts share the same Strategy logic. The strategies in `Strategies/` are implemented in both PHP (here) and JavaScript (`frontend/src/engine/strategies/`). They must be kept in sync.

---

## Architecture Overview

```
MatchContext (input data package)
    ↓
EventGenerator (probability-weighted event selection per tick)
    ↓
Pipeline (routes each event type to its pipeline)
    ├── ShotPipeline → [Physics → GK Dimension → Fatigue → Weather →
    │                    Climate → Altitude → Fasting → Pitch →
    │                    ChanceLayer → OutcomeResolver]
    ├── DuelPipeline → [StrengthCalculation → Fatigue → Morale →
    │                    ClimateEffect → OutcomeResolver]
    ├── PassPipeline → [Accuracy → Pressure → ClimateEffect → Outcome]
    └── MovementPipeline → [Fatigue → Positioning → ClimateEffect]
    ↓
Event fired → Listeners handle independently
    ├── MoraleUpdateListener
    ├── StatisticsListener
    ├── CommentaryListener
    ├── CrowdReactionListener
    ├── CharacterImpactListener
    └── UnlockCheckListener (Phase 3+)
    ↓
CharacterFractureEngine (checks fracture conditions after each event)
    ↓
State broadcast (WebSocket for multiplayer / direct return for headless)
```

---

## File Map

```
app/Engine/
├── CLAUDE.md                          ← You are here
├── HeadlessMatchEngine.php            ← Full 90-min headless simulation
├── MatchContext.php                   ← Immutable context package (input)
├── Contracts/
│   ├── ShotStrategyInterface.php
│   ├── DuelStrategyInterface.php
│   ├── PassStrategyInterface.php
│   ├── MovementStrategyInterface.php
│   ├── MoraleStrategyInterface.php
│   ├── WeatherStrategyInterface.php
│   ├── ClimateStrategyInterface.php
│   ├── RefereeStrategyInterface.php
│   └── ChanceLayerInterface.php
├── Strategies/
│   ├── Shot/
│   │   ├── PhysicsCalculationStrategy.php
│   │   ├── GoalkeeperDimensionStrategy.php
│   │   ├── FatigueEffectStrategy.php
│   │   ├── WeatherEffectStrategy.php
│   │   ├── ClimateAdaptationStrategy.php
│   │   ├── AltitudeEffectStrategy.php
│   │   ├── FastingEffectStrategy.php
│   │   ├── PitchQualityStrategy.php
│   │   ├── ChanceLayerStrategy.php
│   │   └── OutcomeResolverStrategy.php
│   ├── Duel/
│   ├── Pass/
│   ├── Movement/
│   ├── Morale/
│   └── Referee/
├── Pipeline/
│   ├── ShotPipeline.php
│   ├── DuelPipeline.php
│   ├── PassPipeline.php
│   └── MovementPipeline.php
├── Events/                            ← Domain events fired during match
└── Listeners/                         ← Event handlers (one concern each)
```

---

## The Strategy Contract

Every Strategy implements one interface from `Contracts/`. The pattern is always:

```php
interface ShotStrategyInterface
{
    /**
     * Apply this strategy's modification to the shot event.
     *
     * Strategies MUST be pure functions with respect to the event:
     * - Read from $ctx freely (it is immutable during a pipeline run)
     * - Modify only $event properties, never $ctx
     * - Always return $event (even if unchanged)
     * - Never throw exceptions for predictable conditions (use modifiers of 1.0 = no effect)
     */
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent;
}
```

**MatchContext is the read-only world state.** It contains everything the strategy needs:
- `$ctx->weather` — temperature, precipitation, wind, humidity, pitch condition
- `$ctx->stadium` — altitude, surface quality, capacity, covered
- `$ctx->getPlayer(string $id)` — returns Player with all four layers loaded
- `$ctx->getTeam(string $id)` — returns Team with identity
- `$ctx->referee` — bias, card tendency, home pressure susceptibility
- `$ctx->minute` — current match minute
- `$ctx->score` — current score state

**Event objects are mutable value objects.** They carry modifiers that accumulate through the pipeline:
```php
class ShotEvent
{
    public float $accuracy_modifier       = 1.0; // Multiplied by each strategy
    public float $physical_output_modifier = 1.0;
    public float $stamina_drain_modifier  = 1.0;
    public float $power_modifier          = 1.0;
    public float $base_accuracy;          // Set by PhysicsCalculationStrategy
    public float $base_power;
    public string $shooter_id;
    public string $goalkeeper_id;
    public ?string $outcome = null;       // Set by OutcomeResolverStrategy (last in chain)
}
```

---

## How to Add a New Match Factor (Step by Step)

Example: adding "boot discomfort" as a factor that reduces shot accuracy when a player's boot fit rating is low.

**Step 1: Write the unit test FIRST** (`tests/Unit/Engine/BootDiscomfortStrategyTest.php`):
```php
it('applies no modifier when boot fit is perfect', function () {
    $event = ShotEvent::make(['shooter_id' => 'player-1', 'accuracy_modifier' => 1.0]);
    $ctx   = MatchContextStub::make(playerBootFit: 100);

    $result = (new BootDiscomfortStrategy($ctx))->apply($event, $ctx);

    expect($result->accuracy_modifier)->toEqual(1.0);
});

it('reduces accuracy by up to 8% with very poor boot fit', function () {
    $event = ShotEvent::make(['shooter_id' => 'player-1', 'accuracy_modifier' => 1.0]);
    $ctx   = MatchContextStub::make(playerBootFit: 10);

    $result = (new BootDiscomfortStrategy($ctx))->apply($event, $ctx);

    expect($result->accuracy_modifier)->toBeLessThan(0.93);
    expect($result->accuracy_modifier)->toBeGreaterThan(0.89);
});
```

**Step 2: Create the Strategy class** (`app/Engine/Strategies/Shot/BootDiscomfortStrategy.php`):
```php
namespace App\Engine\Strategies\Shot;

use App\Engine\Contracts\ShotStrategyInterface;
use App\Engine\Events\ShotEvent;
use App\Engine\MatchContext;

final class BootDiscomfortStrategy implements ShotStrategyInterface
{
    public function __construct(private readonly MatchContext $ctx) {}

    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent
    {
        $player   = $ctx->getPlayer($event->shooter_id);
        $bootFit  = $player->state->boot_fit ?? 100; // default: no effect

        if ($bootFit < 80) {
            $discomfort = (80 - $bootFit) / 80; // 0.0 at 80, 0.875 at fit=10
            $event->accuracy_modifier *= (1 - $discomfort * 0.08);
        }

        return $event;
    }
}
```

**Step 3: Run the unit test** — it should pass now.

**Step 4: Register in the Pipeline** (`app/Engine/Pipeline/ShotPipeline.php`):
```php
private array $strategies = [
    PhysicsCalculationStrategy::class,
    GoalkeeperDimensionStrategy::class,
    FatigueEffectStrategy::class,
    WeatherEffectStrategy::class,
    ClimateAdaptationStrategy::class,
    AltitudeEffectStrategy::class,
    FastingEffectStrategy::class,
    PitchQualityStrategy::class,
    BootDiscomfortStrategy::class,   // ← Add here, before ChanceLayer
    ChanceLayerStrategy::class,
    OutcomeResolverStrategy::class,
];
```

**Step 5: Mirror in JavaScript** (`frontend/src/engine/strategies/shot/BootDiscomfortStrategy.ts`):
```typescript
// See frontend/src/engine/CLAUDE.md for the JS strategy contract.
// The formula MUST be identical to the PHP implementation.
```

**Step 6: Add to `MatchContext` if new data is needed** — if boot fit isn't already in `PlayerMatchState`, add it to the model schema, migration, and factory.

Done. The existing tests still pass. The new factor is isolated, testable, and documented.

---

## Pipeline Architecture

Pipelines are in `app/Engine/Pipeline/`. They are thin orchestrators — they do nothing except define the order of strategies and execute them.

```php
final class ShotPipeline
{
    private array $strategies = [/* ordered list of strategy class names */];

    public function run(ShotEvent $event, MatchContext $ctx): ShotEvent
    {
        return Pipeline::send($event)
            ->through(array_map(
                fn(string $class) => new $class($ctx),
                $this->strategies
            ))
            ->thenReturn();
    }
}
```

**Order matters.** Physics must come first (sets base values). OutcomeResolver must come last (uses final accumulated modifiers to determine outcome). ChanceLayer must come second-to-last.

**Never add conditional logic to a Pipeline class.** If you need to skip a strategy under some condition, handle that condition inside the strategy itself (return $event unchanged).

---

## Event / Listener Architecture

Engine events in `app/Engine/Events/` are standard Laravel events fired during match simulation. They propagate match outcomes to independent listeners, each responsible for exactly one concern.

```php
// In HeadlessMatchEngine or via pipeline OutcomeResolverStrategy:
event(new GoalScored(
    goal: $goal,
    match: $ctx->match,
    scorer: $ctx->getPlayer($event->shooter_id),
    minute: $ctx->minute,
));
```

Listeners in `app/Engine/Listeners/`:
```
MoraleUpdateListener      → adjusts morale for both teams based on goal
StatisticsListener        → increments player and team stats
CommentaryListener        → generates commentary text (Phase 2: AI-generated)
CrowdReactionListener     → updates crowd atmosphere state
CharacterImpactListener   → queues tiny character delta for post-match recording
UnlockCheckListener       → checks if a career milestone was reached (Phase 3)
```

**Each listener handles exactly one concern and is independently testable.**

Register listeners in `app/Providers/EventServiceProvider.php`:
```php
protected $listen = [
    GoalScored::class => [
        MoraleUpdateListener::class,
        StatisticsListener::class,
        CommentaryListener::class,
        CrowdReactionListener::class,
        CharacterImpactListener::class,
    ],
    RedCardIssued::class => [
        MoraleUpdateListener::class,
        CharacterFractureListener::class,
        StatisticsListener::class,
    ],
];
```

---

## Character Fracture System

The Character Fracture Engine runs after every significant event. It checks whether a player's accumulated stress exceeds their self-control threshold.

```php
// app/Engine/CharacterFractureEngine.php
final class CharacterFractureEngine
{
    public function check(Player $player, MatchContext $ctx): ?FractureEvent
    {
        $threshold = $player->character->self_control * 0.95;
        $load      = $player->state->anger_accumulation
                   + ($player->state->stress_level * 0.15);

        if ($load > $threshold) {
            return $this->triggerFracture($player, $ctx);
        }
        return null;
    }

    private function triggerFracture(Player $player, MatchContext $ctx): FractureEvent
    {
        // Momentary out-of-character behavior in-match (handled by pipeline)
        // + tiny permanent character delta (queued for post-match persistence)
        $delta = $ctx->event_intensity / $player->character->resilience;
        return new FractureEvent($player, $delta, $ctx->minute);
    }
}
```

**Key behaviors:**
- Maldini (self_control=97): only fractures under extreme cumulative stress — rare, small delta
- Dirty player (self_control=22, flash_temper=85): fractures frequently, larger delta
- The calm professional (self_control=95, composure=90): effectively never fractures

Character deltas from fractures are **queued** during the match and written to `player_characters` **after** the match ends (via `ProcessMatchResultJob`). They are never written mid-match.

---

## Climate / Geography System

Climate strategies are among the most important in this engine. A mistake here breaks the game's core promise.

The formula for cold effect on a shot (from `ClimateAdaptationStrategy`):

```
cold_factor    = player.climate_adaptation.cold_comfort / 100
temp_penalty   = max(0, (5 - temperature_celsius) / 30)
output_modifier = (0.60 + cold_factor * 0.50 - temp_penalty)

Norwegian (cold=90): 0.60 + 0.45 = 1.05 → slight BOOST
Nigerian  (cold=28): 0.60 + 0.14 = 0.74 → severe PENALTY (26% output reduction)
```

The formula for altitude (above 1500m):

```
excess_km      = (altitude_meters - 1500) / 1000
alt_adapt      = player.climate_adaptation.altitude_comfort / 100
raw_penalty    = excess_km * 0.12
effective      = raw_penalty * (1 - alt_adapt * 0.75)

Bolivian at La Paz (3637m, altitude=95): effective = 0.073 → barely feels it
Englishman at La Paz (altitude=40):      effective = 0.178 → struggles from minute 20
```

**These specific numbers are tested in `tests/Unit/Engine/ClimateAdaptationStrategyTest.php`.** If you change a formula, the test must be updated AND the change must be mirrored in the JavaScript engine AND documented in `docs/climate-system.md`.

---

## Performance Requirements

**Headless match (90 minutes):** must complete in under 8 seconds. Hard limit.
**Per-tick (headless):** must complete in under 50ms. Hard limit.
**Per-tick (frontend):** must complete in under 16ms (60fps target). Enforced by Vitest benchmark.

Performance rules:
1. No database queries inside the engine during simulation. All data is pre-loaded into `MatchContext` before the first tick.
2. No HTTP calls during simulation.
3. No file I/O during simulation.
4. Strategies must be stateless — they receive context, return modified event, done.
5. Floating point math only. Never convert to string inside a strategy.

If the headless engine is approaching the 8-second limit, profile with Blackfire. Do not optimize blindly.

---

## What NOT to Do in the Engine

```php
// 1. Hardcoded calculation (ban #1 in the entire codebase)
$event->accuracy_modifier -= 0.12; // in a Pipeline or Engine method. NEVER.

// 2. Database query inside a tick
$player = Player::find($id); // inside any strategy or pipeline. NEVER.
// All player data is in MatchContext, pre-loaded.

// 3. Mutable MatchContext
$ctx->weather->temperature = 10; // NEVER. MatchContext is read-only.

// 4. Chained if/elseif for different weather types
if ($weather === 'rain') { ... }
elseif ($weather === 'snow') { ... }
elseif ($weather === 'fog') { ... }
// NEVER. Each condition is a separate Strategy or handled by enum dispatch.

// 5. Strategy that does two things
class WeatherAndAltitudeStrategy implements ShotStrategyInterface { ... }
// NEVER. One Strategy = one concern. Split into WeatherEffectStrategy + AltitudeEffectStrategy.

// 6. Logging inside a strategy (performance)
Log::debug('Strategy applied'); // inside a strategy. NEVER.
// Use structured event listeners for post-match logging.

// 7. Modifying $ctx from inside a strategy
$ctx->addEvent(new GoalScored()); // NEVER. Fire events through HeadlessMatchEngine.

// 8. Throwing exceptions for expected game states
throw new Exception('Player not found'); // in a strategy. Return $event unchanged.
// If a player cannot be found, it's a programming error caught by static analysis.
```

---

## Adding a New Event Type (28 Event Types)

The engine supports 28 event types (goal, shot_saved, shot_wide, shot_blocked, duel_won, duel_lost, pass_completed, pass_intercepted, foul, yellow_card, red_card, corner, offside, substitution, injury, penalty_awarded, penalty_scored, penalty_missed, free_kick, throw_in, goalkeeper_save, header_attempt, long_ball, through_ball, tackle_won, tackle_lost, crowd_reaction, character_fracture).

To add a new event type:
1. Create the Event class in `app/Engine/Events/`
2. Create the Pipeline in `app/Engine/Pipeline/`
3. Create the Interface in `app/Engine/Contracts/`
4. Implement at least `OutcomeResolverStrategy` and `PhysicsCalculationStrategy` equivalents for the new type
5. Register the event type in `HeadlessMatchEngine::EVENT_TYPES`
6. Add Listener registrations in `EventServiceProvider`
7. Mirror the event type in `frontend/src/engine/events/`
8. Write unit tests for every strategy
9. Write a feature test in `HeadlessMatchEngineTest` verifying the new event appears in simulation results

---

## MatchContext — The Immutable World Package

`MatchContext` is built once before the match starts and never modified during simulation. It is the single source of truth for all contextual data.

```php
final class MatchContext
{
    public function __construct(
        public readonly string            $matchId,
        public readonly string            $matchType,
        public readonly WeatherCondition  $weather,
        public readonly Stadium           $stadium,
        public readonly Referee           $referee,
        public readonly TeamContext       $homeTeam,
        public readonly TeamContext       $awayTeam,
        public readonly MatchCrowdContext $crowd,
        public readonly int               $minute = 0,
        public readonly Score             $score = new Score(0, 0),
    ) {}

    public function getPlayer(string $playerId): PlayerContext
    {
        return $this->homeTeam->getPlayer($playerId)
            ?? $this->awayTeam->getPlayer($playerId)
            ?? throw new PlayerNotInContextException($playerId);
    }

    public function withMinute(int $minute): self
    {
        // Returns new instance with updated minute (immutable update)
        return new self(..., minute: $minute);
    }
}
```
