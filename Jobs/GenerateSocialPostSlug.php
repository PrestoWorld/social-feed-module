<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Jobs;

use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Services\SlugProcessor;
use Witals\Framework\Application;
use Witals\Framework\Queue\Job;

/**
 * Job nền sinh slug AI cho một social post.
 *
 * Payload chỉ chứa business_id + external_id (serializable) — repository và
 * slug processor được resolve từ container trong process worker, theo mẫu
 * RunSyncJob của SyncCore.
 *
 * maxTries = 1: retry nằm ở tầng slug_generated/retries của DB, không để
 * worker retry âm thầm (tránh gọi Groq nhiều lần khi đang rate limit).
 */
final class GenerateSocialPostSlug extends Job
{
    public ?string $queue = 'social';
    public ?int $maxTries = 1;
    public ?int $timeout = 60;

    public function __construct(
        private readonly string $businessId,
        private readonly string $externalId,
    ) {
    }

    public function handle(): void
    {
        $app = app(Application::class);
        if (!$app instanceof Application) {
            return;
        }

        $posts = $app->make(SocialPostRepositoryInterface::class);
        if (!$posts instanceof SocialPostRepositoryInterface) {
            return;
        }

        $post = $posts->find($this->businessId, $this->externalId);

        // Post đã bị xoá, hoặc slug đã sinh xong từ lúc dispatch tới giờ -> bỏ qua.
        if ($post === null || !empty($post['slug_generated'])) {
            return;
        }

        $processor = $app->make(SlugProcessor::class);
        if ($processor instanceof SlugProcessor) {
            $processor->processPost($post);
        }
    }

    public function displayName(): string
    {
        return 'social-feed:slug:' . $this->businessId . ':' . $this->externalId;
    }
}
