<?php

/**
 * Collective Football — AI Provider Configuration
 *
 * Çoklu AI provider desteği. Provider değiştirmek için sadece
 * AI_PROVIDER, AI_MODEL, AI_API_KEY değişkenlerini güncellemek yeterli.
 *
 * ─── PROVIDER DEĞIŞTIRME KILAVUZU ──────────────────────────────────────────
 *
 * Şu an (OpenAI):
 *   AI_PROVIDER=openai
 *   AI_MODEL=gpt-4o-mini
 *   AI_API_KEY=sk-proj-...
 *   AI_BASE_URL=          (boş = OpenAI default: https://api.openai.com/v1)
 *
 * Anthropic'e geçmek için:
 *   AI_PROVIDER=anthropic
 *   AI_MODEL=claude-haiku-4-5-20251001
 *   AI_API_KEY=sk-ant-...
 *   AI_BASE_URL=          (boş = Anthropic default: https://api.anthropic.com)
 *
 * Phase 4 — GPU Server / Ollama (Redmine #199):
 *   AI_PROVIDER=ollama
 *   AI_MODEL=qwen2.5:7b
 *   AI_API_KEY=           (Ollama auth yoksa boş)
 *   AI_BASE_URL=http://gpu-server-ip:11434
 *
 * AI_BASE_URL ne zaman dolar?
 *   - Ollama/self-hosted LLM kullanırken
 *   - OpenAI-compatible proxy üzerinden geçerken (Azure OpenAI, LiteLLM, vs.)
 *   - Boş bırakılırsa her provider kendi default URL'ini kullanır
 *
 * Prism provider enum'ları:
 *   \EchoLabs\Prism\Enums\Provider::OpenAI
 *   \EchoLabs\Prism\Enums\Provider::Anthropic
 *   \EchoLabs\Prism\Enums\Provider::Ollama
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Aktif Provider
    |--------------------------------------------------------------------------
    | openai | anthropic | ollama
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Aktif Model
    |--------------------------------------------------------------------------
    | OpenAI:    gpt-4o-mini, gpt-4o, gpt-3.5-turbo
    | Anthropic: claude-haiku-4-5-20251001, claude-sonnet-4-6
    | Ollama:    qwen2.5:7b, llama3:8b, mistral:7b
    */
    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | API Key — Tüm provider'lar için tek değişken
    |--------------------------------------------------------------------------
    | Provider değişince sadece bu değer değişir.
    | Ollama gibi auth gerektirmeyen provider'larda boş bırakılır.
    */
    'api_key' => env('AI_API_KEY') ?? env('OPENAI_API_KEY') ?? env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL — Self-hosted / Proxy desteği
    |--------------------------------------------------------------------------
    | Boş bırakılırsa her provider kendi varsayılan URL'ini kullanır:
    |   openai    → https://api.openai.com/v1
    |   anthropic → https://api.anthropic.com
    |   ollama    → http://localhost:11434  (fallback)
    |
    | Doldurulduğunda bu URL kullanılır (Ollama, Azure, LiteLLM, vs.)
    */
    'base_url' => env('AI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Provider Defaults (fallback model başarısız olursa)
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'openai' => [
            'default_model'  => 'gpt-4o-mini',
            'fallback_model' => 'gpt-3.5-turbo',
            'default_url'    => 'https://api.openai.com/v1',
        ],

        'anthropic' => [
            'default_model'  => 'claude-haiku-4-5-20251001',
            'fallback_model' => 'claude-haiku-4-5-20251001',
            'default_url'    => 'https://api.anthropic.com',
        ],

        'ollama' => [
            'default_model'  => 'qwen2.5:7b',
            'fallback_model' => 'llama3:8b',
            'default_url'    => 'http://localhost:11434',
            // Ollama, OpenAI-compatible API sunar — Prism bunu destekler
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
