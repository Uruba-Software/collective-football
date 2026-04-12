<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

/**
 * Maç Olayı Üretici Servisi
 *
 * Maç sırasında olağandışı, beklenmedik olaylar üretir.
 * Bu olaylar:
 * - Match Engine pipeline'ına enjekte edilir
 * - Oyuncu state'lerini etkiler (morale, stress, anger)
 * - Takım psikolojisini etkiler
 * - Bazıları maçı tatil edebilir
 *
 * KULLANIM (Match Engine içinden):
 *   $generator = app(MatchEventGeneratorService::class);
 *   $event = $generator->generateForMinute($context, $minute);
 *   if ($event !== null) {
 *       $pipeline->inject($event);
 *   }
 *
 * EVENT CORPUS ÜRETİMİ (seed data):
 *   php artisan ai:generate-event-corpus --count=500 --type=all
 *
 * YENİ TİP EKLEMEK:
 *   1. MatchEventType enum'una ekle
 *   2. SYSTEM_PROMPT'a örnek ekle
 *   3. impact() metoduna etkiyi tanımla
 *   Bu kadar.
 */
class MatchEventGeneratorService
{
    /**
     * Olayların maçta gerçekleşme olasılığı.
     * Her dakika bu oran kadar şans var.
     * 0.02 = %2 ihtimal / dakika → 90 dakikalık maçta ortalama 1.8 olay
     */
    private const BASE_PROBABILITY = 0.02;

    /**
     * Kritik dakikalarda olasılık çarpanı (penaltı bölgesi benzeri)
     * 44, 45, 89, 90. dakikalarda olaylar daha dramatik.
     */
    private const CRITICAL_MINUTE_MULTIPLIER = 2.5;

    private const CRITICAL_MINUTES = [44, 45, 89, 90];

    public function __construct(private readonly AiService $ai) {}

    /**
     * Belirli bir dakika için olağandışı olay üret.
     * %98 ihtimalle null döner (olay yok).
     * %2 ihtimalle MatchEvent döner.
     *
     * @param array<string, mixed> $matchContext /api/v1/match/prepare'den gelen context
     */
    public function generateForMinute(array $matchContext, int $minute): ?MatchEvent
    {
        $probability = in_array($minute, self::CRITICAL_MINUTES, true)
            ? self::BASE_PROBABILITY * self::CRITICAL_MINUTE_MULTIPLIER
            : self::BASE_PROBABILITY;

        if (random_int(1, 10000) > (int)($probability * 10000)) {
            return null;
        }

        return $this->generate($matchContext, $minute);
    }

    /**
     * Belirli bir maç context'i için olay üret (deterministik — test/seed için).
     *
     * @param array<string, mixed> $matchContext
     */
    public function generate(array $matchContext, int $minute): MatchEvent
    {
        $prompt = $this->buildPrompt($matchContext, $minute);

        try {
            $data = $this->ai->json(
                useCase: 'match_events',
                prompt: $prompt,
                systemPrompt: self::SYSTEM_PROMPT,
            );

            return MatchEvent::fromArray($data);

        } catch (\Throwable $e) {
            Log::warning('MatchEventGenerator AI failed, using fallback', [
                'minute' => $minute,
                'error'  => $e->getMessage(),
            ]);

            return $this->fallbackEvent($minute);
        }
    }

    /**
     * Toplu event corpus üretimi (seeder/artisan command için).
     * Sonuçlar database'e seed edilir.
     *
     * @return MatchEvent[]
     */
    public function generateCorpus(int $count = 100, ?string $type = null): array
    {
        $events = [];

        $prompt = "Farklı maçlarda gerçekleşebilecek {$count} adet olağandışı futbol olayı üret. "
            . ($type ? "Sadece şu tip: {$type}. " : "Tüm tipleri karıştır. ")
            . "Her olay benzersiz ve gerçekçi olmalı. JSON array döndür.";

        $data = $this->ai->json(
            useCase: 'event_corpus_generation',
            prompt: $prompt,
            systemPrompt: self::CORPUS_SYSTEM_PROMPT,
        );

        foreach ($data['events'] ?? $data as $item) {
            $events[] = MatchEvent::fromArray($item);
        }

        return $events;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function buildPrompt(array $matchContext, int $minute): string
    {
        $homeTeam   = $matchContext['home_team']['name'] ?? 'Ev Sahibi';
        $awayTeam   = $matchContext['away_team']['name'] ?? 'Deplasman';
        $weather    = $matchContext['weather']['condition'] ?? 'normal';
        $homeScore  = $matchContext['current_score']['home'] ?? 0;
        $awayScore  = $matchContext['current_score']['away'] ?? 0;
        $stadium    = $matchContext['stadium']['name'] ?? 'Stadyum';
        $crowd      = $matchContext['crowd']['atmosphere'] ?? 'normal';
        $matchType  = $matchContext['match_type'] ?? 'league';

        return <<<PROMPT
        Maç bilgileri:
        - Ev sahibi: {$homeTeam}, Deplasman: {$awayTeam}
        - Dakika: {$minute}
        - Skor: {$homeScore}-{$awayScore}
        - Hava: {$weather}
        - Stadyum: {$stadium} (atmosfer: {$crowd})
        - Maç tipi: {$matchType}

        Bu maçın {$minute}. dakikasında gerçekleşen olağandışı bir olay üret.
        Olay bu maça özgü olsun — skor, hava, atmosfer ve dakikayı göz önünde bulundur.
        PROMPT;
    }

    private function fallbackEvent(int $minute): MatchEvent
    {
        // AI başarısız olursa rule-based fallback
        $fallbacks = [
            ['type' => 'animal_on_pitch', 'animal' => 'güvercin', 'severity' => 'minor'],
            ['type' => 'crowd_disturbance', 'location' => 'kuzey tribün', 'severity' => 'minor'],
            ['type' => 'flare_thrown', 'origin' => 'deplasman sektörü', 'severity' => 'moderate'],
        ];

        return MatchEvent::fromArray([
            ...$fallbacks[array_rand($fallbacks)],
            'minute' => $minute,
            'description' => 'Küçük bir olay maçı anlık aksattı.',
            'match_suspended' => false,
        ]);
    }

    // ─── Promptlar ───────────────────────────────────────────────────────────

    private const SYSTEM_PROMPT = <<<'PROMPT'
    Sen bir futbol maçı olay üreticisisin. Görevin, gerçek futbol tarihinden ilham alarak
    olağandışı, beklenmedik maç olayları üretmek.

    OLAY TİPLERİ:
    - animal_on_pitch: Sahaya hayvan girişi (martı, köpek, kedi, at, inek, yılan...)
    - player_medical: Oyuncu sağlık krizi (kalp krizi, bilinç kaybı, nöbet...)
    - mass_protest: Toplu oyuncu protestosu (hakem kararına karşı, fiziksel protesto)
    - pitch_invasion: Saha işgali (tek seyirci, saldırgan fan grubu, çıplak protestocu...)
    - referee_scandal: Hakem skandalı (açık hata, rüşvet iddiaları, hakeme saldırı)
    - player_meltdown: Oyuncu psikolojik çöküşü (kendine zarar verme davranışı, maç dışı eylem)
    - club_president_incident: Kulüp başkanı olayı (sahaya inme, kavga, hakaret)
    - stadium_incident: Stadyum altyapı sorunu (tribün çöküşü, elektrik kesintisi, yangın)
    - match_fixing_revelation: Şike iddiası (maç sırasında ortaya çıkar)
    - disciplinary_cascade: Art arda disiplin olayları (3+ kırmızı kart bir maçta)
    - weather_extreme: Aşırı hava olayı (fırtına, dolu, sis maçı bitirir)
    - flare_incident: Meşale/bengal ateşi olayı
    - crowd_disturbance: Tribün olayı

    ÇIKTI FORMATI (JSON):
    {
      "type": "animal_on_pitch",
      "description": "81. dakikada büyük bir martı sahaya inerek topa çarptı...",
      "minute": 81,
      "severity": "minor|moderate|major|critical",
      "match_suspended": false,
      "suspension_duration_minutes": 0,
      "player_impacts": [
        {"player_role": "goalkeeper", "morale_delta": -5, "stress_delta": 10}
      ],
      "team_impacts": [
        {"team": "home", "morale_delta": 5, "chemistry_delta": 2}
      ],
      "permanent_consequences": [],
      "media_value": "low|medium|high|viral",
      "historical_precedent": "Real bir referans (opsiyonel)"
    }

    KURALLAR:
    - Gerçekçi ol. Futbol tarihinde olmayacak şeyler yazma.
    - severity: minor=maç devam, moderate=5dk duraklama, major=uzun duraklama, critical=tatil
    - player_impacts sadece olaydan doğrudan etkilenen rolleri içersin
    - Türkçe description yaz
    - historical_precedent opsiyonel ama güçlü olanları ekle (örn: "2012 Brezilya Serie A - sahaya köpek")
    PROMPT;

    private const CORPUS_SYSTEM_PROMPT = <<<'PROMPT'
    Sen bir futbol tarihi ve dramaturjisi uzmanısın. Çeşitli ligler, kültürler ve dönemlerden
    ilham alarak zengin, çeşitli ve gerçekçi futbol sahası olayları üretiyorsun.
    Bu veriler bir futbol simülasyon oyununun motor test veritabanı olarak kullanılacak.

    JSON formatı: {"events": [<her olay için MatchEvent nesnesi>]}

    Her olayın type alanı şunlardan biri olmalı:
    animal_on_pitch, player_medical, mass_protest, pitch_invasion, referee_scandal,
    player_meltdown, club_president_incident, stadium_incident, match_fixing_revelation,
    disciplinary_cascade, weather_extreme, flare_incident, crowd_disturbance

    Çeşitlilik: Her tip eşit oranda üretilsin. Coğrafi çeşitlilik olsun (Türkiye, Arjantin,
    İtalya, Brezilya, Afrika, Orta Doğu...). Dönem çeşitliliği olsun (1970ler-2020ler).
    PROMPT;
}
