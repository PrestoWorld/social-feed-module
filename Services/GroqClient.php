<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Services;

use PrestoWorld\Modules\SocialFeed\Contracts\GroqClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Witals\Framework\Application;

/**
 * Client tối giản gọi Groq Chat Completions (tương thích OpenAI).
 *
 * Document tham chiếu: https://console.groq.com/docs/quickstart
 *   POST https://api.groq.com/openai/v1/chat/completions
 *   Authorization: Bearer $GROQ_API_KEY
 *   -> choices[0].message.content
 *
 * Những tham số Groq KHÔNG hỗ trợ (logprobs / logit_bias / top_logprobs /
 * messages[].name) không được gửi — API từ chối và không đáng để retry.
 *
 * Mọi lỗi (401 key sai, 429 rate limit, timeout, mạng) đều trả về
 * ['ok' => false] thay vì throw: caller quyết định để slug trống và sinh lại sau.
 */
final class GroqClient implements GroqClientInterface
{
    public const DEFAULT_BASE_URL = 'https://api.groq.com/openai/v1';
    public const DEFAULT_MODEL = 'llama-3.1-8b-instant';

    /** temperature > 0 theo docs Groq — giá trị 0 bị convert sang 1e-8 (FP8) và có thể ra NaN. */
    private const TEMPERATURE = 0.5;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function apiKey(): string
    {
        return trim((string) $this->app->config('social-feed.groq.api_key', ''));
    }

    public function model(): string
    {
        $model = trim((string) $this->app->config('social-feed.groq.model', ''));

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    public function baseUrl(): string
    {
        $url = trim((string) $this->app->config('social-feed.groq.base_url', ''));

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * Chưa cấu hình API key thì coi như "tắt" — caller bỏ qua, không log lỗi.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function timeout(): int
    {
        $timeout = (int) $this->app->config('social-feed.groq.timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    /**
     * Gọi chat completion với 1 user message, trả về text của assistant.
     *
     * @param list<array{role: string, content: string}> $messages
     * @return array{ok: bool, text: ?string, retry_after: ?int, error: ?string}
     */
    public function complete(array $messages): array
    {
        if (!$this->isConfigured()) {
            return $this->failure('groq: GROQ_API_KEY chưa được cấu hình', null);
        }

        try {
            $client = HttpClient::create([
                'base_uri' => $this->baseUrl(),
                'timeout' => $this->timeout(),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $response = $client->request('POST', '/chat/completions', [
                'json' => [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'n' => 1,
                    'temperature' => self::TEMPERATURE,
                    'max_tokens' => 64,
                ],
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
        } catch (\Throwable $e) {
            // TransportException (timeout / DNS / reset) hoặc json encode lỗi.
            return $this->failure('groq: ' . $e->getMessage(), null);
        }

        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];

        if ($status < 200 || $status >= 300) {
            $message = (string) ($body['error']['message'] ?? ('HTTP ' . $status));

            // Chỉ retry khi server bảo nên: 429 + retry-after, hoặc body báo rate limit.
            $retryAfter = $this->retryAfter($status, $body, $response->getHeaders(false));

            return $this->failure('groq: HTTP ' . $status . ' ' . $message, $retryAfter);
        }

        $content = $body['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return $this->failure('groq: response rỗng (không có choices[0].message.content)', null);
        }

        return [
            'ok' => true,
            'text' => trim($content),
            'retry_after' => null,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $headers
     */
    private function retryAfter(int $status, array $body, array $headers): ?int
    {
        if ($status !== 429) {
            return null;
        }

        $header = $headers['retry-after'][0] ?? null;
        if (is_string($header) && is_numeric($header)) {
            return max(0, (int) $header);
        }

        // Body dạng {"error":{"message":"... rate_limit_exceeded ..."}}
        $message = strtolower((string) ($body['error']['message'] ?? ''));
        if (str_contains($message, 'rate_limit')) {
            return 60;
        }

        return null;
    }

    private function failure(string $error, ?int $retryAfter): array
    {
        return [
            'ok' => false,
            'text' => null,
            'retry_after' => $retryAfter,
            'error' => $error,
        ];
    }
}
