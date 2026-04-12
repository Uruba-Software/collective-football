# Documentation Standards
## `docs/` — Collective Football

> Documentation is not optional in this project. LAW 2 (root CLAUDE.md): every module has a CLAUDE.md. This document governs everything beyond CLAUDE.md files — ADRs, the OpenAPI spec, the Postman collection, and the wiki.

---

## What Lives in `docs/`

```
docs/
├── CLAUDE.md                    ← You are here — documentation standards
├── adr/                         ← Architecture Decision Records
│   ├── 0001-strategy-pattern.md
│   ├── 0002-four-layer-player-model.md
│   ├── 0003-rabbitmq-over-redis-queues.md
│   ├── 0004-postgis-over-mysql.md
│   └── template.md              ← Copy this for every new ADR
├── postman/
│   ├── collective-football.postman_collection.json
│   └── environments/
│       ├── local.postman_environment.json
│       └── production.postman_environment.json
├── philosophy.md                ← The Collective Principle (full essay)
├── architecture.md              ← Full system architecture diagram + narrative
├── engine.md                    ← Match engine deep-dive (supplement to Engine/CLAUDE.md)
├── climate-system.md            ← Climate/geography system — formulas, nationality table
├── player-model.md              ← Four-layer player model — detailed rationale
└── api.md                       ← GENERATED — do not edit manually (see OpenAPI section)
```

---

## Architecture Decision Records (ADRs)

**Every architecture decision that is not obvious must have an ADR.** This includes decisions about:
- Why a specific pattern was chosen (Strategy Pattern — ADR-0001)
- Why data is modeled a certain way (four-layer player model — ADR-0002)
- Why a specific technology was chosen (RabbitMQ over Redis queues — ADR-0003)
- When a previous decision was reversed

### When an ADR is Required

Write an ADR when:
- You choose one technology over another (database, queue, library)
- You establish a pattern that will be repeated (Controller→Service→Repository)
- You make a decision whose rationale will not be obvious in 6 months
- You reject an obvious approach in favor of a less obvious one
- A significant refactor changes the architecture

An ADR is NOT required for:
- Implementing a feature within an established pattern (adding a new Strategy class)
- Bug fixes
- Adding a new API endpoint that follows the established pattern

### ADR Process

1. **Copy the template:** `cp docs/adr/template.md docs/adr/NNNN-short-title.md` where NNNN is the next sequential number.
2. **Fill it out.** The template is short — it should take 15-30 minutes, not 3 hours.
3. **Commit with the code change** that implements the decision, not separately.
4. **Never delete an ADR.** If a decision is reversed, write a new ADR that supersedes the old one. Mark the old one with `Status: Superseded by ADR-NNNN`.

### ADR Template (`docs/adr/template.md`)

```markdown
# ADR-NNNN: [Short Title]

**Date:** YYYY-MM-DD
**Status:** Proposed | Accepted | Superseded by ADR-NNNN
**Deciders:** [Names or roles of people who made this decision]

## Context

What is the situation that requires a decision? What forces are at play?
Keep this short — 2-4 sentences is sufficient for most decisions.

## Decision

What was decided? State the decision clearly and concisely.
"We will use X because Y" is the format. Not "we considered X and Y..."

## Rationale

Why was this decision made? What alternatives were considered and rejected?
This is where you explain the reasoning. Future developers need this.

## Consequences

What are the trade-offs? What becomes easier and what becomes harder
as a result of this decision?

## Implementation Notes

(Optional) Any specific implementation guidance that flows directly
from this decision.
```

### Existing ADRs — Required Reading

- **ADR-0001: Strategy Pattern** — Why all calculations are Strategy classes. Read this before touching the engine.
- **ADR-0002: Four-Layer Player Model** — Why Player, PlayerCeiling, PlayerCharacter, and PlayerForm are four separate tables. Read this before touching any player model.
- **ADR-0003: RabbitMQ over Redis Queues** — Why background match simulation uses RabbitMQ instead of Laravel's Redis queue driver.
- **ADR-0004: PostGIS over MySQL** — Why PostgreSQL with PostGIS was chosen as the primary database.

---

## OpenAPI Specification — Do Not Edit Manually

The `docs/api.md` file is **auto-generated** by Dedoc Scramble (`dedoc/scramble` in `composer.json`). It is regenerated on every deploy. Never edit it by hand — your changes will be overwritten.

Scramble reads PHP docblocks, FormRequest rules, and JsonResource `toArray()` methods to generate the spec automatically.

### How to Keep the Spec Accurate

The spec is accurate when:
1. **FormRequests have validation rules** that reflect the actual accepted input.
2. **JsonResources have `toArray()` methods** that return the actual response shape.
3. **Route names are set** (Scramble uses route names for operation IDs).
4. **Controllers have `@return` type hints** or return typed values.

To add documentation to an endpoint beyond what Scramble can infer, use PHPDoc on the controller method:

```php
/**
 * Returns the climate adaptation profile for a player.
 *
 * @operationId getPlayerClimateProfile
 * @response array{data: array{cold_comfort: int, heat_comfort: int, altitude_comfort: int, humidity_comfort: int}}
 */
public function climateProfile(ShowPlayerClimateRequest $request, Player $player): JsonResponse
```

### Viewing the Spec

In local development, Scramble provides a live UI at:
```
http://localhost:8001/docs/api
```

This is the authoritative API reference during development. It is always up to date because it reads the actual code.

To export the OpenAPI JSON/YAML:
```bash
docker compose exec app php artisan scramble:export
# Outputs to: docs/api.json (committed to git for external use)
```

---

## Postman Collection

The Postman collection at `docs/postman/collective-football.postman_collection.json` is the **human-maintained** counterpart to the auto-generated OpenAPI spec. It contains working example requests with real example data, test assertions, and pre-request scripts.

**Rule: Every new endpoint must be added to the Postman collection.** This is enforced in code review. An endpoint with no Postman entry is considered incomplete.

### Collection Structure

```
Collective Football API (v0.1.0)
├── Auth
│   ├── POST CSRF Cookie          ← Must call before login
│   ├── POST Login
│   ├── POST Logout
│   └── GET Current User
├── Matches
│   ├── POST Prepare Match        ← Returns full MatchContext package
│   ├── POST Submit Result        ← After match ends
│   ├── GET Match Details
│   └── GET Match Events
├── Players
│   ├── GET Players (indexed)
│   ├── GET Player Detail
│   └── GET Player Climate Profile
└── Teams
    ├── GET Teams
    └── GET Team Detail
```

### When Adding a New Endpoint to Postman

1. **Open** `docs/postman/collective-football.postman_collection.json` in Postman (File → Import).
2. **Add** the new request to the correct folder.
3. **Set** the URL using environment variables: `{{base_url}}/api/v1/your-endpoint`.
4. **Add** request headers: `Content-Type: application/json`, `Accept: application/json`.
5. **Add** an example response body (copy from a real response).
6. **Add** test assertions in the Tests tab:
   ```javascript
   pm.test("Status is 200", () => pm.response.to.have.status(200))
   pm.test("Has data field", () => pm.expect(pm.response.json()).to.have.property('data'))
   pm.test("cold_comfort is a number", () => {
       pm.expect(pm.response.json().data.cold_comfort).to.be.a('number')
   })
   ```
7. **Export** the updated collection (File → Export → Collection v2.1) and overwrite `docs/postman/collective-football.postman_collection.json`.
8. **Commit** the updated collection file in the same PR as the new endpoint.

### Environments

```
local.postman_environment.json:
  base_url: http://localhost:8001
  auth_token: (set after login)

production.postman_environment.json:
  base_url: https://collective.football (production domain — TBD)
  auth_token: (never commit a real token here)
```

Never commit a real production auth token to the environments file. The `auth_token` variable is populated by the Login request's test script:
```javascript
// In Auth/Login Tests tab:
const token = pm.response.json().token
pm.environment.set('auth_token', token)
```

---

## CLAUDE.md Files — Required for Every Module

This project requires a `CLAUDE.md` in every significant directory. See `CLAUDE.md` at root for the full list of required locations.

**Minimum required content for any CLAUDE.md:**
1. What this module does and why it exists (2-3 sentences)
2. The core principles governing decisions (the non-negotiable rules)
3. What patterns are used here
4. What NOT to do (anti-patterns specific to this module)
5. How to add a new feature to this module (step by step)

**CLAUDE.md files must be:**
- Written assuming the reader has never seen this code before
- Specific to this codebase, not generic best practices
- Updated when the code they describe changes significantly
- Committed in the same PR as the code change they describe

**CLAUDE.md files must not be:**
- Generic summaries of Laravel, Vue, or TypeScript documentation
- Aspirational ("this module will eventually...")
- Wrong (if code changed and CLAUDE.md wasn't updated, update it now)

---

## Core Narrative Documents

These documents in `docs/` explain the game design in depth. They are maintained by hand and represent the authoritative explanation of systems that span many code files.

### `docs/philosophy.md`
The full Collective Principle essay. The Maradona Principle, the Bodo/Glimt Principle, what the game must communicate about football as a human and collective sport. This is not technical documentation — it is the why behind every design decision in this codebase.

**Update trigger:** Only when the fundamental game philosophy changes. This should be rare.

### `docs/architecture.md`
Full system architecture: Docker services, network topology, data flow diagrams, technology choices with rationale. Should include:
- The backend → RabbitMQ → Workers → WebSocket → Frontend data flow diagram
- The three match modes (single vs computer, computer vs computer, multiplayer)
- The 30-second disconnection grace period and abandon penalty system

**Update trigger:** Any time a new service is added, removed, or its role changes.

### `docs/engine.md`
Match engine deep-dive beyond what `backend/app/Engine/CLAUDE.md` covers:
- The complete list of 28 event types with their pipeline composition
- Statistical validation results from the 1000-match smoke test
- Tick timing benchmarks (target vs actual)
- How the frontend engine and backend headless engine are kept in sync

**Update trigger:** New event types, pipeline changes, new strategies.

### `docs/climate-system.md`
The climate/geography system in full detail:
- Complete nationality climate profile table (all 35 nationalities)
- The four formulas: cold effect, heat effect, altitude effect, humidity effect
- Career adaptation rules (how years abroad modify comfort values)
- The PostGIS queries used to determine climate zone from stadium coordinates

**Update trigger:** New nationalities added, formula adjustments, new climate factors.

### `docs/player-model.md`
The four-layer player model in full detail:
- Rationale for each layer (supplement to `app/Models/CLAUDE.md`)
- The effective value formula with worked examples
- The Character Fracture system mechanics
- The TeamIdentity system and identity bonus/penalty calculation

**Update trigger:** New player attributes, model schema changes, formula changes.

---

## Wiki Standards

Project wiki is at: https://redmine.urubasoftware.com/projects/collective-football/wiki

The wiki is for operational knowledge — deployment procedures, incident runbooks, environment setup guides — that does not belong in the code repository.

**In the repo (docs/):** Architecture, design decisions, API spec, game design documentation.
**In the wiki:** How to rotate secrets, how to restore from backup, how to scale workers, how to run a database migration in production, incident post-mortems.

Wiki pages follow the same rule as CLAUDE.md files: assume the reader has never done this operation before. Every runbook must be executable without asking anyone for clarification.
