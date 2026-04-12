# Changelog — Collective Football

All notable changes to this project are documented here.
Format: [Semantic Versioning](https://semver.org/) — MAJOR.MINOR.PATCH

## Versioning Strategy
- **MAJOR** (x.0.0): Game-breaking changes (rare — reserved for fundamental architecture shifts)
- **MINOR** (0.x.0): New phase completion (Phase 0→1 bumps 0.0→0.1, Phase 1→2 bumps 0.1→0.2, etc.)
- **PATCH** (0.0.x): Tasks, bugs, features within a phase
- **1.0.0**: Public launch — all Phase 1 features complete and battle-tested
- **Backward compatibility**: Active game instances are NEVER affected by version upgrades

## [Unreleased]

## [0.0.3] - 2026-04-12
### Added
- Laravel Horizon (queue monitoring UI)
- Laravel Telescope (debug assistant — local/staging only)
- Laravel Pulse (performance monitoring)
- Dedoc Scramble (OpenAPI auto-generation)
- Rector (automated code refactoring + quality)
- Roave Security Advisories (dependency vulnerability scanning)
- Postman collection with test assertions (docs/postman/)
- Laravel API support (Sanctum + routes/api.php)
- PHP 8.5-fpm Docker image with Swoole, AMQP, Redis extensions

### Infrastructure
- Docker stack: PostgreSQL+PostGIS ✓, Redis ✓, RabbitMQ ✓, Nginx ✓, PHP-FPM ✓
- App running at localhost:8001 (PHP/8.5.5 confirmed)
- Redmine tasks: #129-#186 (Phase 0 through Phase 6)

## [0.0.2] - 2026-04-12
### Added
- Full Docker Compose stack definition
- GitHub Actions CI/CD pipeline
- Two development domains: collective.docker (Docker), collective.local (host)
- Supervisor configuration for queue workers and WebSocket server
- Root CLAUDE.md game philosophy documentation

## [0.0.1] - 2026-04-12
### Added
- Initial project scaffold
- Laravel 13 backend with key dependencies
- Vue 3 + TypeScript frontend
- GitHub repository created (Uruba-Software/collective-football, private)
- Redmine project created (collective-football)
- All phase epics and tasks created in Redmine (#129-#186)

## [0.0.0] - 2026-04-12
### Initial
- Project conception
- COLLECTIVE_FOOTBALL_PROMPT.md specification written
