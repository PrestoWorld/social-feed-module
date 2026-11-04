<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Services;

use PrestoWorld\Modules\SocialFeed\Contracts\GroqClientInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;

/**
 * Pipeline sinh slug AI cho feed post.
 *
 * Luồng:
 *   post được tạo  -> slug = UUID, slug_generated = false
 *   worker/job     -> lấy bản ghi slug_generated = false order by retries ASC
 *   Groq trả slug -> slug = compose(...), slug_generated = true
 *   Groq lỗi      -> slug giữ nguyên (UUID), retries = retries + 1, thử lại sau
 *
 * Không có GROQ_API_KEY thì coi như "tắt": không gọi API, chỉ tăng retries
 * để không quét đi quét lại vô hạn.
 */
final class SlugProcessor
{
    private ?int $lastRetryAfter = null;

    public function __construct(
        private readonly SocialPostRepositoryInterface $posts,
        private readonly GroqClientInterface $groq,
        private readonly SlugComposer $composer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->groq->isConfigured();
    }

    /**
     * Xử lý một batch post đang chờ slug.
     *
     * @return array{generated: int, failed: int, pending: int, retry_after: ?int}
     */
    public function processPending(int $limit = 20): array
    {
        $generated = 0;
        $failed = 0;
        $retryAfter = null;

        if ($limit < 1) {
            $limit = 1;
        }

        $rows = $this->posts->pendingSlug($limit);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            // 429 từ một request -> dừng batch, trả retry_after cho caller tôn trọng.
            $result = $this->processPost($row);

            if ($result['ok']) {
                $generated++;
                continue;
            }

            $failed++;
            $retryAfter = $result['retry_after'] ?? $retryAfter;

            if ($retryAfter !== null) {
                break;
            }
        }

        return [
            'generated' => $generated,
            'failed' => $failed,
            'has_more' => $this->posts->pendingSlug(1) !== [],
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * Sinh slug cho đúng một post.
     *
     * @param array<string, mixed> $row dòng social_posts (cần id, business_id, content, slug_generated)
     * @return array{ok: bool, slug: ?string, retry_after: ?int, error: ?string}
     */
    public function processPost(array $row): array
    {
        $id = (string) ($row['id'] ?? '');

        if ($id === '') {
            return $this->error('slug: dòng social_posts thiếu id', null);
        }

        if (!empty($row['slug_generated'])) {
            return ['ok' => true, 'slug' => (string) ($row['slug'] ?? ''), 'retry_after' => null, 'error' => null];
        }

        if (!$this->groq->isConfigured()) {
            // Không có key: giữ slug UUID, đánh dấu đã thử -> không quét lại vô hạn.
            $this->posts->markSlugFailed($id);

            return $this->error('slug: GROQ_API_KEY chưa cấu hình', null);
        }

        $businessId = (string) ($row['business_id'] ?? '');
        $content = trim(strip_tags((string) ($row['content'] ?? '')));
        $seed = $businessId . '|' . (string) ($row['external_id'] ?? '') . '|' . $id;

        // Slug sinh lại phải khác slug cũ để phân biệt "đã thử mà thất bại".
        $slug = $this->groqSlug($content, $businessId, $seed);

        if ($slug === null) {
            $this->posts->markSlugFailed($id);

            return $this->error('slug: Groq không trả slug', $this->lastRetryAfter);
        }

        $final = $this->composer->compose($slug, $businessId, $seed);

        if ($final === '') {
            // Không bao giờ ghi slug rỗng — giữ UUID, đánh dấu thử lại.
            $this->posts->markSlugFailed($id);

            return $this->error('slug: compose() trả slug rỗng', null);
        }

        $this->posts->markSlugGenerated($id, $final);

        return ['ok' => true, 'slug' => $final, 'retry_after' => null, 'error' => null];
    }

    private function groqSlug(string $content, string $businessId, string $seed): ?string
    {
        $this->lastRetryAfter = null;

        $result = $this->groq->complete($this->prompt($content, $businessId, $seed));

        if (!$result['ok']) {
            $this->lastRetryAfter = $result['retry_after'];

            return null;
        }

        return $result['text'];
    }

    /**
     * System prompt tĩnh đứng trước (Groq prompt caching ưu tiên prefix khớp nhau),
     * phần biến (nội dung post) đứng sau.
     *
     * @return list<array{role: string, content: string}>
     */
    private function prompt(string $content, string $businessId, string $seed): array
    {
        $maxMeaning = SlugComposer::MAX_LENGTH - SlugComposer::SHOP_ID_MAX - SlugComposer::HASH_LENGTH - 2;

        return [
            [
                'role' => 'system',
                'content' => 'You generate URL slugs for social feed posts.'
                    . ' Reply with ONE slug and nothing else: lowercase ASCII letters a-z, digits,'
                    . ' single hyphens between words. No spaces, no quotes, no punctuation, no accents,'
                    . ' no explanation, no markdown. Max ' . $maxMeaning . ' characters.'
                    . ' Transliterate Vietnamese to unaccented Latin. Keep the core subject of the post.',
            ],
            [
                'role' => 'user',
                'content' => 'Shop: ' . ($businessId !== '' ? $businessId : '(global BookOwl feed)')
                    . "\nPost hash: " . $seed
                    . "\nPost content:\n" . mb_substr($content, 0, 1200, 'UTF-8'),
            ],
        ];
    }

    private function error(string $message, ?int $retryAfter): array
    {
        return ['ok' => false, 'slug' => null, 'retry_after' => $retryAfter, 'error' => $message];
    }
}
