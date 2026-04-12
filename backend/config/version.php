<?php

return [
    /*
     * Collective Football — Version Configuration
     *
     * VERSIONING STRATEGY:
     * 0.MINOR.PATCH — Pre-launch
     * MINOR = Phase number (Phase 0 = 0.0.x, Phase 1 = 0.1.x, Phase 2 = 0.2.x)
     * PATCH = Task/bug/feature count within phase
     * 1.0.0 = Public launch (Phase 1 complete + battle-tested)
     *
     * BACKWARD COMPATIBILITY:
     * Active game instances are NEVER broken by version upgrades.
     * Each game instance stores its started_at_version.
     * Engine version gates handle compatibility.
     */
    'current' => env('APP_VERSION', '0.0.3'),
    'phase'   => env('GAME_PHASE', 0),

    /*
     * Game instance compatibility.
     * Games started at version X continue to work when version advances to Y.
     * The engine selects the correct strategy set based on match.started_at_version.
     */
    'min_compatible_game_version' => '0.0.1',

    /*
     * Public launch version — all Phase 1 features complete.
     */
    'launch_version' => '1.0.0',
];
