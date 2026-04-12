# ADR-001: Technology Stack Selection

**Date:** 2026-04-12  
**Status:** Accepted  
**Deciders:** Bayram Uluad (Product), Claude Code (Technical)

## Context
Collective Football needs to support thousands of concurrent players, real-time match simulation (WebSocket), async background processing, and geographic data. The stack must scale horizontally on Hetzner.

## Decision
- **PHP 8.5 / Laravel 13** — mature ecosystem, team familiarity, excellent queue/WebSocket support
- **PostgreSQL 16 + PostGIS** — geographic queries (stadium coordinates, altitude), complex analytics
- **Redis** — sessions, caching, Horizon queue, Pulse metrics
- **RabbitMQ** — async match simulation (guaranteed delivery, dead letter queues, fan-out)
- **Laravel Reverb** — first-party WebSocket, no external service dependency
- **Laravel Octane + Swoole** — persistent process, 10x throughput vs PHP-FPM for match ticks
- **Elasticsearch** — player/club search, match history indexing

## Consequences
- **Positive:** All chosen tools are production-proven at scale, Docker-native, self-hostable
- **Negative:** Complex local setup (mitigated by Docker Compose)
- **Risk:** PHP 8.5 is new — monitor for extension compatibility issues
