<?php

namespace App\Services\Ai;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Maç Olağandışı Olayı — Value Object
 *
 * AI tarafından üretilir veya rule-based fallback ile oluşturulur.
 * Match Engine pipeline'ına enjekte edilir.
 *
 * Immutable. Oluşturulduktan sonra değiştirilemez.
 */
final readonly class MatchEvent implements Arrayable
{
    public function __construct(
        /** Olay tipi — MatchEventGeneratorService içindeki tip listesine bakın */
        public string $type,

        /** İnsan okunabilir açıklama (Türkçe) */
        public string $description,

        /** Olayın gerçekleştiği dakika */
        public int $minute,

        /** minor | moderate | major | critical */
        public string $severity,

        /** Maç tatil edildi mi? */
        public bool $matchSuspended,

        /** Kaç dakika durdu? (0 = durmadı) */
        public int $suspensionDurationMinutes,

        /**
         * Oyuncu etkileri
         * [['player_role' => 'goalkeeper', 'morale_delta' => -10, 'stress_delta' => 25], ...]
         *
         * @var array<int, array{player_role: string, morale_delta: int, stress_delta: int}>
         */
        public array $playerImpacts,

        /**
         * Takım etkileri
         * [['team' => 'home', 'morale_delta' => 5, 'chemistry_delta' => -3], ...]
         *
         * @var array<int, array{team: string, morale_delta: int, chemistry_delta: int}>
         */
        public array $teamImpacts,

        /**
         * Kalıcı sonuçlar (maç sonrası da devam eder)
         * ['player_suspended', 'club_fined', 'match_awarded', ...]
         *
         * @var string[]
         */
        public array $permanentConsequences,

        /** Medya değeri: low | medium | high | viral */
        public string $mediaValue,

        /** Gerçek tarihsel referans (varsa) */
        public ?string $historicalPrecedent,
    ) {}

    /**
     * AI'dan gelen ham array'den MatchEvent oluştur.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type:                      $data['type'] ?? 'unknown',
            description:               $data['description'] ?? '',
            minute:                    (int) ($data['minute'] ?? 0),
            severity:                  $data['severity'] ?? 'minor',
            matchSuspended:            (bool) ($data['match_suspended'] ?? false),
            suspensionDurationMinutes: (int) ($data['suspension_duration_minutes'] ?? 0),
            playerImpacts:             $data['player_impacts'] ?? [],
            teamImpacts:               $data['team_impacts'] ?? [],
            permanentConsequences:     $data['permanent_consequences'] ?? [],
            mediaValue:                $data['media_value'] ?? 'low',
            historicalPrecedent:       $data['historical_precedent'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'type'                        => $this->type,
            'description'                 => $this->description,
            'minute'                      => $this->minute,
            'severity'                    => $this->severity,
            'match_suspended'             => $this->matchSuspended,
            'suspension_duration_minutes' => $this->suspensionDurationMinutes,
            'player_impacts'              => $this->playerImpacts,
            'team_impacts'                => $this->teamImpacts,
            'permanent_consequences'      => $this->permanentConsequences,
            'media_value'                 => $this->mediaValue,
            'historical_precedent'        => $this->historicalPrecedent,
        ];
    }

    /** Maç tatil/iptal kararı gerektiriyor mu? */
    public function requiresMatchAbandonment(): bool
    {
        return $this->severity === 'critical' || $this->matchSuspended;
    }

    /** Haber değeri var mı? (Phase 6 — Media sistemi için) */
    public function isNewsworthy(): bool
    {
        return in_array($this->mediaValue, ['high', 'viral'], true);
    }
}
