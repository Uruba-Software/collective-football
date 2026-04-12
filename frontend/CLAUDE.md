# Frontend Engineering Guide
## Collective Football — Vue 3 / TypeScript

> Read `/CLAUDE.md` (root) and `backend/app/Engine/CLAUDE.md` before working on the frontend engine. The frontend is not a passive UI — it runs the match simulation for human-controlled matches.

---

## What This Layer Does

The frontend is the only interface between the player and the game. It has two distinct responsibilities:

1. **Match Engine (the most important part):** When a human player controls a team, the Vue.js frontend runs the full match simulation tick-by-tick using `src/engine/MatchEngine.ts`. The PHP backend provides the initial `MatchContext` package, then the frontend is authoritative for the entire match. Results are sent back to the backend after the match ends.

2. **Game UI:** All management screens — team management, tactics, player profiles, standings, transfers. These are standard data-driven Vue components backed by Pinia stores that talk to the backend API.

---

## Stack

```
Vue 3.5+          — Composition API only (script setup syntax always)
TypeScript 6      — Strict mode always
Pinia 3           — State management (no Vuex, no event bus)
Vue Router 5      — SPA routing
VueUse            — Composable utilities (@vueuse/core)
Axios 1.x         — HTTP client (configured in src/utils/http.ts)
Canvas API        — 2D match pitch rendering (no third-party canvas libs)
Chart.js 4        — Statistics visualization (via vue-chartjs)
D3.js 7           — Advanced data visualization (standings charts, form graphs)
Vite 8            — Build tool
Vitest 4          — Unit testing
Playwright 1.x    — E2E testing (match engine smoke tests)
```

---

## Non-Negotiable Rules

### Composition API Only. No Options API.

Every component uses `<script setup lang="ts">`. The Options API is disabled in practice — if you write it, the reviewer rejects it.

```vue
<!-- CORRECT -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useMatchStore } from '@/stores/match'

const matchStore = useMatchStore()
const score = computed(() => matchStore.score)
</script>

<!-- WRONG — Options API -->
<script lang="ts">
export default {
  data() { return { score: 0 } },
  computed: { ... }
}
</script>
```

### TypeScript Strict Mode. Always.

`tsconfig.app.json` has strict mode and `noUnusedLocals`, `noUnusedParameters`, `noFallthroughCasesInSwitch`. No `// @ts-ignore`. No `any` types unless you have a written comment explaining why it is literally impossible to type properly.

```typescript
// WRONG
const player: any = getPlayer(id)

// CORRECT
const player: PlayerContext = getPlayer(id)

// WRONG — implicit any
function applyModifier(event, ctx) { ... }

// CORRECT
function applyModifier(event: ShotEvent, ctx: MatchContext): ShotEvent { ... }
```

### `ref()` vs `reactive()` — use `ref()` by default

`reactive()` loses reactivity on destructure. Use `ref()` for primitive values and `ref()` with typed interfaces for objects. Use `reactive()` only when you have a clear reason (e.g., a complex object where you explicitly never destructure it).

---

## Component Naming Conventions

```
PascalCase for component files and imports:
  MatchPitch.vue          — match pitch canvas component
  PlayerCard.vue          — player info card
  TacticsBoard.vue        — formation selector
  EventFeed.vue           — live event log
  WeatherDisplay.vue      — weather overlay
  InterventionPanel.vue   — substitution + tactics controls
  StatsBars.vue           — statistics visualization

Directory structure mirrors domain:
  src/components/
  ├── match/
  │   ├── MatchPitch.vue          — Canvas pitch (the core visual)
  │   ├── EventFeed.vue           — Live text event log
  │   ├── MatchStats.vue          — Shots, possession, cards panel
  │   ├── WeatherDisplay.vue      — Weather overlay (rain/snow visual)
  │   ├── InterventionPanel.vue   — Substitution and tactic controls
  │   └── CommentaryBox.vue       — Commentary text display
  ├── tactics/
  │   ├── TacticsBoard.vue        — Formation selector + player positioning
  │   ├── FormationGrid.vue       — Visual grid for player placement
  │   └── MentalitySlider.vue     — 1-10 mentality control
  ├── player/
  │   ├── PlayerCard.vue          — Full player four-layer info card
  │   ├── PlayerMiniCard.vue      — Compact version for squad list
  │   └── ClimateProfileBar.vue   — Visual climate adaptation bars
  └── ui/
      ├── AppButton.vue           — Standardized button
      ├── AppModal.vue            — Modal wrapper
      ├── AppToast.vue            — Notification toast
      └── LoadingSpinner.vue      — Loading state
```

Component filename = component name = display name. No disambiguation prefixes.

---

## Pinia Store Conventions

Stores live in `src/stores/`. One store per domain. Use the `defineStore` composition syntax (not object syntax).

```typescript
// src/stores/match.ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { MatchContext, MatchScore, MatchEvent, PlayerMatchState } from '@/types/match'

export const useMatchStore = defineStore('match', () => {
    // State — ref() always
    const context  = ref<MatchContext | null>(null)
    const score    = ref<MatchScore>({ home: 0, away: 0 })
    const minute   = ref<number>(0)
    const events   = ref<MatchEvent[]>([])
    const isLive   = ref<boolean>(false)

    // Getters — computed() always
    const homeTeam = computed(() => context.value?.homeTeam ?? null)
    const awayTeam = computed(() => context.value?.awayTeam ?? null)
    const elapsed  = computed(() => `${minute.value}'`)

    // Actions — plain async functions
    async function loadContext(matchId: string): Promise<void> {
        const response = await http.post<MatchContext>(`/api/v1/matches/${matchId}/prepare`)
        context.value = response.data
    }

    function recordEvent(event: MatchEvent): void {
        events.value.push(event)
        if (event.type === 'GOAL') {
            score.value[event.team === 'home' ? 'home' : 'away']++
        }
    }

    function reset(): void {
        context.value = null
        score.value   = { home: 0, away: 0 }
        minute.value  = 0
        events.value  = []
        isLive.value  = false
    }

    return { context, score, minute, events, isLive, homeTeam, awayTeam, elapsed,
             loadContext, recordEvent, reset }
})
```

Store naming: `useMatchStore`, `usePlayerStore`, `useTeamStore`, `useTacticsStore`, `useAuthStore`.

**Never call `$store.commit()` or `$store.dispatch()` — those are Vuex patterns. Pinia actions are called directly: `matchStore.loadContext(id)`.**

---

## Canvas Engine Architecture

The match pitch is rendered on a `<canvas>` element using the Canvas 2D API. No WebGL, no Three.js. A top-down 2D pitch.

```typescript
// src/engine/MatchEngine.ts — the core engine class
export class MatchEngine {
    private canvas: HTMLCanvasElement
    private ctx: CanvasRenderingContext2D
    private matchContext: MatchContext
    private state: MatchEngineState
    private animationFrame: number | null = null

    constructor(canvas: HTMLCanvasElement, matchContext: MatchContext) {
        this.canvas = canvas
        this.ctx    = canvas.getContext('2d')!
        this.matchContext = matchContext
        this.state  = MatchEngineState.initial(matchContext)
    }

    start(): void {
        this.isRunning = true
        this.scheduleTick()
    }

    private scheduleTick(): void {
        // Each tick = 1 game minute ≈ 3 real seconds (1x speed)
        // At 2x: 1.5s per tick. At instant: synchronous.
        setTimeout(() => this.tick(), this.tickIntervalMs)
    }

    private tick(): void {
        // 1. Update all player positions (VectorEngine)
        this.vectorEngine.updatePositions(this.state)

        // 2. Update ball possession
        this.updatePossession()

        // 3. Increment match fatigue (climate-modified)
        this.updateFatigue()

        // 4. Run event generator
        const event = this.eventGenerator.generate(this.state, this.matchContext)

        // 5. If event → run through pipeline
        if (event) {
            const result = this.pipeline.run(event, this.matchContext, this.state)
            this.handleEventResult(result)
        }

        // 6. Check character fracture conditions
        this.fractureEngine.check(this.state.players, this.matchContext)

        // 7. Render canvas frame
        this.renderer.render(this.canvas, this.ctx, this.state)

        // 8. Broadcast tick state (multiplayer via WebSocket)
        if (this.isMultiplayer) {
            this.websocket.broadcast(this.buildTickBroadcast())
        }

        // 9. Update Pinia store (UI reacts automatically)
        this.matchStore.updateState(this.state)

        // 10. Schedule next tick
        this.state.minute++
        if (this.state.minute <= 90) {
            this.scheduleTick()
        } else {
            this.finish()
        }
    }
}
```

**Canvas rendering rules:**
- Clear the canvas on every frame: `ctx.clearRect(0, 0, canvas.width, canvas.height)`
- Pitch coordinates are in real-world meters (105m × 68m standard pitch). Scale to canvas pixels using `VectorEngine.toCanvasCoords()`.
- Render order: pitch background → pitch lines → player dots → ball → UI overlays → weather effects
- Players are circles (radius 4px) with team colors. Label with shirt number below.
- Weather overlay: semi-transparent rain/snow particle system drawn last.

---

## How to Mirror a PHP Strategy in TypeScript

Every PHP strategy in `backend/app/Engine/Strategies/` has a TypeScript mirror in `frontend/src/engine/strategies/`. The formula must be identical.

Example: mirroring `ClimateAdaptationStrategy.php`.

**Step 1: Create the TypeScript interface (if not already exists):**
```typescript
// src/engine/contracts/ShotStrategyInterface.ts
export interface ShotStrategy {
    apply(event: ShotEvent, ctx: MatchContext): ShotEvent
}
```

**Step 2: Create the TypeScript implementation:**
```typescript
// src/engine/strategies/shot/ClimateAdaptationStrategy.ts
import type { ShotStrategy } from '@/engine/contracts/ShotStrategyInterface'
import type { ShotEvent, MatchContext } from '@/types/engine'

export class ClimateAdaptationStrategy implements ShotStrategy {
    apply(event: ShotEvent, ctx: MatchContext): ShotEvent {
        const player  = ctx.getPlayer(event.shooterId)
        const weather = ctx.weather
        const adapt   = player.climateAdaptation

        // COLD: Below 5°C — formula MUST match PHP exactly
        if (weather.temperatureCelsius < 5) {
            const coldFactor  = adapt.coldComfort / 100
            const tempPenalty = Math.max(0, (5 - weather.temperatureCelsius) / 30)
            event.physicalOutputModifier *= (0.60 + coldFactor * 0.50 - tempPenalty)
        }

        // HOT: Above 30°C
        if (weather.temperatureCelsius > 30) {
            const heatFactor = adapt.heatComfort / 100
            const tempExcess = (weather.temperatureCelsius - 30) / 20
            event.staminaDrainModifier   *= (2.0 - heatFactor * 1.2 + tempExcess)
            event.physicalOutputModifier *= (0.65 + heatFactor * 0.45)
        }

        // ALTITUDE: Above 1500m
        if (ctx.stadium.altitudeMeters > 1500) {
            const excessKm       = (ctx.stadium.altitudeMeters - 1500) / 1000
            const altAdapt       = adapt.altitudeComfort / 100
            const rawPenalty     = excessKm * 0.12
            const effectivePenalty = rawPenalty * (1 - altAdapt * 0.75)
            event.staminaDrainModifier   *= (1 + effectivePenalty)
            event.physicalOutputModifier *= (1 - effectivePenalty * 0.4)
        }

        return event
    }
}
```

**Step 3: Register in the pipeline:**
```typescript
// src/engine/pipeline/ShotPipeline.ts
import { ClimateAdaptationStrategy } from '@/engine/strategies/shot/ClimateAdaptationStrategy'

export const shotPipeline: ShotStrategy[] = [
    new PhysicsCalculationStrategy(),
    new GoalkeeperDimensionStrategy(),
    new FatigueEffectStrategy(),
    new WeatherEffectStrategy(),
    new ClimateAdaptationStrategy(),   // ← add here
    new AltitudeEffectStrategy(),
    new FastingEffectStrategy(),
    new PitchQualityStrategy(),
    new ChanceLayerStrategy(),
    new OutcomeResolverStrategy(),
]
```

**Step 4: Write a Vitest unit test:**
```typescript
// tests/unit/engine/ClimateAdaptationStrategy.test.ts
import { describe, it, expect } from 'vitest'
import { ClimateAdaptationStrategy } from '@/engine/strategies/shot/ClimateAdaptationStrategy'

describe('ClimateAdaptationStrategy', () => {
    it('applies cold boost to Norwegian player below 5°C', () => {
        const event = createShotEvent({ physicalOutputModifier: 1.0 })
        const ctx   = createMatchContext({ temperature: -5, playerNationality: 'Norwegian', coldComfort: 90 })
        const result = new ClimateAdaptationStrategy().apply(event, ctx)
        expect(result.physicalOutputModifier).toBeGreaterThan(1.0)
    })

    it('applies severe cold penalty to Nigerian player below 5°C', () => {
        const event = createShotEvent({ physicalOutputModifier: 1.0 })
        const ctx   = createMatchContext({ temperature: -5, playerNationality: 'Nigerian', coldComfort: 28 })
        const result = new ClimateAdaptationStrategy().apply(event, ctx)
        expect(result.physicalOutputModifier).toBeLessThan(0.80)
    })
})
```

---

## TypeScript Type Conventions

All types live in `src/types/`. One file per domain:
```
src/types/
├── engine.ts      ← MatchContext, ShotEvent, DuelEvent, PlayerContext, etc.
├── match.ts       ← MatchScore, MatchEvent, TickBroadcast, etc.
├── player.ts      ← Player, PlayerCharacter, PlayerForm, ClimateAdaptation
├── team.ts        ← Team, TeamIdentity, Formation
├── api.ts         ← API request/response shapes
└── common.ts      ← UUID, Timestamp, shared utility types
```

Never use `interface` where `type` works. Use `interface` only for objects that need to be extended/implemented (strategy contracts).

```typescript
// Prefer type aliases for data shapes
type PlayerForm = {
    currentForm: number           // 0-100
    confidenceStreak: number
    fatigueAccumulative: number   // 0-100
    injuryStatus: 'fit' | 'minor' | 'moderate' | 'severe' | 'out'
    psychologicalBase: number
    mediaPressure: number
}

// Use interface for contracts (implementable)
interface ShotStrategy {
    apply(event: ShotEvent, ctx: MatchContext): ShotEvent
}
```

**Naming:** TypeScript types use PascalCase. TypeScript properties use camelCase (the API returns snake_case — convert in `src/utils/http.ts` using a response interceptor that transforms keys).

---

## Composable Conventions

Composables live in `src/composables/`. Prefix with `use`. One composable per concern.

```typescript
// src/composables/useMatchTimer.ts
export function useMatchTimer(onTick: () => void, intervalMs: number) {
    const isRunning = ref(false)
    let timer: ReturnType<typeof setTimeout> | null = null

    function start() {
        isRunning.value = true
        schedule()
    }

    function stop() {
        isRunning.value = false
        if (timer) clearTimeout(timer)
    }

    function schedule() {
        if (!isRunning.value) return
        timer = setTimeout(() => { onTick(); schedule() }, intervalMs)
    }

    onUnmounted(stop)
    return { isRunning, start, stop }
}
```

Do not use a composable where a utility function (`src/utils/`) is sufficient. Composables are for reactive concerns only.

---

## Test Requirements

### Vitest (Unit Tests) — `tests/unit/`

Every engine strategy must have a unit test. Run with:
```bash
npm run test:unit
# or with coverage:
npm run test:unit -- --coverage
```

Required unit test coverage:
- All strategy classes in `src/engine/strategies/`
- All pipeline classes in `src/engine/pipeline/`
- VectorEngine calculations
- Pinia store actions and getters
- Composables with complex logic

```typescript
// tests/unit/engine/ShotPipeline.test.ts
import { describe, it, expect, beforeEach } from 'vitest'
import { ShotPipeline } from '@/engine/pipeline/ShotPipeline'

describe('ShotPipeline', () => {
    it('runs all strategies in order and returns a result with an outcome', () => {
        const event  = createShotEvent()
        const ctx    = createMatchContext()
        const result = new ShotPipeline().run(event, ctx)
        expect(result.outcome).not.toBeNull()
        expect(['goal', 'saved', 'wide', 'blocked', 'post']).toContain(result.outcome)
    })
})
```

### Playwright (E2E Tests) — `tests/e2e/`

E2E tests are smoke tests for critical user flows. They are not exhaustive.

Required E2E tests:
```
tests/e2e/
├── match-engine-smoke.spec.ts    ← Start a match, run 10 ticks, verify score display works
├── auth-flow.spec.ts             ← Login → land on dashboard → logout
└── player-profile.spec.ts        ← Navigate to player, verify all four layers display
```

Run Playwright:
```bash
npm run test:e2e
# For headed mode (debugging):
npm run test:e2e -- --headed
```

---

## Forbidden Patterns

```typescript
// 1. Options API
export default { data() { ... } }  // NEVER

// 2. Vuex (not installed, but just in case)
import { useStore } from 'vuex'  // NEVER. Use Pinia.

// 3. Direct DOM manipulation outside canvas engine
document.querySelector('.player-dot').style.left = '50px'  // NEVER. Let Vue handle DOM.

// 4. Inline styles on components (use CSS classes)
<div :style="{ color: 'red' }">  // NEVER for business-logic-driven styling
// Exception: canvas position offsets that are calculated values

// 5. Hardcoded formula (same law as PHP)
event.accuracyModifier -= 0.12  // outside a strategy class. NEVER.

// 6. API calls inside a component (go through a Pinia action)
const response = await axios.get('/api/v1/players')  // inside <script setup>. NEVER.
// Correct: playerStore.loadPlayers()

// 7. Watching a store property to trigger side effects (use actions)
watch(() => matchStore.score, (newScore) => {
    axios.post('/result', newScore)  // NEVER. This belongs in a store action.
})

// 8. Non-typed component props
const props = defineProps(['player', 'team'])  // NEVER. Always typed:
const props = defineProps<{ player: PlayerContext; team: TeamContext }>()

// 9. console.log left in production code
console.log('tick:', minute)  // Only in debug mode, never committed

// 10. Mutation of props
props.player.form.currentForm = 80  // NEVER. Props are read-only.
// Emit an event or use a store action.
```

---

## Directory Map

```
frontend/
├── src/
│   ├── engine/                   ← Match simulation engine
│   │   ├── MatchEngine.ts        ← Main engine class (tick loop, orchestration)
│   │   ├── VectorEngine.ts       ← Player positioning, movement math
│   │   ├── EventGenerator.ts     ← Probability-weighted event selection
│   │   ├── CharacterFractureEngine.ts
│   │   ├── AIManager.ts          ← AI opponent (evaluates every 5 ticks)
│   │   ├── contracts/            ← Strategy interfaces
│   │   ├── strategies/
│   │   │   ├── shot/             ← Mirrors backend Strategies/Shot/
│   │   │   ├── duel/
│   │   │   ├── pass/
│   │   │   ├── movement/
│   │   │   └── morale/
│   │   ├── pipeline/             ← Pipeline orchestrators
│   │   └── events/               ← Event type definitions
│   ├── components/
│   │   ├── match/
│   │   ├── tactics/
│   │   ├── player/
│   │   └── ui/
│   ├── composables/              ← Reusable reactive logic
│   ├── stores/                   ← Pinia stores (one per domain)
│   ├── types/                    ← All TypeScript types
│   ├── utils/
│   │   ├── http.ts               ← Axios instance with interceptors (CSRF, auth, snake_case→camelCase)
│   │   └── format.ts             ← Date, number, position formatters
│   ├── router/
│   │   └── index.ts              ← Vue Router configuration
│   ├── App.vue
│   └── main.ts                   ← App bootstrap (Pinia, Router, app.mount)
├── tests/
│   ├── unit/
│   │   └── engine/               ← Strategy unit tests (Vitest)
│   └── e2e/                      ← Playwright smoke tests
├── package.json
├── vite.config.ts
└── tsconfig.app.json             ← strict mode, noUnusedLocals, erasableSyntaxOnly
```
