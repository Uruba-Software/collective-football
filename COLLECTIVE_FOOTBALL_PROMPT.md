# COLLECTIVE FOOTBALL — THE LIVING GAME
## Claude Code Master Prompt — Complete Project Specification

---

## GAME IDENTITY

**Short name:** Collective
**Full name:** Collective Football — The Living Game
**Tagline:** *"Football is a collective game."*
**GitHub repository:** `collective-football` (private)
**Philosophy codename:** The Collective Principle

### What Collective Football Means
"Collective Football" originates from Italian football culture — it means the entire living community around a club: players, staff, fans, city, history, and everything in between. It captures exactly what this game is about: football as a collective organism, not a collection of individuals.

---

## CORE PHILOSOPHY — READ THIS BEFORE WRITING A SINGLE LINE OF CODE

This is not a game about superstar players. This is not a game about tactics alone. This is a game about **the living, breathing ecosystem of football**.

### The Maradona Principle
Maradona made Argentina world champions. But Argentina was already a team that could reach the quarter-finals without him. He didn't create the team — he elevated what was already there. If Maradona had been born Senegalese in 1960, he might not have made Senegal world champions. The collective substrate matters as much as the individual genius.

### The Bodo/Glimt Principle
A tiny Norwegian town. A team that was near-amateur level a decade ago. A club president who brought in a military-minded mentor. Five years of disciplined, collective work — values, identity, fighting spirit — built into every layer of the club. Then targeted, intelligent interventions on top of that foundation. Today: a European legend.

This is what Collective Football must simulate. **Individual brilliance matters. But collective foundations determine the ceiling.**

### What the Game Must Communicate
- Football players are human beings. They get tired, angry, scared, inspired, homesick, religious, hungry, in love, depressed.
- Football is a team game. No single person — not the best player, not the best manager — can override a broken collective.
- Football exists in a society. Political instability, economic crisis, war, famine — these affect clubs, players, performance. Peace and prosperity allow football to flourish.
- Football has a culture that can be positive (community, identity, joy, Bodo/Glimt) or negative (hooliganism, corruption, match-fixing, financial doping).
- The modern game has become industrialized. Individual players carry enormous societal pressure. Historical football schools (Dutch total football, Czech passing tradition, Cruyff's Barcelona) have been replaced by money and marketing. Collective Football must remember these roots.
- Market value is not a player's worth. A player from a war-torn country playing in an amateur league might have a market value of €0 and be a generational talent. Collective Football must separate market value from actual human worth.

### The Collective Spectrum
```
INDIVIDUAL IMPACT        COLLECTIVE IMPACT
─────────────────────────────────────────────
Immediate, visible       Slow, cumulative
Star player goal         Youth academy culture
Transfer window signing  5-year coaching philosophy
Set piece specialist     Club identity & values
Individual talent        Fan base trust
```

Both sides of this spectrum must be simulated. A club that focuses only on individual talent (buying stars) at the expense of collective culture will eventually fracture. A club that builds collective identity but never recruits quality individuals will stagnate. **The balance is the game.**

---

## DOCUMENTATION PHILOSOPHY

This project will grow for years. Documentation is not optional. Every significant directory must have a `CLAUDE.md` file explaining:
1. What this module does and why it exists
2. The core principles governing decisions in this module
3. What patterns are used and why
4. What NOT to do here (anti-patterns)
5. How to add new features to this module

### CLAUDE.md File Locations
```
/CLAUDE.md                          — Root: game philosophy, architecture overview
/backend/CLAUDE.md                  — Backend principles, Laravel conventions
/backend/app/Engine/CLAUDE.md       — Match engine: strategy pattern, pipeline rules
/backend/app/Engine/Strategies/CLAUDE.md — How to add new strategies
/backend/app/Models/CLAUDE.md       — Data model principles, player data layers
/backend/app/Jobs/CLAUDE.md         — Queue philosophy, async patterns
/frontend/CLAUDE.md                 — Frontend principles, Vue conventions
/frontend/src/engine/CLAUDE.md      — JS engine mirror principles
/frontend/src/components/CLAUDE.md  — Component design system
/infrastructure/CLAUDE.md           — DevOps, deployment, monitoring
/docs/CLAUDE.md                     — Documentation standards
```

Each CLAUDE.md must be written with the assumption that a developer (or Claude instance) reading it has never seen this codebase before.

---

## TECHNICAL STACK — FULL SPECIFICATION

### Backend
```
PHP 8.3+ / Laravel 11
PostgreSQL 16          (primary database, replaces MySQL — better for complex queries)
PostGIS extension      (geographical data: stadium coordinates, altitude, climate zones)
Redis 7                (caching, session, queue driver, pub/sub)
RabbitMQ              (message broker for async match jobs, event streaming)
Laravel Reverb         (WebSocket server, built on ReactPHP)
Elasticsearch 8        (full-text search: player names, clubs, history, statistics)
```

### Frontend
```
Vue 3 (Composition API)
Pinia (state management)
Canvas API (2D match pitch rendering)
Chart.js / D3.js (statistics visualization)
```

### Infrastructure
```
Hetzner Cloud          (production server)
Docker + Docker Compose (containerization)
GitHub (private)       (source control)
GitHub Actions         (CI/CD pipeline)
Grafana + Prometheus   (metrics and monitoring dashboards)
Self-hosted Sentry     (error tracking)
Zabbix                 (infrastructure monitoring)
```

### External APIs
```
football-data.org      (leagues, fixtures, results — free tier)
api-football.com       (detailed player stats, injuries)
OpenMeteo API          (historical and forecast weather by coordinates)
Nominatim/OSM          (geocoding, stadium coordinates)
Transfermarkt scraper  (market values, career history)
FBref scraper          (xG, pressing stats, advanced metrics)
Sofifa scraper         (base attribute ratings)
```

---

## INFRASTRUCTURE ARCHITECTURE

### Docker Compose Services
```yaml
services:
  app:          # Laravel PHP-FPM
  nginx:        # Web server
  postgres:     # PostgreSQL + PostGIS
  redis:        # Redis
  rabbitmq:     # RabbitMQ + management UI
  reverb:       # Laravel Reverb WebSocket
  elasticsearch: # Elasticsearch
  kibana:       # Elasticsearch UI (dev only)
  grafana:      # Metrics dashboard
  prometheus:   # Metrics collector
  sentry:       # Self-hosted Sentry (GlitchTip or official)
  zabbix-server:
  zabbix-web:
  vue-dev:      # Vue dev server (dev only)
```

### Event-Driven Architecture (RabbitMQ)
```
EXCHANGES:
  match.events        → match results, goals, cards (fanout)
  player.events       → transfers, injuries, form changes (topic)
  league.events       → standings updates, season transitions (topic)
  system.events       → errors, health checks (direct)

QUEUES:
  match.simulate      → async headless match jobs
  match.results       → process results after headless match
  player.form.update  → weekly form sync from APIs
  data.scrape         → scheduled scraping jobs
  notifications       → user notifications
  analytics           → non-critical analytics events

DEAD LETTER QUEUES:
  Every queue has a DLQ for failed messages
  Failed jobs → Sentry alert → manual review
```

### Always-On Architecture
```
Process Manager: Supervisor (manages Laravel workers)
  - 4x match simulation workers (RabbitMQ consumers)
  - 2x general queue workers
  - 1x schedule worker (cron replacement)
  - 1x Reverb WebSocket server

Health Checks:
  - /health endpoint: checks DB, Redis, RabbitMQ, Elasticsearch
  - Zabbix monitors all services
  - Grafana dashboard: queue depth, match simulation throughput,
    WebSocket connections, error rates
  - Self-hosted Sentry: all exceptions with full context

Database:
  - PostgreSQL primary + read replica (for reporting queries)
  - Redis: hot player data cache, session, rate limiting
  - Elasticsearch: player search, match history, statistics search

Deployment:
  - Local dev → full Docker Compose stack
  - GitHub Actions → run tests → push to Hetzner on merge to main
  - Zero-downtime deploy (rolling restart)
  - Database migrations run automatically in CI
```

### CI/CD Pipeline (GitHub Actions)
```yaml
# .github/workflows/main.yml
on: [push, pull_request]

jobs:
  backend-tests:
    - PHP 8.3
    - PostgreSQL + PostGIS service
    - Redis service
    - Run PHPUnit (unit + feature)
    - Run static analysis (PHPStan level 8)
    - Code style check (Laravel Pint)

  frontend-tests:
    - Node 20
    - Run Vitest (unit tests)
    - Run Playwright (e2e, match engine smoke tests)
    - Build check

  deploy:
    needs: [backend-tests, frontend-tests]
    if: branch == main
    - SSH to Hetzner
    - Pull latest
    - Run migrations
    - Restart Supervisor workers
    - Clear caches
    - Health check
```

---

## PHASE MAP — KNOW ALL, BUILD PHASE 1 FIRST

### PHASE 1 — WORKING MATCH ENGINE ✅ (Build now)
Fully playable, intervene-able, visualized match with randomly generated players and teams. Multiplayer support. Full infrastructure stack running locally.

### PHASE 2 — DATA ENGINE
Real-world data: APIs, scrapers, player rating engine, live form sync, real match results for unplayed leagues, Elasticsearch indexing.

### PHASE 3 — SEASON & LEAGUE ENGINE
Full league/cup management, season snapshots, transfer market, async simulation, market value dynamics, record tracking.

### PHASE 4 — DEEPENING
Youth academies, second teams, career mode, unlock system, confederations, national teams, football history database, social/political context system.

### PHASE 5 — EDITOR & ADMIN TOOLS
Full in-game editor for all data: players, teams, leagues, countries, climate zones, stadiums, referees. Import/export, bulk operations, test data generators.

### PHASE 6 — COLLECTIVE CONTEXT SYSTEM
Political and economic climate per country (affects club finances, player morale, fan attendance). Hooliganism culture system. Football school/identity system (build a playing philosophy over generations). Social media pressure system. Historical football schools simulation.

---

## PHASE 1 — DETAILED REQUIREMENTS

### 1. PROJECT STRUCTURE
```
collective-football/
├── .github/
│   └── workflows/
│       ├── backend-tests.yml
│       ├── frontend-tests.yml
│       └── deploy.yml
├── backend/                              # Laravel 11
│   ├── CLAUDE.md                         # Backend principles
│   ├── app/
│   │   ├── Engine/
│   │   │   ├── CLAUDE.md                 # Engine principles
│   │   │   ├── Contracts/
│   │   │   │   ├── CLAUDE.md
│   │   │   │   ├── ShotStrategyInterface.php
│   │   │   │   ├── DuelStrategyInterface.php
│   │   │   │   ├── PassStrategyInterface.php
│   │   │   │   ├── MovementStrategyInterface.php
│   │   │   │   ├── MoralStrategyInterface.php
│   │   │   │   ├── WeatherStrategyInterface.php
│   │   │   │   ├── ClimateStrategyInterface.php
│   │   │   │   ├── RefereeStrategyInterface.php
│   │   │   │   └── ChanceLayerInterface.php
│   │   │   ├── Strategies/
│   │   │   │   ├── CLAUDE.md             # How to add new strategies
│   │   │   │   ├── Shot/
│   │   │   │   │   ├── PhysicsCalculationStrategy.php
│   │   │   │   │   ├── GoalkeeperDimensionStrategy.php
│   │   │   │   │   ├── FatigueEffectStrategy.php
│   │   │   │   │   ├── WeatherEffectStrategy.php
│   │   │   │   │   ├── ClimateAdaptationStrategy.php
│   │   │   │   │   ├── AltitudeEffectStrategy.php
│   │   │   │   │   ├── FastingEffectStrategy.php
│   │   │   │   │   ├── ChanceLayerStrategy.php
│   │   │   │   │   └── OutcomeResolverStrategy.php
│   │   │   │   ├── Duel/
│   │   │   │   ├── Pass/
│   │   │   │   ├── Movement/
│   │   │   │   ├── Morale/
│   │   │   │   └── Referee/
│   │   │   ├── Pipeline/
│   │   │   │   ├── EventPipeline.php
│   │   │   │   ├── ShotPipeline.php
│   │   │   │   ├── DuelPipeline.php
│   │   │   │   └── PassPipeline.php
│   │   │   ├── Events/
│   │   │   ├── Listeners/
│   │   │   ├── HeadlessMatchEngine.php
│   │   │   └── MatchContext.php
│   │   ├── Models/
│   │   │   ├── CLAUDE.md
│   │   │   ├── Player.php
│   │   │   ├── PlayerCeiling.php         # Immutable potential ceiling
│   │   │   ├── PlayerCharacter.php       # Slow-changing attributes
│   │   │   ├── PlayerForm.php            # Weekly-changing state
│   │   │   ├── PlayerMatchState.php      # In-match only (not persisted)
│   │   │   ├── PlayerClimateAdaptation.php
│   │   │   ├── PlayerPositionHistory.php
│   │   │   ├── PlayerCareerEvent.php     # Unlock triggers, milestones
│   │   │   ├── Team.php
│   │   │   ├── TeamIdentity.php          # Playing philosophy, club values
│   │   │   ├── Match.php
│   │   │   ├── MatchEvent.php
│   │   │   ├── Referee.php
│   │   │   ├── Stadium.php
│   │   │   ├── Country.php
│   │   │   ├── ClimateZone.php
│   │   │   └── WeatherCondition.php
│   │   ├── Http/Controllers/
│   │   │   ├── MatchController.php
│   │   │   ├── MatchRoomController.php
│   │   │   ├── PlayerController.php
│   │   │   └── TeamController.php
│   │   ├── Jobs/
│   │   │   ├── CLAUDE.md
│   │   │   ├── SimulateMatchJob.php
│   │   │   └── ProcessMatchResultJob.php
│   │   └── Factories/
│   │       ├── RandomPlayerFactory.php
│   │       └── RandomTeamFactory.php
│   ├── tests/
│   │   ├── Unit/Engine/
│   │   │   ├── ShotStrategyTest.php
│   │   │   ├── DuelStrategyTest.php
│   │   │   ├── WeatherEffectTest.php
│   │   │   ├── ClimateAdaptationTest.php
│   │   │   ├── AltitudeEffectTest.php
│   │   │   ├── FastingEffectTest.php
│   │   │   ├── ChanceLayerTest.php
│   │   │   ├── CharacterFractureTest.php
│   │   │   └── FormationCompatibilityTest.php
│   │   └── Feature/
│   │       ├── MatchSimulationTest.php
│   │       ├── HeadlessMatchEngineTest.php
│   │       ├── MultiplayerConnectionTest.php
│   │       └── DisconnectionHandlingTest.php
│   └── infrastructure/
│       ├── CLAUDE.md
│       ├── docker/
│       ├── nginx/
│       └── supervisor/
├── frontend/                             # Vue 3
│   ├── CLAUDE.md
│   ├── src/
│   │   ├── engine/
│   │   │   ├── CLAUDE.md
│   │   │   ├── strategies/
│   │   │   ├── pipeline/
│   │   │   ├── events/
│   │   │   ├── vector/VectorEngine.js
│   │   │   └── MatchEngine.js
│   │   ├── components/
│   │   │   ├── CLAUDE.md
│   │   │   ├── match/
│   │   │   ├── tactics/
│   │   │   ├── player/
│   │   │   └── ui/
│   │   ├── composables/
│   │   └── stores/
│   └── tests/
│       ├── unit/
│       └── e2e/
├── docs/
│   ├── CLAUDE.md
│   ├── philosophy.md                     # The Collective Principle, full essay
│   ├── architecture.md                   # Full system architecture
│   ├── engine.md                         # Match engine deep-dive
│   ├── climate-system.md                 # Climate/geography system
│   ├── player-model.md                   # Four-layer player model
│   └── api.md                            # API documentation
├── CLAUDE.md                             # ROOT: read this first
├── docker-compose.yml                    # Full local stack
├── docker-compose.prod.yml               # Production overrides
└── README.md
```

---

### 2. ROOT CLAUDE.md CONTENT

The root `/CLAUDE.md` must contain:

```markdown
# Collective Football — The Living Game
## Root Architecture Guide for Claude Code

### What is Collective Football?
[The full philosophy: Maradona principle, Bodo/Glimt principle, collective vs individual]

### The Three Laws of This Codebase
1. NEVER hardcode a calculation. Use Strategy Pattern.
   Adding a new match factor = adding one new class.
2. DOCUMENT everything. Every module has a CLAUDE.md.
   Assume the next developer (or Claude) has never seen this code.
3. TEST everything. Every strategy, pipeline, and engine component
   has a unit test before it ships.

### Architecture at a Glance
[Diagram: Backend ↔ RabbitMQ ↔ Workers ↔ WebSocket ↔ Frontend]

### The Player Data Model (Four Layers)
[CEILING → CHARACTER → FORM → STATE — brief explanation]

### The Strategy Pattern Requirement
[How to add a new strategy, step by step]

### Local Development Setup
[Step by step: clone, docker-compose up, seed, run]

### Deployment
[GitHub Actions → Hetzner, what happens automatically]
```

---

### 3. DESIGN PATTERN REQUIREMENTS — NON-NEGOTIABLE

**Every calculation uses Strategy Pattern. No exceptions.**

```php
// THE RULE: If you find yourself writing this...
if ($weather === 'rain') {
    $accuracy -= 12; // WRONG — hardcoded, untestable, unmaintainable
}

// ...write this instead:
class WeatherEffectStrategy implements ShotStrategyInterface {
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent {
        if ($ctx->weather->precipitation === 'heavy_rain') {
            $event->accuracy_modifier *= 0.88;
        }
        return $event;
    }
}
// Then register it in ShotPipeline. Done. Testable. Swappable. Documented.
```

**Pipeline Pattern — every event flows through a pipeline:**
```php
$result = Pipeline::send($shotEvent)
    ->through([
        PhysicsCalculationStrategy::class,
        GoalkeeperDimensionStrategy::class,
        FatigueEffectStrategy::class,
        WeatherEffectStrategy::class,
        ClimateAdaptationStrategy::class,
        AltitudeEffectStrategy::class,
        FastingEffectStrategy::class,        // Salah in Ramadan
        PitchQualityStrategy::class,
        ChanceLayerStrategy::class,
        OutcomeResolverStrategy::class,
    ])
    ->thenReturn();
// Adding shoe discomfort effect? Add ShoeDiscomfortStrategy. Nothing else changes.
```

**Event/Observer Pattern — results propagate automatically:**
```php
event(new GoalScored($goal, $match));
// Each listener handles its own concern independently:
// MoraleUpdateListener, StatisticsListener, CommentaryListener,
// CrowdReactionListener, CharacterImpactListener, UnlockCheckListener
// Adding a new reaction? Add a new listener. Nothing else changes.
```

**RabbitMQ for async work:**
```php
// Match between two computer teams → queue it
RabbitMQ::publish('match.simulate', new SimulateMatchMessage($match));
// Worker picks it up, runs HeadlessMatchEngine, publishes result
RabbitMQ::publish('match.results', new MatchResultMessage($result));
// Result listener updates standings, player form, statistics
```

---

### 4. PLAYER DATA MODEL — FOUR IMMUTABLE LAYERS

#### Layer 1: CEILING (Never Changes)
```php
// PlayerCeiling — separate model, read-only after creation
class PlayerCeiling extends Model {
    protected $fillable = []; // Immutable — no mass assignment
    protected $guarded = ['*'];

    public $overall_ceiling;       // 0-100
    public $position_ceilings;     // JSON: {ST: 85, CAM: 72, GK: 8}
    public $development_speed;     // early|normal|late
    public $development_style;     // training|match_experience|mentorship
}
// Barış Alper ceiling: 72. Mbappé ceiling: 97.
// No training, no coaching, no anything closes that gap.
// Ronaldo reached his ceiling (96) through discipline + ambition.
// Freddy Adu had ceiling ~91. His CHARACTER never let him reach it.
```

#### Layer 2: CHARACTER (Changes Over Years)
```php
// PlayerCharacter — 109 attributes across 6 categories
// Technical (34): BALL_CONTROL, FIRST_TOUCH, DRIBBLING, FINISHING, ...
// Physical (23): ACCELERATION, PACE, STAMINA, COLD_WEATHER_PERFORMANCE, ...
// Mental/Tactical (11): GAME_READING, DECISION_SPEED, POACHING_INSTINCT, ...
// Personality (14): AMBITION, DISCIPLINE, FLASH_TEMPER, SELF_CONTROL, ...
// Social/Human (10): CULTURAL_FIT, LANGUAGE_PROFICIENCY, MANAGER_TRUST, ...
// Moral/Ethics (7): HONESTY, DIRTY_PLAY, FASTING_PERFORMANCE_IMPACT, ...

// Character changes:
// delta = event_intensity / character_resilience
// Maldini's red card → self_control: 97 → 96.8 (tiny mark)
// Dirty player gets punished → self_control: 22 → 24 (slightly improves) OR 20 (gets worse)
```

#### Layer 3: FORM (Changes Weekly)
```php
// PlayerForm — updated after each match, weekly API sync in Phase 2
[
    'current_form'       => 72,   // last 5 match average (0-100)
    'confidence_streak'  => 3,    // consecutive good performances
    'insecurity_load'    => 0,    // recent mistake burden
    'fatigue_cumulative' => 35,   // season fatigue (0-100)
    'injury_status'      => 'fit',
    'psychological_base' => 80,
    'goal_motivation'    => 65,   // top scorer race, national team, etc.
    'media_pressure'     => 20,
]
```

#### Layer 4: STATE (Changes Per Match Minute)
```php
// PlayerMatchState — NOT persisted to DB during match
// Sent in pre-match context, updated in frontend engine,
// final values sent back after match
[
    'current_tempo'       => 88,
    'concentration'       => 85,
    'morale'              => 80,
    'anger_accumulation'  => 5,   // 0 at kickoff, rises with events
    'stress_level'        => 20,
    'excitement'          => 0,
    'insecurity_spike'    => 0,
    'focus_loss'          => 0,   // long time without ball
    'fatigue_current'     => 22,  // rises each tick, climate-affected
]
```

#### Effective Value Formula:
```
effective = character_value × form_coefficient × state_coefficient
effective = min(effective, position_ceiling[current_position])

// Norwegian in freezing rain: physical_output gets climate BONUS
// Nigerian in freezing rain: physical_output gets severe PENALTY
// Same formula, different inputs, realistic different outcomes
```

---

### 5. CHARACTER FRACTURE SYSTEM

```php
class CharacterFractureEngine {
    public function check(Player $player, MatchContext $ctx): ?FractureEvent {
        foreach ($player->character->personality_traits as $trait => $value) {
            $threshold = $player->character->self_control * 0.95;
            $load = $player->state->anger_accumulation
                  + ($player->state->stress_level * 0.15);

            if ($load > $threshold) {
                return $this->triggerFracture($player, $trait, $ctx);
            }
        }
        return null;
    }

    private function triggerFracture(Player $player, string $trait, MatchContext $ctx): FractureEvent {
        // Momentary out-of-character behavior
        // + tiny permanent character delta
        $delta = $ctx->event_intensity / $player->character->resilience;
        return new FractureEvent($player, $trait, $delta);
    }
}
// The Maldini red card is fully reproducible with this system.
// The dirty player who snaps every game is also fully reproducible.
// The calm professional who NEVER snaps is also fully reproducible.
```

---

### 6. CLIMATE, GEOGRAPHY & WEATHER SYSTEM

#### Database Schema (PostGIS)
```sql
CREATE TABLE countries (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    code CHAR(3),
    climate_zone_id INTEGER REFERENCES climate_zones(id),
    -- PostGIS geometry for geographic queries
    geom GEOMETRY(MULTIPOLYGON, 4326)
);

CREATE TABLE stadiums (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200),
    city VARCHAR(100),
    country_id INTEGER REFERENCES countries(id),
    -- PostGIS point geometry
    location GEOMETRY(POINT, 4326),  -- longitude, latitude
    altitude_meters INTEGER,
    surface VARCHAR(20),
    surface_quality INTEGER CHECK (surface_quality BETWEEN 0 AND 100),
    covered BOOLEAN DEFAULT FALSE,
    capacity INTEGER,
    -- Monthly climate data
    avg_temp_jan DECIMAL(4,1), avg_temp_feb DECIMAL(4,1), -- ... all months
    avg_humidity_jan INTEGER, -- ... all months
    avg_precipitation_jan DECIMAL(5,1) -- ... all months
);

CREATE TABLE player_climate_adaptations (
    id SERIAL PRIMARY KEY,
    player_id INTEGER REFERENCES players(id),
    cold_comfort INTEGER DEFAULT 50 CHECK (cold_comfort BETWEEN 0 AND 100),
    heat_comfort INTEGER DEFAULT 50,
    humidity_comfort INTEGER DEFAULT 50,
    altitude_comfort INTEGER DEFAULT 50,
    -- Career adaptation history stored as JSONB
    adaptation_history JSONB DEFAULT '[]'::jsonb
);
```

#### Nationality Climate Profiles
```php
// These are the BASE values — career abroad modifies them over time
const NATIONALITY_CLIMATE_BASE = [
    // Arctic/Nordic — Cold experts, heat sufferers
    'Norwegian'    => ['cold' => 90, 'heat' => 35, 'altitude' => 55],
    'Swedish'      => ['cold' => 88, 'heat' => 38, 'altitude' => 55],
    'Finnish'      => ['cold' => 92, 'heat' => 32, 'altitude' => 50],
    'Icelandic'    => ['cold' => 93, 'heat' => 28, 'altitude' => 48],
    'Danish'       => ['cold' => 82, 'heat' => 45, 'altitude' => 55],
    'Russian'      => ['cold' => 88, 'heat' => 38, 'altitude' => 55],

    // Western European — Balanced temperate
    'English'      => ['cold' => 72, 'heat' => 52, 'altitude' => 58],
    'German'       => ['cold' => 75, 'heat' => 55, 'altitude' => 60],
    'French'       => ['cold' => 68, 'heat' => 65, 'altitude' => 62],
    'Dutch'        => ['cold' => 73, 'heat' => 54, 'altitude' => 57],
    'Belgian'      => ['cold' => 71, 'heat' => 55, 'altitude' => 57],
    'Turkish'      => ['cold' => 62, 'heat' => 72, 'altitude' => 68],

    // Mediterranean — Heat-adapted
    'Spanish'      => ['cold' => 58, 'heat' => 78, 'altitude' => 65],
    'Italian'      => ['cold' => 55, 'heat' => 78, 'altitude' => 64],
    'Portuguese'   => ['cold' => 56, 'heat' => 78, 'altitude' => 63],
    'Greek'        => ['cold' => 48, 'heat' => 82, 'altitude' => 62],
    'Croatian'     => ['cold' => 65, 'heat' => 70, 'altitude' => 65],

    // African — Tropical heat masters
    'Nigerian'     => ['cold' => 28, 'heat' => 92, 'altitude' => 60],
    'Ghanaian'     => ['cold' => 30, 'heat' => 90, 'altitude' => 58],
    'Senegalese'   => ['cold' => 25, 'heat' => 93, 'altitude' => 55],
    'Cameroonian'  => ['cold' => 27, 'heat' => 91, 'altitude' => 65],
    'Ivorian'      => ['cold' => 28, 'heat' => 91, 'altitude' => 58],
    'Moroccan'     => ['cold' => 42, 'heat' => 85, 'altitude' => 68],
    'Kenyan'       => ['cold' => 40, 'heat' => 82, 'altitude' => 88], // Highland
    'Ethiopian'    => ['cold' => 38, 'heat' => 78, 'altitude' => 90], // Highland

    // South American — Mixed
    'Brazilian'    => ['cold' => 32, 'heat' => 89, 'altitude' => 58],
    'Argentine'    => ['cold' => 55, 'heat' => 70, 'altitude' => 62],
    'Colombian'    => ['cold' => 45, 'heat' => 80, 'altitude' => 78],
    'Bolivian'     => ['cold' => 52, 'heat' => 68, 'altitude' => 95], // La Paz native
    'Peruvian'     => ['cold' => 50, 'heat' => 72, 'altitude' => 88],
    'Chilean'      => ['cold' => 60, 'heat' => 65, 'altitude' => 75],
    'Uruguayan'    => ['cold' => 58, 'heat' => 68, 'altitude' => 60],

    // Middle Eastern — Extreme heat adapted
    'Saudi'        => ['cold' => 35, 'heat' => 95, 'altitude' => 58],
    'Qatari'       => ['cold' => 30, 'heat' => 96, 'altitude' => 55],
    'Emirati'      => ['cold' => 28, 'heat' => 97, 'altitude' => 54],
    'Iranian'      => ['cold' => 58, 'heat' => 78, 'altitude' => 70],

    // Asian
    'Japanese'     => ['cold' => 68, 'heat' => 70, 'altitude' => 60],
    'Korean'       => ['cold' => 70, 'heat' => 65, 'altitude' => 62],
    'Chinese'      => ['cold' => 65, 'heat' => 68, 'altitude' => 65],
];

// Career adaptation: each full season abroad in a different climate zone
// modifies the relevant comfort value by ±5 (diminishing returns, capped at 90)
// A Norwegian who spends 8 years in Brazil never becomes fully heat-adapted
// but gets meaningfully better (cold: 90→82, heat: 35→55)
```

#### Climate Effect Strategies
```php
class ClimateAdaptationStrategy implements ShotStrategyInterface {
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent {
        $player = $ctx->getPlayer($event->shooter_id);
        $weather = $ctx->weather;
        $stadium = $ctx->stadium;
        $adapt   = $player->climate_adaptation;

        // COLD: Below 5°C
        if ($weather->temperature_celsius < 5) {
            $cold_factor = $adapt->cold_comfort / 100;
            $temp_penalty = max(0, (5 - $weather->temperature_celsius) / 30);
            $event->physical_output_modifier *= (0.60 + $cold_factor * 0.50 - $temp_penalty);
            // Norwegian (cold=90):  0.60 + 0.45 = 1.05 → slight BOOST in cold
            // Nigerian  (cold=28):  0.60 + 0.14 = 0.74 → severe PENALTY in cold
        }

        // HOT: Above 30°C
        if ($weather->temperature_celsius > 30) {
            $heat_factor  = $adapt->heat_comfort / 100;
            $temp_excess  = ($weather->temperature_celsius - 30) / 20;
            $event->stamina_drain_modifier    *= (2.0 - $heat_factor * 1.2 + $temp_excess);
            $event->physical_output_modifier  *= (0.65 + $heat_factor * 0.45);
            // Nigerian  (heat=92): barely affected
            // Norwegian (heat=35): stamina drains at 1.65x rate, output -30%
        }

        // ALTITUDE: Above 1500m (significant above 2500m)
        if ($stadium->altitude_meters > 1500) {
            $excess_km     = ($stadium->altitude_meters - 1500) / 1000;
            $alt_adapt     = $adapt->altitude_comfort / 100;
            $raw_penalty   = $excess_km * 0.12; // 12% per 1000m above 1500m
            $effective_penalty = $raw_penalty * (1 - $alt_adapt * 0.75);
            $event->stamina_drain_modifier   *= (1 + $effective_penalty);
            $event->physical_output_modifier *= (1 - $effective_penalty * 0.4);
            // Bolivian at La Paz (3637m, altitude=95):
            //   raw_penalty=0.254, effective=0.254*(1-0.7125)=0.073 → barely feels it
            // Englishman at La Paz (altitude=40):
            //   raw_penalty=0.254, effective=0.254*(1-0.30)=0.178 → struggles from min 20
        }

        // HUMIDITY: Above 75%
        if ($weather->humidity_percent > 75) {
            $excess = ($weather->humidity_percent - 75) / 100;
            $humid_adapt = $adapt->humidity_comfort / 100;
            $event->stamina_drain_modifier *= (1 + $excess * (1 - $humid_adapt * 0.6));
        }

        return $event;
    }
}
```

---

### 7. TEAM IDENTITY SYSTEM

A team's collective identity affects match outcomes independently of individual player quality. This is the Bodo/Glimt principle made mechanical.

```php
class TeamIdentity extends Model {
    // Playing philosophy (built over seasons)
    public float $pressing_culture;      // 0-100: how deeply gegenpressing is in the DNA
    public float $possession_culture;    // 0-100: tiki-taka tradition
    public float $counter_culture;       // 0-100: counter-attack as identity
    public float $set_piece_culture;     // 0-100: dead ball expertise
    public float $youth_integration;     // 0-100: how well youth players are absorbed

    // Club values (affect player recruitment fit and morale)
    public float $discipline_culture;    // Bodo/Glimt military discipline
    public float $family_culture;        // Leicester 2016 spirit
    public float $winning_culture;       // Real Madrid expectation pressure
    public float $community_connection;  // Club's bond with its city/fans

    // History effects
    public float $crisis_resilience;     // How well team handles adversity
    public float $big_match_experience;  // Champions League DNA vs newcomers

    // Current collective state
    public float $team_chemistry;        // 0-100, current squad cohesion
    public float $collective_confidence; // Recent results as a unit
    public float $tactical_understanding;// How well players know each other's runs

    // Identity bonus: when a player's character fits club identity,
    // performance is boosted beyond what individual attributes suggest
    // A disciplined player at Bodo/Glimt → gets discipline_culture bonus
    // A maverick individualist at a pressing club → identity mismatch → penalty
}
```

---

### 8. MATCH ARCHITECTURE

#### Pre-Match Context Package (Backend → Frontend, one-time)
```json
{
  "match_id": "uuid",
  "match_type": "league",
  "home_team": {
    "players": [...],
    "formation": "4-3-3",
    "mentality": 7,
    "principles": ["high_press", "short_passing"],
    "identity": {...}
  },
  "away_team": { ... },
  "referee": { "bias_score": 0.2, "card_tendency": 65, ... },
  "stadium": {
    "altitude_meters": 3637,
    "surface_quality": 88,
    "capacity": 41143,
    "covered": false
  },
  "weather": {
    "temperature_celsius": 14,
    "precipitation": "light_rain",
    "wind_speed_kmh": 18,
    "wind_direction": 315,
    "humidity_percent": 72,
    "pitch_condition": "wet"
  },
  "context": {
    "home_crowd_atmosphere": 85,
    "rivalry_context": false,
    "title_implications": false
  }
}
```

#### Tick System
```
Each tick = 1 game minute ≈ 3 real seconds (adjustable: 1x, 2x, 3x, instant)

Each tick sequence:
1. Update all player positions (vector math)
2. Update ball possession
3. Increment match fatigue (climate-modified rate)
4. Run event generator (probability-weighted, formation-aware)
5. If event generated → run through pipeline
6. Fire event listeners (morale, stats, commentary, crowd, character)
7. Check character fracture conditions
8. Render Canvas frame
9. WebSocket broadcast tick state (multiplayer)
10. Check match control events (sub needed? tactic change pending?)
```

#### Async Architecture for Background Matches
```
User plays their match (frontend engine)
     ↓
All other fixtures this matchday → RabbitMQ queue
     ↓
4x PHP workers consume queue
     ↓
HeadlessMatchEngine runs (identical logic, no rendering)
     ↓
Results published to match.results exchange
     ↓
ResultProcessor: updates standings, player form, market values
     ↓
Elasticsearch: indexes new stats for search
     ↓
User sees live standings update when their match finishes
```

---

### 9. MULTIPLAYER ARCHITECTURE

#### Three Modes
```
SINGLE vs COMPUTER:
  Engine:   Vue.js frontend
  AI:       Frontend AIManager class
  Backend:  Context provider + result recorder

COMPUTER vs COMPUTER (Async Background):
  Engine:   PHP HeadlessMatchEngine
  Queue:    RabbitMQ (SimulateMatchJob)
  Output:   Database + Elasticsearch

SINGLE vs SINGLE (Real-time):
  Protocol: WebSocket via Laravel Reverb
  Model:    Host/Guest (lower-ping player hosts)
  Channel:  match.{matchId} presence channel
```

#### WebSocket Tick Broadcast (every tick)
```json
{
  "tick": 47,
  "player_positions": [
    {"id": "uuid", "x": 78.3, "y": 23.1, "direction": 47, "speed": 8.2},
    ...
  ],
  "ball": {"x": 79.1, "y": 22.8, "holder_id": "uuid"},
  "events": [
    {"type": "DRIBBLE_SUCCESS", "player_id": "uuid", "tick": 47}
  ],
  "state": {
    "score": {"home": 1, "away": 0},
    "cards": {"home": {"yellow": 1, "red": 0}, "away": {"yellow": 0, "red": 0}},
    "subs_used": {"home": 1, "away": 0},
    "home_fatigue_avg": 34,
    "away_fatigue_avg": 28
  }
}
```

#### Disconnection Handling (30-Second Rule)
```php
class DisconnectionHandler {
    const GRACE_PERIOD_SECONDS = 30;

    // Triggers: browser close, tab close, network cut, no heartbeat for 5s
    public function onDisconnect(string $playerId, string $matchId): void {
        Cache::put("disconnect:{$matchId}:{$playerId}", true, self::GRACE_PERIOD_SECONDS);
        broadcast(new PlayerDisconnected($playerId, $matchId, self::GRACE_PERIOD_SECONDS));
        // Show countdown to both players
        // Guest has 30s to reconnect
    }

    public function onGracePeriodExpired(string $playerId, string $matchId): void {
        // ABANDON MODE — broadcast severe penalties to frontend engine
        broadcast(new AbandonPenaltyApplied($playerId, $matchId, [
            'form_delta'               => -30,
            'morale'                   => 20,
            'fatigue_spike'            => +40,
            'mentality_forced'         => 2,
            'tactical_coherence'       => 15,
            'chance_window_expansion'  => +0.25,
            'abandonment_recorded'     => true,
        ]));
        // Frontend applies these immediately to the disconnected player's team
        // Post-match: character penalties recorded, abandon noted in history
    }

    public function onReconnect(string $playerId, string $matchId): void {
        if (Cache::has("disconnect:{$matchId}:{$playerId}")) {
            Cache::forget("disconnect:{$matchId}:{$playerId}");
            // Seamless rejoin — send current match state
            $state = MatchStateRepository::get($matchId);
            broadcast(new PlayerReconnected($playerId, $matchId, $state));
        }
        // If grace period already expired: they join as observer of their own disaster
    }
}
```

---

### 10. AI OPPONENT

```javascript
class AIManager {
    constructor(team, difficulty = 'medium') {
        this.team = team;
        this.difficulty = difficulty;
        // difficulty affects: decision frequency, tactical intelligence,
        // substitution timing, formation knowledge
    }

    // Called every 5 ticks
    evaluate(match) {
        const situation = this.analyzeSituation(match);

        if (this.shouldSubstitute(situation)) {
            const sub = this.chooseSub(situation);
            if (sub) match.executeSubstitution(sub);
        }

        if (this.shouldChangeTactic(situation)) {
            const change = this.chooseTacticChange(situation);
            match.executeTacticChange(change);
        }
    }

    analyzeSituation(match) {
        return {
            score_diff: match.homeScore - match.awayScore,
            minute: match.minute,
            my_shots_last_15: match.getShotsInWindow(15, this.team.id),
            opp_shots_last_15: match.getShotsInWindow(15, this.opponentTeamId),
            most_tired_player: this.team.getMostFatiguedOutfieldPlayer(),
            injured_player: this.team.getInjuredPlayer(),
            formation_working: this.evaluateFormationEffectiveness(match),
        };
    }

    chooseTacticChange(situation) {
        // Losing by 1 after minute 70 → push mentality to 8, add striker
        // Winning by 2 after minute 75 → drop to 4, protect
        // 0 shots in 20 minutes → change formation
        // Opponent playing 3-5-2 → switch to wide 4-4-2
        // AI intelligence capped by team's manager_rating attribute
    }
}
```

---

### 11. DATABASE SCHEMA (PostgreSQL + PostGIS)

```sql
-- Core tables (Phase 1)
CREATE TABLE countries (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code CHAR(3) UNIQUE,
    climate_zone VARCHAR(30),
    geom GEOMETRY(MULTIPOLYGON, 4326),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE climate_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- arctic, tropical_wet, desert, highland, etc.
    base_cold_comfort INTEGER,
    base_heat_comfort INTEGER,
    base_humidity_comfort INTEGER
);

CREATE TABLE stadiums (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200),
    city VARCHAR(100),
    country_id INTEGER REFERENCES countries(id),
    location GEOMETRY(POINT, 4326),
    altitude_meters INTEGER DEFAULT 0,
    surface VARCHAR(20) DEFAULT 'grass',
    surface_quality INTEGER DEFAULT 80 CHECK (surface_quality BETWEEN 0 AND 100),
    covered BOOLEAN DEFAULT FALSE,
    capacity INTEGER,
    avg_temp JSONB,      -- {"jan": 14, "feb": 15, ...}
    avg_humidity JSONB,  -- {"jan": 72, ...}
    avg_precip JSONB     -- {"jan": 45.2, ...}
);

CREATE TABLE teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    short_name VARCHAR(10),
    country_id INTEGER REFERENCES countries(id),
    stadium_id INTEGER REFERENCES stadiums(id),
    reputation INTEGER DEFAULT 50 CHECK (reputation BETWEEN 0 AND 100),
    financial_power INTEGER DEFAULT 50,
    -- Team identity (JSON for Phase 1, may normalize later)
    identity JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE players (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(200) NOT NULL,
    date_of_birth DATE,
    nationality VARCHAR(100),
    team_id INTEGER REFERENCES teams(id),
    primary_position VARCHAR(10),
    -- Four-layer model in separate tables
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE player_ceilings (
    id SERIAL PRIMARY KEY,
    player_id UUID UNIQUE REFERENCES players(id) ON DELETE CASCADE,
    overall_ceiling INTEGER CHECK (overall_ceiling BETWEEN 0 AND 100),
    position_ceilings JSONB,    -- {"ST": 85, "CAM": 72}
    development_speed VARCHAR(20) DEFAULT 'normal',
    development_style VARCHAR(30) DEFAULT 'training'
    -- IMMUTABLE after creation
);

CREATE TABLE player_characters (
    id SERIAL PRIMARY KEY,
    player_id UUID UNIQUE REFERENCES players(id) ON DELETE CASCADE,
    -- Technical (34 attributes as integer columns)
    ball_control INTEGER DEFAULT 50,
    first_touch INTEGER DEFAULT 50,
    dribbling INTEGER DEFAULT 50,
    finishing INTEGER DEFAULT 50,
    shot_accuracy INTEGER DEFAULT 50,
    shot_power INTEGER DEFAULT 50,
    long_shots INTEGER DEFAULT 50,
    volleys INTEGER DEFAULT 50,
    heading_shot INTEGER DEFAULT 50,
    composed_finishing INTEGER DEFAULT 50,
    short_passing INTEGER DEFAULT 50,
    medium_passing INTEGER DEFAULT 50,
    long_passing INTEGER DEFAULT 50,
    pass_timing INTEGER DEFAULT 50,
    vision INTEGER DEFAULT 50,
    crossing INTEGER DEFAULT 50,
    set_piece_delivery INTEGER DEFAULT 50,
    penalty_taking INTEGER DEFAULT 50,
    tackling INTEGER DEFAULT 50,
    hard_tackling INTEGER DEFAULT 50,
    aerial_defending INTEGER DEFAULT 50,
    interception INTEGER DEFAULT 50,
    marking INTEGER DEFAULT 50,
    shot_blocking INTEGER DEFAULT 50,
    ball_shielding INTEGER DEFAULT 50,
    close_control_dribbling INTEGER DEFAULT 50,
    pace_dribbling INTEGER DEFAULT 50,
    dribbling_style VARCHAR(20) DEFAULT 'balanced',
    gk_reflexes INTEGER DEFAULT 10,
    gk_positioning INTEGER DEFAULT 10,
    gk_handling INTEGER DEFAULT 10,
    gk_kicking INTEGER DEFAULT 10,
    gk_one_on_one INTEGER DEFAULT 10,
    gk_communication INTEGER DEFAULT 10,
    -- Physical (23 attributes)
    acceleration INTEGER DEFAULT 50,
    pace INTEGER DEFAULT 50,
    stamina INTEGER DEFAULT 50,
    strength INTEGER DEFAULT 50,
    agility INTEGER DEFAULT 50,
    balance INTEGER DEFAULT 50,
    jumping_reach INTEGER DEFAULT 50,
    body_control INTEGER DEFAULT 50,
    flexibility INTEGER DEFAULT 50,
    injury_resistance INTEGER DEFAULT 50,
    injury_proneness INTEGER DEFAULT 50,
    recovery_rate INTEGER DEFAULT 50,
    strong_foot VARCHAR(5) DEFAULT 'right',
    weak_foot INTEGER DEFAULT 2 CHECK (weak_foot BETWEEN 1 AND 5),
    heading_power INTEGER DEFAULT 50,
    heading_accuracy INTEGER DEFAULT 50,
    kick_power INTEGER DEFAULT 50,
    upper_body_strength INTEGER DEFAULT 50,
    knee_strength INTEGER DEFAULT 50,
    running_efficiency INTEGER DEFAULT 50,
    warmup_time INTEGER DEFAULT 50,
    cold_weather_performance INTEGER DEFAULT 50,
    hot_weather_performance INTEGER DEFAULT 50,
    -- Mental/Tactical (11)
    game_reading INTEGER DEFAULT 50,
    spatial_awareness INTEGER DEFAULT 50,
    positioning_instinct INTEGER DEFAULT 50,
    tactical_intelligence INTEGER DEFAULT 50,
    decision_speed INTEGER DEFAULT 50,
    risk_assessment INTEGER DEFAULT 50,
    opportunity_sensing INTEGER DEFAULT 50,
    poaching_instinct INTEGER DEFAULT 50,
    timing_of_runs INTEGER DEFAULT 50,
    pressing_awareness INTEGER DEFAULT 50,
    behind_defense_runs INTEGER DEFAULT 50,
    -- Personality (14)
    competitive_fire INTEGER DEFAULT 50,
    leadership INTEGER DEFAULT 50,
    loyalty INTEGER DEFAULT 50,
    confidence_resilience INTEGER DEFAULT 50,
    flash_temper INTEGER DEFAULT 30,
    self_control INTEGER DEFAULT 70,
    arrogance INTEGER DEFAULT 30,
    provocation_resistance INTEGER DEFAULT 60,
    sacrifice INTEGER DEFAULT 50,
    ambition INTEGER DEFAULT 50,
    discipline INTEGER DEFAULT 50,
    composure INTEGER DEFAULT 50,
    professionalism INTEGER DEFAULT 50,
    adaptability INTEGER DEFAULT 50,
    -- Social/Human (10)
    team_communication INTEGER DEFAULT 50,
    language_proficiency INTEGER DEFAULT 50,
    cultural_fit INTEGER DEFAULT 50,
    new_transfer_weeks INTEGER DEFAULT 0,
    manager_trust INTEGER DEFAULT 50,
    board_trust INTEGER DEFAULT 50,
    fan_connection INTEGER DEFAULT 50,
    peer_respect INTEGER DEFAULT 50,
    media_resilience INTEGER DEFAULT 50,
    religious_belief_strength INTEGER DEFAULT 30,
    -- Moral/Ethics (7)
    honesty INTEGER DEFAULT 70,
    sportsmanship INTEGER DEFAULT 70,
    dirty_play INTEGER DEFAULT 20,
    provocation_tendency INTEGER DEFAULT 20,
    complaint_tendency INTEGER DEFAULT 30,
    religious_motivation_effect INTEGER DEFAULT 20,
    fasting_performance_impact INTEGER DEFAULT 10,

    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE player_forms (
    id SERIAL PRIMARY KEY,
    player_id UUID UNIQUE REFERENCES players(id) ON DELETE CASCADE,
    current_form INTEGER DEFAULT 60,
    confidence_streak INTEGER DEFAULT 0,
    insecurity_load INTEGER DEFAULT 0,
    fatigue_cumulative INTEGER DEFAULT 0,
    injury_status VARCHAR(20) DEFAULT 'fit',
    psychological_base INTEGER DEFAULT 70,
    goal_motivation INTEGER DEFAULT 50,
    media_pressure INTEGER DEFAULT 20,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE player_climate_adaptations (
    id SERIAL PRIMARY KEY,
    player_id UUID UNIQUE REFERENCES players(id) ON DELETE CASCADE,
    cold_comfort INTEGER DEFAULT 50,
    heat_comfort INTEGER DEFAULT 50,
    humidity_comfort INTEGER DEFAULT 50,
    altitude_comfort INTEGER DEFAULT 50,
    adaptation_history JSONB DEFAULT '[]'::jsonb
);

CREATE TABLE player_position_histories (
    id SERIAL PRIMARY KEY,
    player_id UUID REFERENCES players(id) ON DELETE CASCADE,
    position VARCHAR(10),
    proficiency INTEGER DEFAULT 50,
    matches_played INTEGER DEFAULT 0,
    seasons_played DECIMAL(4,1) DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE matches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    home_team_id INTEGER REFERENCES teams(id),
    away_team_id INTEGER REFERENCES teams(id),
    stadium_id INTEGER REFERENCES stadiums(id),
    referee_id INTEGER REFERENCES referees(id),
    match_type VARCHAR(30),
    scheduled_at TIMESTAMP,
    started_at TIMESTAMP,
    finished_at TIMESTAMP,
    home_score INTEGER,
    away_score INTEGER,
    weather_snapshot JSONB,
    status VARCHAR(20) DEFAULT 'scheduled',
    mode VARCHAR(20),     -- single_vs_computer|computer_vs_computer|multiplayer
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE match_events (
    id SERIAL PRIMARY KEY,
    match_id UUID REFERENCES matches(id) ON DELETE CASCADE,
    minute INTEGER,
    event_type VARCHAR(50),
    player_id UUID REFERENCES players(id),
    secondary_player_id UUID REFERENCES players(id),
    team_id INTEGER REFERENCES teams(id),
    details JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE referees (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200),
    nationality VARCHAR(100),
    bias_score DECIMAL(4,3) DEFAULT 0.0 CHECK (bias_score BETWEEN -1 AND 1),
    home_pressure_susceptibility INTEGER DEFAULT 50,
    card_tendency INTEGER DEFAULT 50,
    advantage_tendency INTEGER DEFAULT 50,
    team_affinities JSONB DEFAULT '{}'::jsonb
);

-- Indexes
CREATE INDEX idx_players_team ON players(team_id);
CREATE INDEX idx_match_events_match ON match_events(match_id);
CREATE INDEX idx_match_events_player ON match_events(player_id);
CREATE INDEX idx_stadiums_location ON stadiums USING GIST(location);
CREATE INDEX idx_countries_geom ON countries USING GIST(geom);
CREATE INDEX idx_player_forms_updated ON player_forms(updated_at);
```

---

### 12. MONITORING & OBSERVABILITY

#### Grafana Dashboards (Phase 1)
```
Dashboard 1: Match Engine Health
  - Active matches (Single/Computer/Multiplayer)
  - RabbitMQ queue depth (match.simulate)
  - Headless match completion time (p50, p95, p99)
  - WebSocket connections
  - Match events per second

Dashboard 2: Application Health
  - HTTP response times (p50, p95, p99)
  - Error rate (from Sentry)
  - Database connection pool
  - Redis hit rate
  - Memory and CPU

Dashboard 3: Game Metrics
  - Matches played (total, today)
  - Goals per match average
  - Red cards per match
  - Most active match types
```

#### Self-Hosted Sentry Configuration
```php
// All exceptions auto-captured with match context
Sentry::configureScope(function (Scope $scope) use ($match) {
    $scope->setContext('match', [
        'id'         => $match->id,
        'type'       => $match->match_type,
        'minute'     => $match->current_minute,
        'mode'       => $match->mode,
    ]);
});
```

#### Zabbix Monitoring
```
Monitored:
  - PostgreSQL: connections, query time, replication lag
  - Redis: memory usage, hit rate, connected clients
  - RabbitMQ: queue depth, consumer count, message rate
  - Reverb: WebSocket connection count
  - Elasticsearch: index size, query time
  - Supervisor: worker process health
  - Disk: capacity and I/O
  - Hetzner: CPU, RAM, network
```

---

### 13. CI/CD (GitHub Actions)

```yaml
# .github/workflows/ci.yml
name: Collective Football CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  backend-quality:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgis/postgis:16-3.4
        env:
          POSTGRES_DB: camia_test
          POSTGRES_PASSWORD: testing
      redis:
        image: redis:7-alpine
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: 'pgsql, pdo_pgsql' }
      - run: cd backend && composer install
      - run: cd backend && ./vendor/bin/pint --test         # Code style
      - run: cd backend && ./vendor/bin/phpstan analyse      # Static analysis (level 8)
      - run: cd backend && php artisan test --coverage       # All tests

  frontend-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: cd frontend && npm ci
      - run: cd frontend && npm run type-check
      - run: cd frontend && npm run test:unit
      - run: cd frontend && npm run build

  deploy:
    needs: [backend-quality, frontend-quality]
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Hetzner
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.HETZNER_HOST }}
          username: deploy
          key: ${{ secrets.HETZNER_SSH_KEY }}
          script: |
            cd /var/www/camia
            git pull origin main
            cd backend
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            cd ../frontend
            npm ci && npm run build
            sudo supervisorctl restart all
            curl -f http://localhost/health || exit 1
            echo "Deploy successful"
```

---

### 14. PHASE 1 BUILD ORDER

Complete in this exact order. Do not skip ahead:

```
STEP 1: Infrastructure
  a. Docker Compose: postgres+postgis, redis, rabbitmq, reverb, elasticsearch,
     grafana, prometheus, sentry (glitchtip), zabbix, nginx, php-fpm, vue-dev
  b. All services healthy and communicating
  c. GitHub repository created (private): collective-football
  d. GitHub Actions workflows created (will fail until code exists — that's fine)

STEP 2: Documentation Foundation
  a. Write root CLAUDE.md (game philosophy, architecture, three laws)
  b. Write backend/CLAUDE.md
  c. Write backend/app/Engine/CLAUDE.md
  d. Write frontend/CLAUDE.md
  e. Write docs/philosophy.md (full Collective Principle essay)

STEP 3: Database
  a. Full PostgreSQL schema with PostGIS extensions
  b. All migrations with proper indexes
  c. Seeders: climate zones, nationality profiles, sample countries, stadiums

STEP 4: Backend Engine Skeleton
  a. All Strategy interfaces (Contracts/)
  b. Pipeline classes
  c. Event/Listener skeleton
  d. MatchContext class
  e. WRITE CLAUDE.md FOR EACH MODULE AS YOU GO

STEP 5: Random Factories
  a. RandomPlayerFactory (nationality → climate adaptation, archetype → personality)
  b. RandomTeamFactory (country → stadium → geography → climate)

STEP 6: Core Strategies (with unit tests for each)
  a. PhysicsCalculationStrategy + test
  b. GoalkeeperDimensionStrategy + test
  c. FatigueEffectStrategy + test
  d. WeatherEffectStrategy + test
  e. ClimateAdaptationStrategy + test (Norwegian cold boost, Nigerian cold penalty)
  f. AltitudeEffectStrategy + test (Bolivian vs Englishman at La Paz)
  g. FastingEffectStrategy + test
  h. ChanceLayerStrategy + test
  i. OutcomeResolverStrategy + test

STEP 7: HeadlessMatchEngine
  a. Full 90-minute simulation
  b. All 28 event types
  c. Feature test: 1000 simulated matches, check statistical sanity

STEP 8: RabbitMQ Integration
  a. SimulateMatchJob
  b. ProcessMatchResultJob
  c. Test: queue 10 matches, verify all complete

STEP 9: API Endpoints
  a. POST /api/match/prepare → returns pre-match context
  b. POST /api/match/result  → saves match result + processes impacts
  c. GET  /api/match/{id}    → match details

STEP 10: Laravel Reverb WebSocket
  a. Presence channel: match.{matchId}
  b. Tick broadcast
  c. Intervention events (tactic change, substitution)
  d. Disconnection detection + 30s grace period
  e. Abandon penalty broadcast

STEP 11: Frontend Engine
  a. Vue 3 project setup
  b. Pinia stores
  c. VectorEngine.js
  d. MatchEngine.js (mirrors PHP strategies — JS implementations)
  e. All 28 event types in JS
  f. Pipeline in JS

STEP 12: Canvas Pitch
  a. 2D top-down pitch rendering
  b. Player dots with position labels
  c. Ball with trajectory
  d. Weather overlay (rain visual, snow visual)
  e. Smooth animation between ticks

STEP 13: UI Components
  a. Match screen layout (CM0304 aesthetic)
  b. EventFeed (live event log with icons)
  c. MatchStats panel
  d. Commentary component
  e. WeatherDisplay overlay
  f. InterventionPanel (substitution, tactics, mentality slider)
  g. TacticsBoard (formation selector, player role assignment)
  h. PlayerCard (all four layers, climate adaptation display)

STEP 14: AI Manager
  a. AIManager.js
  b. Situation analysis
  c. Substitution decision logic
  d. Tactic change decision logic

STEP 15: Multiplayer
  a. Match room creation/joining
  b. Host/Guest WebSocket sync
  c. Disconnection handler with 30s rule
  d. Reconnection flow

STEP 16: Monitoring
  a. Grafana dashboards
  b. Sentry error tracking in both backend and frontend
  c. Zabbix host configuration
  d. /health endpoint

STEP 17: CI/CD
  a. All GitHub Actions working
  b. Successful deploy to Hetzner
  c. Health check passes

STEP 18: Final Integration Test
  a. Full match: Single vs Computer → plays, displays, records result
  b. Full match: Computer vs Computer → headless, result in DB
  c. Full match: Single vs Single → two browser tabs, WebSocket sync
  d. Disconnection test: close tab mid-match, verify penalty applied
  e. Climate test: Norwegian player at La Paz in July → verify altitude + cold effects
```

---

## PHASE 5 — EDITOR & ADMIN TOOLS (Know, Don't Build Yet)

```
PLAYER EDITOR:
  Create/edit any player attribute across all four layers
  Ceiling editor (admin only — requires explicit unlock)
  Bulk CSV/JSON import with validation
  Random player generator with custom constraints
  Conflict validator: warns on statistically impossible combinations

TEAM EDITOR:
  Full team creation and configuration
  Squad management: assign players, set formation defaults
  Team identity builder: set collective values, playing philosophy
  Financial configuration: budget, wage structure
  Trophy and history editor

LEAGUE EDITOR:
  Create leagues: country, tier, promotion/relegation rules
  Cup competitions: knockout formats, group stages
  Continental competition tier assignment
  Match calendar generator

COUNTRY EDITOR:
  Add/edit countries with PostGIS geometry
  Climate zone assignment
  Nationality profile editor: base climate adaptation values
  Football federation configuration

CLIMATE & GEOGRAPHY EDITOR:
  Stadium altitude and coordinate editor
  Monthly climate data per stadium
  Custom climate zone creation
  Geographic impact preview: "How would a Nigerian player perform at this stadium in December?"

DATA SYNC CONTROL PANEL:
  Manual API sync trigger
  Scraper run logs and error reporting
  Data diff viewer
  Sync history and rollback

TEST SUITE RUNNER (in-editor):
  Run all engine unit tests from UI
  Scenario simulator: custom player vs custom weather/altitude
  A/B strategy tester: compare two strategy implementations
  Match replay from saved state
```

---

## THE THREE LAWS — ALWAYS

**LAW 1: No hardcoded calculations.**
Every calculation is a Strategy. Adding new behavior = adding one class.

**LAW 2: Every module has a CLAUDE.md.**
The next developer (or Claude instance) must understand the module without asking anyone.

**LAW 3: Every strategy has a test.**
If it doesn't have a test, it doesn't ship.

---

## FINAL NOTE ON THE COLLECTIVE PRINCIPLE

When in doubt about any design decision, ask: *Does this make the game feel more like football as a living, human, collective sport?*

A Norwegian striker who finally adapts to the Andalusian heat after three seasons at Sevilla. An Argentine wonderkid who collapses under World Cup pressure despite being technically superior. A Bodo/Glimt-style club that beats a wealthier opponent through sheer collective discipline. A player who performs brilliantly when his team is winning but invisibly when morale collapses.

**These are the stories Collective Football must be able to tell.**
