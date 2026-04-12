<?php

namespace App\Services\Ai;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Text\Response as TextResponse;
use Illuminate\Support\Facades\Log;

/**
 * Collective Football — Merkezi AI Servisi
 *
 * Tüm AI işlemleri bu servis üzerinden geçer.
 * Provider değişince sadece AI_PROVIDER env değişkeni değişir.
 *
 * KULLANIM ÖRNEĞİ:
 *   $ai = app(AiService::class);
 *   $response = $ai->text('match_events', 'Bir maçta olağandışı olay yarat...', context: [...]);
 *
 * YENİ USE CASE EKLEMEK:
 *   1. config/ai.php → use_cases dizisine ekle
 *   2. Bu servisi değiştirmene gerek yok
 *   3. Yeni bir XxxGeneratorService yaz, bu servisi inject et
 */
class AiService
{
    /**
     * Text completion — en yaygın kullanım.
     *
     * @param string $useCase config/ai.php use_cases anahtarı
     * @param string $prompt  Kullanıcı/sistem promptu
     * @param array  $context Prompt içine gömülecek context değişkenleri
     * @param string|null $systemPrompt Override sistem promptu
     */
    public function text(
        string $useCase,
        string $prompt,
        array $context = [],
        ?string $systemPrompt = null,
    ): string {
        $config    = $this->resolveConfig($useCase);
        $provider  = $this->resolveProvider($config['provider']);
        $model     = $config['model'];

        // Context değişkenlerini prompt'a göm
        if (! empty($context)) {
            $prompt = $this->interpolate($prompt, $context);
        }

        try {
            $prism = Prism::text()
                ->using($provider, $model)
                ->withClientOptions($this->clientOptions())
                ->withMaxTokens($config['max_tokens']);

            if ($systemPrompt !== null) {
                $prism = $prism->withSystemPrompt($systemPrompt);
            }

            /** @var TextResponse $response */
            $response = $prism->withPrompt($prompt)->generate();

            return $response->text;

        } catch (\Throwable $e) {
            Log::error('AiService text generation failed', [
                'use_case' => $useCase,
                'provider' => $config['provider'],
                'model'    => $model,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * JSON çıktı döndüren text completion.
     * Prompt'a JSON formatı talimatı otomatik eklenir.
     *
     * @return array<string, mixed>
     */
    public function json(
        string $useCase,
        string $prompt,
        array $context = [],
        ?string $systemPrompt = null,
    ): array {
        $jsonSystemPrompt = ($systemPrompt ?? '')
            . "\n\nSadece geçerli JSON döndür. Markdown code block kullanma. Açıklama yazma.";

        $raw = $this->text($useCase, $prompt, $context, $jsonSystemPrompt);

        // Code block varsa temizle
        $cleaned = preg_replace('/^```json?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);

        $decoded = json_decode(trim($cleaned), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AiService JSON parse failed', [
                'use_case' => $useCase,
                'raw'      => substr($raw, 0, 500),
                'error'    => json_last_error_msg(),
            ]);
            throw new \RuntimeException("AI JSON parse hatası: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Prism client options — base_url ve api_key enjeksiyonu.
     * AI_BASE_URL doluysa onu kullanır (Ollama, Azure, LiteLLM vs.)
     * AI_API_KEY / OPENAI_API_KEY / ANTHROPIC_API_KEY sırasıyla denenir.
     *
     * @return array<string, string>
     */
    private function clientOptions(): array
    {
        $options = [];

        $baseUrl = config('ai.base_url');
        if (! empty($baseUrl)) {
            $options['base_url'] = rtrim($baseUrl, '/');
        }

        $apiKey = config('ai.api_key');
        if (! empty($apiKey)) {
            $options['api_key'] = $apiKey;
        }

        return $options;
    }

    /**
     * Hangi provider aktif?
     */
    public function activeProvider(): string
    {
        return config('ai.provider', 'openai');
    }

    /**
     * Hangi model aktif?
     */
    public function activeModel(): string
    {
        return config('ai.model', 'gpt-4o-mini');
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Use case config'ini çöz (global override + use_case spesifik).
     *
     * @return array{provider: string, model: string, temperature: float, max_tokens: int}
     */
    private function resolveConfig(string $useCase): array
    {
        $global   = config('ai');
        $specific = config("ai.use_cases.{$useCase}", []);

        return [
            'provider'    => $specific['provider'] ?? $global['provider'],
            'model'       => $specific['model']    ?? $global['model'],
            'temperature' => $specific['temperature'] ?? 0.7,
            'max_tokens'  => $specific['max_tokens']  ?? 1000,
        ];
    }

    /**
     * String provider adını Prism Provider enum'una çevir.
     */
    private function resolveProvider(string $provider): Provider
    {
        return match ($provider) {
            'openai'    => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'ollama'    => Provider::Ollama,
            default     => throw new \InvalidArgumentException("Bilinmeyen AI provider: {$provider}"),
        };
    }

    /**
     * Prompt içine context değişkenlerini göm.
     * Syntax: {{variable_name}}
     *
     * Örnek: "{{player_name}} için psikoloji raporu yaz"
     */
    private function interpolate(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            $template = str_replace("{{{{$key}}}}", (string) $value, $template);
        }

        return $template;
    }
}
