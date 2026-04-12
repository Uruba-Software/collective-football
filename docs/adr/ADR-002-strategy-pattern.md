# ADR-002: Strategy Pattern for All Match Calculations

**Date:** 2026-04-12  
**Status:** Accepted (Non-negotiable)  
**Deciders:** Bayram Uluad, Claude Code

## Context
The match engine has 20+ factors affecting outcomes (weather, altitude, fatigue, fasting, climate, referee bias, etc.). More will be added over time. Hardcoding these creates untestable, unmaintainable code.

## Decision
Every match calculation MUST be a Strategy class implementing a contract interface. No exceptions.

```php
// FORBIDDEN:
if ($weather === 'rain') { $accuracy -= 12; }

// REQUIRED:
class WeatherEffectStrategy implements ShotStrategyInterface {
    public function apply(ShotEvent $event, MatchContext $ctx): ShotEvent { ... }
}
```

Adding a new factor = one new class + one unit test. Zero changes to existing code.

## Consequences
- **Positive:** Every strategy is independently testable. Adding new factors is safe.
- **Negative:** More files, more classes. Accepted — this is the right trade-off for long-term maintainability.
