# Backend Engineering Guide
## Collective Football — Laravel 13 / PHP 8.5

> Read `/CLAUDE.md` (root) first. This document assumes you understand the Three Laws and the game philosophy.

---

## What This Layer Does

The backend is the authoritative source of truth for all game state. It:
- Provides pre-match context packages to the frontend (players, weather, stadium, referee)
- Records match results and propagates impacts to all layers (form, character, standings)
- Runs the `HeadlessMatchEngine` for background Computer-vs-Computer matches
- Manages async job dispatch via RabbitMQ
- Broadcasts real-time tick state via Laravel Reverb WebSocket
- Exposes a versioned REST API consumed only by the Vue 3 frontend

The backend does **not** run the match engine for human-controlled matches. The frontend engine does. The backend provides context and records results.

---

## Stack Versions (Pinned)

```
PHP 8.5
Laravel 13
PostgreSQL 16 + PostGIS 3.4
Redis 7
RabbitMQ 3.13
Laravel Reverb 1.x
Laravel Octane (Swoole driver)
Laravel Horizon (queue monitoring)
Laravel Pulse (application monitoring)
Laravel Telescope (dev only)
Dedoc Scramble (OpenAPI auto-generation)
Spatie Laravel Data (DTOs)
Spatie Laravel Query Builder (filterable API queries)
bschmitt/laravel-amqp (RabbitMQ)
Pest 4 (testing framework)
PHPStan / Larastan level 8 (static analysis)
Laravel Pint (code style)
```

---

## Mandatory Request Lifecycle Pattern

Every API endpoint MUST follow this exact pattern. No exceptions.

```
HTTP Request
    → Route (routes/api.php, versioned: /api/v1/*)
    → FormRequest (validation + authorization)
    → Controller (thin: delegates only)
    → Service (business logic)
    → Repository (data access)
    → Model (Eloquent, casts, scopes)
    → Response (JsonResource or Spatie Data DTO)
```

### What Each Layer Is Responsible For

**Controller** (`app/Http/Controllers/Api/V1/`):
- Receives the injected FormRequest (already validated)
- Calls exactly one Service method
- Returns a JsonResource or Spatie Data response
- Contains zero business logic
- Contains zero Eloquent queries

```php
// CORRECT
class MatchController extends Controller
{
    public function prepare(PrepareMatchRequest $request, MatchService $service): JsonResponse
    {
        $context = $service->prepareContext($request->validated());
        return response()->json(MatchContextResource::make($context));
    }
}

// WRONG — business logic in controller
class MatchController extends Controller
{
    public function prepare(Request $request): JsonResponse
    {
        $match = Match::find($request->match_id); // No.
        $weather = $this->fetchWeather($match->stadium); // No.
        return response()->json(['weather' => $weather]);
    }
}
```

**FormRequest** (`app/Http/Requests/Api/V1/`):
- All validation rules live here
- `authorize()` checks Sanctum authentication and ownership
- Never touches the database directly (use exists: or unique: rules only)
- Named as `[Verb][Resource]Request.php` (e.g., `PrepareMatchRequest`, `StorePlayerRequest`)

```php
class PrepareMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play', Match::find($this->match_id));
    }

    public function rules(): array
    {
        return [
            'match_id' => ['required', 'uuid', 'exists:matches,id'],
        ];
    }
}
```

**Service** (`app/Services/`):
- Contains all business logic
- Coordinates between repositories
- Dispatches jobs and fires events
- Is injectable (registered in AppServiceProvider or auto-resolved)
- Never returns Eloquent models directly — returns DTOs (Spatie Data) or arrays

**Repository** (`app/Repositories/`):
- Contains all Eloquent queries
- One repository per Aggregate Root model
- Methods are named for intent, not for SQL: `findWithFullContext()`, not `getWithRelations()`
- Always returns Model instances or Collections — never raw query builders outside the class

**Model** (`app/Models/`):
- Has `casts()`, `$fillable`, relationships
- Has named scopes (`scopeActive`, `scopeForTeam`)
- See `app/Models/CLAUDE.md` for full model conventions

---

## API Versioning

All routes are versioned at `/api/v1/`. When a breaking change is needed, create `/api/v2/` routes alongside v1 (never modify v1 in place).

```php
// routes/api.php
Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('matches', MatchController::class);
        Route::post('matches/{match}/prepare', [MatchController::class, 'prepare'])
             ->name('matches.prepare');
        Route::post('matches/{match}/result', [MatchController::class, 'result'])
             ->name('matches.result');
        Route::apiResource('players', PlayerController::class)->only(['index', 'show']);
        Route::apiResource('teams', TeamController::class)->only(['index', 'show']);
    });

    // Public (no auth)
    Route::get('health', [HealthController::class, 'check'])->name('health');
});
```

**Route naming convention:** `v1.matches.prepare`, `v1.players.index`. Always use `route()` helper, never hardcode strings.

---

## Authentication — Laravel Sanctum

This is a SPA. The frontend is served from the same domain in production. Use cookie-based authentication (stateful), not token-based.

```php
// Middleware for all API routes:
Route::middleware(['web', 'auth:sanctum'])

// Login: POST /api/v1/auth/login → issues session cookie
// Logout: POST /api/v1/auth/logout → invalidates session
// Current user: GET /api/v1/auth/me

// SPA CSRF: Vue frontend must call GET /sanctum/csrf-cookie before login.
// This is handled in the Axios interceptor in frontend/src/utils/http.ts.
```

Token-based auth (Bearer tokens) is reserved for machine-to-machine API access only (e.g., external scrapers in Phase 2). Never use Bearer tokens for the frontend.

**Authorization:** use Laravel Policies, not inline checks.
- `app/Policies/MatchPolicy.php` — who can play, view, abandon a match
- `app/Policies/TeamPolicy.php` — who can manage a team
- Register in `AppServiceProvider::boot()` or use `#[Policy]` attribute (Laravel 13)

---

## Error Handling Conventions

All errors return JSON. The response structure is always:

```json
// Success
{"data": {...}, "meta": {...}}

// Validation error (422)
{"message": "The given data was invalid.", "errors": {"field": ["Error message."]}}

// Application error (400/403/404/409/500)
{"message": "Human-readable message.", "code": "MACHINE_READABLE_CODE"}
```

Configure this in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return match(true) {
                $e instanceof ModelNotFoundException => response()->json(
                    ['message' => 'Resource not found.', 'code' => 'NOT_FOUND'], 404
                ),
                $e instanceof AuthorizationException => response()->json(
                    ['message' => 'Forbidden.', 'code' => 'FORBIDDEN'], 403
                ),
                $e instanceof MatchAlreadyFinishedException => response()->json(
                    ['message' => $e->getMessage(), 'code' => 'MATCH_ALREADY_FINISHED'], 409
                ),
                default => null, // let Laravel handle the rest
            };
        }
    });
})
```

**Domain exceptions** live in `app/Exceptions/`. Name them after the domain concept they represent:
- `MatchAlreadyFinishedException`
- `PlayerNotInTeamException`
- `ClimateDataUnavailableException`

Never throw generic `\Exception`. Always throw a named domain exception.

**Sentry** captures all unhandled exceptions automatically via the Sentry Laravel SDK. Add match context in the Service layer before throwing:

```php
Sentry::configureScope(function (Scope $scope) use ($match): void {
    $scope->setContext('match', [
        'id'     => $match->id,
        'type'   => $match->match_type,
        'minute' => $match->current_minute,
        'mode'   => $match->mode,
    ]);
});
```

---

## Queue / RabbitMQ Patterns

This project uses RabbitMQ via `bschmitt/laravel-amqp` for match simulation. Redis is used for Laravel Horizon's internal queue management (non-match jobs).

### Exchange / Queue Architecture

```
EXCHANGES (fanout/topic):
  match.events     → goals, cards, events (fanout → all subscribers)
  player.events    → transfers, injuries, form changes (topic)
  league.events    → standings updates, season transitions (topic)
  system.events    → errors, health checks (direct)

QUEUES:
  match.simulate   → SimulateMatchJob (headless Computer-vs-Computer)
  match.results    → ProcessMatchResultJob (after any match type)
  player.form      → PlayerFormUpdateJob (weekly batch)
  data.scrape      → ScraperJob subclasses (Phase 2)
  notifications    → UserNotificationJob
  analytics        → AnalyticsEventJob (non-critical, can be lost)

DEAD LETTER QUEUES (auto-configured):
  match.simulate.dlq   → failed simulation → Sentry alert
  match.results.dlq    → failed result processing → Sentry alert
```

### Job Conventions

Jobs live in `app/Jobs/`. See `app/Jobs/CLAUDE.md` for full patterns.

Key rules:
1. Jobs are **idempotent**. Running a job twice must produce the same result.
2. Jobs carry the minimum data needed — pass IDs, not full models.
3. Match simulation jobs must complete in under 10 seconds (headless engine budget: 8s, leaving 2s margin).
4. Always set `$tries` and `$backoff`:

```php
class SimulateMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300]; // seconds

    public function __construct(
        public readonly string $matchId,
    ) {}

    public function handle(HeadlessMatchEngine $engine, MatchRepository $repo): void
    {
        $match = $repo->findWithFullContext($this->matchId);
        $result = $engine->simulate($match);
        // Publish to match.results exchange — do not process result here
        $this->publishResult($result);
    }

    public function failed(Throwable $exception): void
    {
        Sentry::captureException($exception);
        // Mark match as 'simulation_failed' in DB
    }
}
```

5. Never dispatch a job inside a database transaction.
6. Always dispatch after the transaction commits:

```php
// WRONG
DB::transaction(function () use ($match) {
    $match->save();
    SimulateMatchJob::dispatch($match->id); // may run before commit
});

// CORRECT
DB::transaction(function () use ($match) {
    $match->save();
});
SimulateMatchJob::dispatch($match->id)->afterCommit();
```

---

## Strategy Pattern — The Law

See root `CLAUDE.md` for the philosophy. Here is the backend-specific implementation contract.

Every engine calculation implements a Strategy interface from `app/Engine/Contracts/`. The interface contract:

```php
interface ShotStrategyInterface
{
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent;
}
```

Every strategy is registered in its Pipeline class. The Pipeline uses Laravel's `Pipeline` facade:

```php
// app/Engine/Pipeline/ShotPipeline.php
class ShotPipeline
{
    private array $strategies = [
        PhysicsCalculationStrategy::class,
        GoalkeeperDimensionStrategy::class,
        FatigueEffectStrategy::class,
        WeatherEffectStrategy::class,
        ClimateAdaptationStrategy::class,
        AltitudeEffectStrategy::class,
        FastingEffectStrategy::class,
        PitchQualityStrategy::class,
        ChanceLayerStrategy::class,
        OutcomeResolverStrategy::class,
    ];

    public function run(ShotEvent $event, MatchContext $ctx): ShotEvent
    {
        return Pipeline::send($event)
            ->through(array_map(
                fn($strategy) => new $strategy($ctx),
                $this->strategies
            ))
            ->thenReturn();
    }
}
```

See `app/Engine/CLAUDE.md` for the full engine guide.

---

## How to Add a New API Endpoint (Step by Step)

Example: adding `GET /api/v1/players/{player}/climate-profile`.

**Step 1: Write the test first** (`tests/Feature/Api/V1/PlayerClimateProfileTest.php`):
```php
it('returns climate adaptation profile for a player', function () {
    $player = Player::factory()->withClimateAdaptation()->create();

    actingAs($this->user)
        ->getJson(route('v1.players.climate-profile', $player))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['cold_comfort', 'heat_comfort', 'altitude_comfort', 'humidity_comfort']
        ]);
});

it('returns 404 for non-existent player', function () {
    actingAs($this->user)
        ->getJson(route('v1.players.climate-profile', Str::uuid()))
        ->assertNotFound();
});
```

**Step 2: Add the route** (`routes/api.php`):
```php
Route::get('players/{player}/climate-profile', [PlayerController::class, 'climateProfile'])
     ->name('players.climate-profile');
```

**Step 3: Create the FormRequest** (`app/Http/Requests/Api/V1/ShowPlayerClimateRequest.php`):
```php
// If there's no special validation beyond route model binding, this can be minimal.
// Still create it — future authorization rules will go here.
class ShowPlayerClimateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
```

**Step 4: Add the controller method** (`app/Http/Controllers/Api/V1/PlayerController.php`):
```php
public function climateProfile(ShowPlayerClimateRequest $request, Player $player): JsonResponse
{
    $profile = $this->playerService->getClimateProfile($player);
    return response()->json(ClimateProfileResource::make($profile));
}
```

**Step 5: Add the service method** (`app/Services/PlayerService.php`):
```php
public function getClimateProfile(Player $player): PlayerClimateAdaptation
{
    return $this->playerRepository->findClimateAdaptation($player->id);
}
```

**Step 6: Add the repository method** (`app/Repositories/PlayerRepository.php`):
```php
public function findClimateAdaptation(string $playerId): PlayerClimateAdaptation
{
    return PlayerClimateAdaptation::where('player_id', $playerId)
        ->firstOrFail();
}
```

**Step 7: Create the JsonResource** (`app/Http/Resources/Api/V1/ClimateProfileResource.php`):
```php
class ClimateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cold_comfort'     => $this->cold_comfort,
            'heat_comfort'     => $this->heat_comfort,
            'altitude_comfort' => $this->altitude_comfort,
            'humidity_comfort' => $this->humidity_comfort,
            'adaptation_history' => $this->adaptation_history,
        ];
    }
}
```

**Step 8: Run the tests** — they should pass now.

**Step 9: Scramble auto-generates the OpenAPI spec.** Verify it looks correct:
```bash
php artisan scramble:export
```

**Step 10: Update the Postman collection** (`docs/postman/collective-football.postman_collection.json`). Add the new request under the Players folder. See `docs/CLAUDE.md` for Postman conventions.

---

## Forbidden Patterns

These are banned. No exceptions. If you see them, fix them.

```php
// 1. Raw SQL in a controller or service
DB::statement("SELECT * FROM players WHERE ..."); // Use repository

// 2. Business logic in a model
class Player extends Model {
    public function calculateEffectiveFinishing(): float {
        // NO. This belongs in a Service or Strategy.
    }
}

// 3. Hardcoded calculation anywhere
$accuracy -= 12; // NO. Create a Strategy.

// 4. Facade in a constructor (kills testability)
class MatchService {
    public function __construct() {
        $this->repo = new MatchRepository(); // NO. Use DI.
    }
}

// 5. N+1 queries — always eager-load in the repository
Player::all()->each(fn($p) => $p->character->finishing); // NO.
// Correct: Player::with('character', 'form', 'climateAdaptation')->get();

// 6. Storing state in session for game data
session(['current_match' => $match]); // NO. Use the database.

// 7. Dispatching jobs inside a DB transaction (see Queue section above)

// 8. Returning Eloquent models from Services (return DTOs or arrays)

// 9. Accessing request data outside FormRequest/Controller
// Service::method(Request $request) — NO. Pass validated data as array or DTO.

// 10. PHPStan suppressors without explanation
/** @phpstan-ignore-next-line */ // Only allowed with a comment explaining WHY.
```

---

## Testing Conventions

This project uses **Pest 4**. Every test goes in `tests/Unit/` or `tests/Feature/`.

```
tests/
├── Unit/
│   └── Engine/         ← Strategy unit tests (fast, no DB)
│       ├── ShotStrategyTest.php
│       ├── ClimateAdaptationStrategyTest.php
│       └── CharacterFractureTest.php
└── Feature/
    ├── Api/
    │   └── V1/         ← Full HTTP cycle tests (uses DB, RefreshDatabase)
    │       ├── MatchControllerTest.php
    │       └── PlayerControllerTest.php
    └── Engine/
        └── HeadlessMatchEngineTest.php
```

**Unit tests** (Engine strategies): No database, no HTTP. Test the math directly.
```php
it('applies cold penalty to Nigerian player below 5°C', function () {
    $event   = ShotEvent::make(['physical_output_modifier' => 1.0]);
    $ctx     = MatchContext::make(weather: WeatherStub::freezingRain(), player: PlayerStub::nigerian());
    $result  = (new ClimateAdaptationStrategy($ctx))->apply($event, $ctx);
    expect($result->physical_output_modifier)->toBeLessThan(0.80);
});
```

**Feature tests** (API): Use `RefreshDatabase` trait and `actingAs()`.
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});
```

**CI gate:** All tests must pass + PHPStan level 8 + Pint before merge to main.

Run locally:
```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app ./vendor/bin/pint --test
```

---

## Directory Map

```
backend/
├── app/
│   ├── Engine/                  ← Match engine (see Engine/CLAUDE.md)
│   │   ├── Contracts/           ← Strategy interfaces
│   │   ├── Strategies/          ← Implementations (Shot/, Duel/, Pass/, etc.)
│   │   ├── Pipeline/            ← Pipeline orchestrators
│   │   ├── Events/              ← Domain events fired by engine
│   │   ├── Listeners/           ← Event handlers
│   │   ├── HeadlessMatchEngine.php
│   │   └── MatchContext.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/  ← Thin controllers only
│   │   ├── Requests/Api/V1/     ← FormRequest validation + auth
│   │   └── Resources/Api/V1/    ← JsonResource transformers
│   ├── Jobs/                    ← Async jobs (see Jobs/CLAUDE.md)
│   ├── Models/                  ← Eloquent models (see Models/CLAUDE.md)
│   ├── Policies/                ← Laravel authorization policies
│   ├── Repositories/            ← All database queries
│   ├── Services/                ← Business logic coordination
│   ├── Exceptions/              ← Domain exceptions
│   └── Providers/
│       └── AppServiceProvider.php  ← Service binding, policy registration
├── config/
│   ├── queue.php                ← RabbitMQ connections defined here
│   ├── sanctum.php              ← Stateful domains
│   └── horizon.php              ← Queue supervisor configuration
├── routes/
│   ├── api.php                  ← All /api/v1/* routes
│   └── console.php              ← Artisan commands
└── tests/
    ├── Unit/Engine/
    └── Feature/Api/V1/
```
