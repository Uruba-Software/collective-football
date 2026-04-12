# Collective Football — The Living Game
## Root Architecture Guide

> **"Football is a collective game."**
> Read this entire document before writing a single line of code.

---

## What is Collective Football?

This is not a game about superstar players. This is not a game about tactics alone.
This is a game about the **living, breathing ecosystem of football**.

### The Maradona Principle
Maradona made Argentina world champions. But Argentina was already a team that could reach the quarter-finals without him. He didn't create the team — he elevated what was already there. If Maradona had been born Senegalese in 1960, he might not have made Senegal world champions. **The collective substrate matters as much as the individual genius.**

### The Bodo/Glimt Principle
A tiny Norwegian town. A team that was near-amateur level a decade ago. A club president who brought in a military-minded mentor. Five years of disciplined, collective work — values, identity, fighting spirit — built into every layer of the club. Then targeted, intelligent interventions on top of that foundation. Today: a European legend.

**Individual brilliance matters. But collective foundations determine the ceiling.**

---

## The Three Laws of This Codebase

### LAW 1: No hardcoded calculations. Strategy Pattern, always.
Every calculation is a Strategy class. Adding new match behavior = adding one new class.
```php
// WRONG:
if ($weather === 'rain') { $accuracy -= 12; }

// RIGHT:
class WeatherEffectStrategy implements ShotStrategyInterface {
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent { ... }
}
// Register it in ShotPipeline. Done. Testable. Swappable. Documented.
```

### LAW 2: Every module has a CLAUDE.md.
The next developer (or Claude instance) reading this code has never seen it before. Every significant directory must explain: what this does, why it exists, what patterns are used, what NOT to do, how to add features.

### LAW 3: Every strategy has a unit test before it ships.
If it doesn't have a test, it doesn't ship. No exceptions.

---

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND (Vue 3)                        │
│  MatchEngine.js ← Pinia Stores ← Canvas Pitch ← UI Components  │
└────────────────────────────┬────────────────────────────────────┘
                             │ WebSocket (Laravel Reverb)
                             │ REST API (Laravel)
┌────────────────────────────▼────────────────────────────────────┐
│                         BACKEND (Laravel 11)                    │
│                                                                 │
│  ┌──────────────────┐    ┌─────────────────────────────────┐   │
│  │  HeadlessEngine  │    │  Match Engine (Frontend-driven)  │   │
│  │  (PHP, async)    │    │  Context provider + recorder     │   │
│  └────────┬─────────┘    └─────────────────────────────────┘   │
│           │                                                     │
│  ┌────────▼────────────────────────────────────────────────┐   │
│  │  Pipeline: Shot → Duel → Pass → Movement → Morale       │   │
│  │  Strategies: Physics, GK, Fatigue, Weather, Climate,    │   │
│  │              Altitude, Fasting, ChanceLayer, Outcome     │   │
│  └─────────────────────────────────────────────────────────┘   │
└────────────────────┬────────────────────────────────────────────┘
                     │
        ┌────────────▼────────────┐
        │  RabbitMQ               │   Async background match jobs
        │  match.simulate queue   │   4x headless workers
        │  match.results queue    │   Result processor
        └────────────┬────────────┘
                     │
┌────────────────────▼────────────────────────────────────────────┐
│  DATA LAYER                                                     │
│  PostgreSQL+PostGIS  │  Redis  │  Elasticsearch                 │
│  Players, Matches,   │  Cache  │  Player search, stats          │
│  Climate, Geography  │  Queue  │  Match history                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## The Player Data Model (Four Layers)

Every player has four layers of data. Never collapse them into one.

```
LAYER 1: CEILING (Immutable — set once at player creation)
         Overall potential (0-100), position-specific ceilings,
         development speed and style. Barış Alper ceiling: 72.
         Mbappé ceiling: 97. Nothing changes this.

LAYER 2: CHARACTER (Changes over years)
         109 attributes across 6 categories: Technical (34),
         Physical (23), Mental/Tactical (11), Personality (14),
         Social/Human (10), Moral/Ethics (7).
         Changes slowly via events, coaching, life experience.

LAYER 3: FORM (Changes weekly)
         current_form, confidence_streak, fatigue_cumulative,
         injury_status, psychological_base, media_pressure.
         Updated after each match and by weekly API sync (Phase 2).

LAYER 4: STATE (Changes per match minute — NOT persisted to DB)
         current_tempo, concentration, morale, anger_accumulation,
         stress_level, excitement, insecurity_spike, focus_loss,
         fatigue_current. Sent pre-match, tracked by frontend,
         final values returned post-match.

EFFECTIVE VALUE FORMULA:
  effective = character_value × form_coefficient × state_coefficient
  effective = min(effective, position_ceiling[current_position])
```

---

## Key Design Systems

### Character Fracture System
When anger_accumulation + stress exceeds self_control threshold, a player
"fractures" — momentary out-of-character behavior + tiny permanent character delta.
Maldini's red card. The dirty player who snaps every game. The unshakeable professional.
All reproducible with one formula.

### Climate & Geography System
Players have nationality-based climate adaptation profiles (cold/heat/altitude/humidity comfort).
Adapts slowly over career abroad. Norwegian in freezing rain = slight boost.
Nigerian in freezing rain = severe penalty. Same formula, different inputs.

### Team Identity System (Bodo/Glimt Principle)
TeamIdentity tracks: pressing_culture, possession_culture, discipline_culture,
family_culture, crisis_resilience, big_match_experience, team_chemistry.
A player whose character matches the club identity gets an identity bonus.
A maverick at a disciplined club gets a penalty.

### Player Psychology & Human Stories
Players are human beings. They get tired, angry, scared, inspired, homesick,
religious, hungry, in love, depressed. They can:
- Fight with teammates (conflict system)
- Retire, come back, retire again
- Suffer from mental health crises
- Be affected by family events, cultural tensions, media pressure
- Have religious practices that affect performance (e.g. Ramadan fasting)

### Media & Social Media (Phase 6)
Newspaper ecosystem with journalist personalities. Social media with fan influencers.
Both create pressure on players and clubs, affecting morale and form.

### Club Structure & Ownership
Clubs can be: company, foundation, association, or various legal entities.
Ownership: public, corporate, community, private, state-backed.
This affects transfer rules, board decisions, financial behavior.

### Player Onboarding
Two paths to enter the game:
1. **Pay to start a new season**: Fresh SaaS DB instance, all data initialized.
   Expensive because it spawns a complete new game world.
2. **Join an existing game for free**: Take over an available lower-league club.
   Advance by building manager reputation points, or pay to access top clubs.

---

## Strategy Pattern — How to Add New Match Factor

Example: adding "shoe discomfort" as a shot factor.
```
1. Create: backend/app/Engine/Contracts/ShotStrategyInterface.php
   (Already exists — check existing interface)
2. Create: backend/app/Engine/Strategies/Shot/ShoeDiscomfortStrategy.php
   Implement apply(ShotEvent $event, MatchContext $ctx): ShotEvent
3. Write: backend/tests/Unit/Engine/ShoeDiscomfortStrategyTest.php
   Test that a player with comfortable shoes has 0 modifier.
   Test that tight shoes reduce shot accuracy by expected amount.
4. Register: backend/app/Engine/Pipeline/ShotPipeline.php
   Add ShoeDiscomfortStrategy::class to the through() array.
5. Update: backend/app/Engine/Strategies/CLAUDE.md
   Document the new strategy.
Done. Nothing else changes.
```

---

## Local Development Setup

```bash
# 1. Clone the repository
git clone https://github.com/Uruba-Software/collective-football.git
cd collective-football

# 2. Start the full stack
docker compose --profile dev up -d

# 3. Wait for all services to be healthy
docker compose ps

# 4. Install Laravel dependencies
docker compose exec app composer install

# 5. Setup the application
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 6. Verify health
curl http://localhost/health

# 7. Access services
# Application:    http://localhost
# Frontend Dev:   http://localhost:5173
# RabbitMQ UI:    http://localhost:15672  (collective / collective_secret)
# Grafana:        http://localhost:3000   (admin / collective_admin)
# GlitchTip:      http://localhost:8090
# Zabbix:         http://localhost:8888
# Kibana:         http://localhost:5601
# Prometheus:     http://localhost:9090
```

---

## Deployment (GitHub Actions → Hetzner)

On merge to `main`:
1. Backend tests run (PHPUnit, PHPStan level 8, Pint)
2. Frontend tests run (Vitest, Playwright, build check)
3. If all pass → SSH deploy to Hetzner
4. Composer install, migrate, cache config/routes/views
5. Frontend build
6. Supervisor restart (zero-downtime rolling)
7. Health check — if fails, alert sent

---

## Redmine Project

All tasks tracked at: https://redmine.urubasoftware.com/projects/collective-football

Phases:
- Phase 0 (#129): Infrastructure (currently active)
- Phase 1 (#136): Working Match Engine
- Phase 2 (#156): Data Engine
- Phase 3 (#164): Season & League Engine
- Phase 4 (#172): Deepening — Psychology & Human Stories
- Phase 5 (#180): Editor & Admin Tools
- Phase 6 (#181): Media, Social Media & Collective Context

---

## The Collective Principle — Final Rule

When in doubt about any design decision, ask:
**Does this make the game feel more like football as a living, human, collective sport?**

A Norwegian striker who finally adapts to Andalusian heat after three seasons at Sevilla.
An Argentine wonderkid who collapses under World Cup pressure despite technical superiority.
A Bodo/Glimt-style club that beats a wealthier opponent through sheer collective discipline.
A player who performs brilliantly when his team is winning but invisibly when morale collapses.

**These are the stories Collective Football must be able to tell.**
