# Collective Football — The Living Game

> *"Football is a collective game."*

An online, multiplayer football management game where the entire living community around a club — players, staff, fans, city, history — is simulated as a collective organism.

## Core Principles

- **Individual brilliance matters. But collective foundations determine the ceiling.**
- Players are human beings: tired, angry, inspired, homesick, religious, in love, depressed.
- Market value is not a player's worth.
- Football exists in society: politics, economics, culture all affect performance.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3 / Laravel 11 |
| Database | PostgreSQL 16 + PostGIS |
| Cache | Redis 7 |
| Message Queue | RabbitMQ |
| WebSocket | Laravel Reverb |
| Search | Elasticsearch 8 |
| Frontend | Vue 3 (Composition API) + Canvas API |
| Infrastructure | Docker + Hetzner + GitHub Actions |
| Monitoring | Grafana + Prometheus + Zabbix + GlitchTip |

## Quick Start

```bash
docker compose --profile dev up -d
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
# App running at http://localhost
```

## Project Management

- **Redmine**: https://redmine.urubasoftware.com/projects/collective-football
- **GitHub**: https://github.com/Uruba-Software/collective-football (private)

## Documentation

See `CLAUDE.md` for full architecture guide, design patterns, and development principles.
