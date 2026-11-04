<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Contracts;

/**
 * Khế ước tối thiểu để SlugProcessor gọi model sinh slug.
 *
 * Tách khỏi GroqClient concrete để test thay bằng fake không cần HTTP.
 */
interface GroqClientInterface
{
    /** false nghĩa là chưa cấu hình API key -> SlugProcessor bỏ qua, không gọi API. */
    public function isConfigured(): bool;

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array{ok: bool, text: ?string, retry_after: ?int, error: ?string}
     */
    public function complete(array $messages): array;
}
