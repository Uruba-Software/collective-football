# Collective Football — The Living Game

> *"Football is a collective game."*

An online, multiplayer football management game where the entire living community around a club — players, staff, fans, city, history — is simulated as a collective organism. Every match is a living event. Every player is a human being.

## Core Principles

- **The Maradona Principle** — Even a once-in-a-generation talent cannot escape collective context. Maradona played his best football at Napoli, a team and city built around him as a system.
- **The Bodo/Glimt Principle** — Collective identity is a transferable advantage. A well-run collective can consistently outperform individual talent.
- **Individual brilliance matters. But collective foundations determine the ceiling.**
- Players are human beings: tired, angry, inspired, homesick, religious, in love, depressed.
- Market value is not a player's worth.
- Football exists in society: politics, economics, culture all affect performance.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.5 / Laravel 13 |
| Database | PostgreSQL 16 + PostGIS |
| Cache | Redis 7 |
| Message Queue | RabbitMQ 3.13 |
| WebSocket | Laravel Reverb |
| Real-time Processing | Laravel Octane + Swoole |
| AI | Laravel Prism |
| Search | Elasticsearch 8 |
| Frontend | Vue 3 (Composition API + TypeScript) + Canvas API |
| Infrastructure | Docker + Hetzner + GitHub Actions CI/CD |
| Monitoring | Grafana + Prometheus + Zabbix + GlitchTip |
| API Auth | Laravel Sanctum |
| OpenAPI | Dedoc Scramble (auto-generated) |

---

## Four-Layer Player Model

Every player has four distinct data layers:

| Layer | Table | Mutability | Purpose |
|-------|-------|-----------|---------|
| **Ceiling** | `player_ceilings` | Immutable | Genetic upper limit — never changes |
| **Character** | `player_characters` | Yearly | 109 attributes — personality, style, values |
| **Form** | `player_forms` | Weekly | Current physical/mental state |
| **State** | (in-memory only) | Per-minute | Live match state — not persisted |

---

## Architecture

```
Client (Vue 3 Canvas)
    │
    ├── WebSocket (Laravel Reverb) ←── tick broadcasts
    │
    └── REST API (Laravel + Sanctum)
            │
            ├── Match Engine (Strategy Pattern, Pipeline)
            │       └── RabbitMQ → Headless Workers (async simulation)
            │
            ├── PostgreSQL + PostGIS (player data, geography)
            ├── Redis (cache, sessions, queues)
            └── Elasticsearch (search, analytics)
```

---

## Quick Start (Local Development)

**Prerequisites:** Docker + Docker Compose

```bash
# Clone
git clone https://github.com/Uruba-Software/collective-football.git
cd collective-football

# Environment
cp backend/.env.example backend/.env

# Start stack (PostgreSQL, Redis, RabbitMQ, Nginx, PHP 8.5)
docker compose up -d

# Install dependencies
docker compose exec app composer install

# Setup
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# Access
# App:       http://localhost:8001
# API:       http://localhost:8001/api/v1/
# API Docs:  http://localhost:8001/docs/api   (Scramble OpenAPI UI)
# RabbitMQ:  http://localhost:15672           (collective / collective_secret)
# Grafana:   http://localhost:3000            (admin / collective_admin)
```

**Domain aliases** (add to `/etc/hosts`):
```
127.0.0.1 collective.docker
127.0.0.1 collective.local
```

---

## Versioning

```
0.MINOR.PATCH → 1.0.0 at public launch

MINOR = Game phase (0 = Infrastructure, 1 = Match Engine, ...)
PATCH = Task number within the phase
```

Current version: **0.0.3** (Phase 0 — Infrastructure)

---

## Production

| Resource | URL |
|----------|-----|
| App | https://collective-football.urubasoftware.com |
| Redmine | https://redmine.urubasoftware.com/projects/collective-football |
| GitHub | https://github.com/Uruba-Software/collective-football |

**Server:** Hetzner — 89.167.119.59 (Ubuntu 24.04, Docker 29.3.0)

---

## Testing

```bash
# Backend
docker compose exec app php artisan test          # PHPUnit
docker compose exec app php artisan test --pest   # Pest
docker compose exec app ./vendor/bin/phpstan analyse --level=8

# Frontend
cd frontend && npm test          # Vitest unit
cd frontend && npm run playwright # E2E
```

**Test policy:** Unit → Feature → Automation → CI → Auto-approve PR → Prod. Never skip.

---

## Documentation

| Document | Purpose |
|----------|---------|
| `CLAUDE.md` | Architecture guide, design laws, development patterns |
| `docs/adr/` | Architecture Decision Records |
| `docs/postman/` | Postman collection (every endpoint, with test assertions) |
| `docs/philosophy.md` | The Collective Principle — full design philosophy |
| `backend/app/Engine/CLAUDE.md` | Match engine internals |
| `backend/app/Models/CLAUDE.md` | Four-layer player model reference |

---

## Phase Roadmap

| Phase | Focus | Redmine Epic |
|-------|-------|-------------|
| **0** | Infrastructure (Docker, Laravel, CI/CD) | #129 |
| **1** | Match Engine (Strategy Pattern, 90-min simulation) | #136 |
| **2** | Player System (4-layer model, character fracture) | #156 |
| **3** | Season & Competition | #164 |
| **4** | Transfer Market | #172 |
| **5** | Fan & Community Systems | #180 |
| **6** | Mobile & Launch Prep | #181 |

---

## Laws of This Codebase

1. **Every calculation is a Strategy class.** No exceptions.
2. **Every module has a CLAUDE.md.** Every pattern is documented.
3. **Nothing is ever deleted.** Soft deletes everywhere. Archive, never drop.
