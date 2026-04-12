# ADR-003: Four-Layer Player Data Model

**Date:** 2026-04-12  
**Status:** Accepted  
**Deciders:** Bayram Uluad, Claude Code

## Context
Player data changes at wildly different rates: potential never changes, character changes over years, form changes weekly, match state changes every second. Storing all in one table creates update conflicts, stale data, and incorrect caching.

## Decision
Four separate models/tables:
1. `PlayerCeiling` — immutable after creation
2. `PlayerCharacter` — 109 attributes, changes over seasons
3. `PlayerForm` — weekly snapshot
4. `PlayerMatchState` — in-memory only during match (not persisted mid-match)

## Consequences
- **Positive:** Each layer can be cached/updated independently. Ceiling can never be accidentally modified.
- **Negative:** 4 joins to get full player data. Mitigated by Redis caching of full player objects pre-match.
