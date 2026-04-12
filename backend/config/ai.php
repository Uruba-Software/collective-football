<?php

/**
 * Collective Football — AI Provider Configuration
 *
 * Çoklu AI provider desteği. Provider değiştirmek için sadece
 * AI_PROVIDER env değişkenini değiştirmek yeterli.
 *
 * Desteklenen provider'lar:
 *   openai     → OpenAI API (GPT-4o, GPT-4o-mini, vs.)
 *   anthropic  → Anthropic API (Claude)
 *   ollama     → Self-hosted Ollama (Qwen, Llama, Mistral, vs.)
 *
 * Prism provider enum'ları:
 *   \EchoLabs\Prism\Enums\Provider::OpenAI
 *   \EchoLabs\Prism\Enums\Provider::Anthropic
 *   \EchoLabs\Prism\Enums\Provider::Ollama
 *
 * GPU Server (Phase 4 hedef):
 *   AI_PROVIDER=ollama
 *   OLLAMA_BASE_URL=http://gpu-server-ip:11434
 *   AI_MODEL=qwen2.5:7b
 *   Redmine: #199
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Aktif Provider
    |--------------------------------------------------------------------------
    | Hangi AI provider kullanılacak. Tüm servisler bu değeri okur.
    | openai | anthropic | ollama
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Aktif Model
    |--------------------------------------------------------------------------
    | Seçili provider için kullanılacak model.
    | OpenAI:    gpt-4o-mini, gpt-4o, gpt-3.5-turbo
    | Anthropic: claude-haiku-4-5-20251001, claude-sonnet-4-6
    | Ollama:    qwen2.5:7b, llama3:8b, mistral:7b
    */
    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Provider Konfigürasyonları
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => 'gpt-4o-mini',
            'fallback_model' => 'gpt-3.5-turbo',
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => 'claude-haiku-4-5-20251001',
            'fallback_model' => 'claude-haiku-4-5-20251001',
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'default_model' => env('OLLAMA_MODEL', 'qwen2.5:7b'),
            'fallback_model' => 'llama3:8b',
            // Ollama'nın Prism entegrasyonu OpenAI-compat API üzerinden
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Kullanım Alanları (use_cases)
    |--------------------------------------------------------------------------
    | Her kullanım alanı için provider ve model override edilebilir.
    | Varsayılan olarak aktif provider/model kullanılır.
    |
    | Bu yapı sayesinde:
    | - match_events: ucuz/hızlı model (gpt-4o-mini)
    | - player_psychology: daha güçlü model (gpt-4o)
    | - news_generation: bulk için Ollama, premium için OpenAI
    */
    'use_cases' => [

        // Maç sırasında olağandışı olaylar üretimi
        // (martı topa çarpar, kalp krizi, saha işgali, vs.)
        'match_events' => [
            'provider' => env('AI_MATCH_EVENTS_PROVIDER', null), // null = aktif provider
            'model'    => env('AI_MATCH_EVENTS_MODEL', null),    // null = aktif model
            'temperature' => 0.9,   // Yüksek: daha yaratıcı olaylar
            'max_tokens'  => 500,
        ],

        // Günlük haber üretimi (gazete haberleri, sosyal medya)
        'news_generation' => [
            'provider' => env('AI_NEWS_PROVIDER', null),
            'model'    => env('AI_NEWS_MODEL', null),
            'temperature' => 0.8,
            'max_tokens'  => 1000,
        ],

        // Oyuncu psikolojisi analizi (karakter değişimi kararları)
        'player_psychology' => [
            'provider' => env('AI_PSYCHOLOGY_PROVIDER', null),
            'model'    => env('AI_PSYCHOLOGY_MODEL', null),
            'temperature' => 0.4,   // Düşük: tutarlı kararlar
            'max_tokens'  => 300,
        ],

        // Maç motoru için örnek olay kümesi üretimi (seed/test data)
        'event_corpus_generation' => [
            'provider' => env('AI_CORPUS_PROVIDER', null),
            'model'    => env('AI_CORPUS_MODEL', null),
            'temperature' => 1.0,   // Maksimum yaratıcılık
            'max_tokens'  => 2000,
        ],

        // Transfer haberleri, şike iddiaları, ceza kararları
        'scandal_generation' => [
            'provider' => env('AI_SCANDAL_PROVIDER', null),
            'model'    => env('AI_SCANDAL_MODEL', null),
            'temperature' => 0.85,
            'max_tokens'  => 800,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Güvenlik
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'requests_per_minute' => env('AI_RATE_LIMIT_RPM', 60),
        'tokens_per_day'      => env('AI_RATE_LIMIT_TPD', 1_000_000),
    ],

];
