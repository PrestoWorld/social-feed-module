<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Services;

use PrestoWorld\Modules\SocialFeed\Jobs\GenerateSocialPostSlug;
use Witals\Framework\Application;
use Witals\Framework\Queue\QueueManager;

/**
 * Push job sinh slug vào queue sau khi post được tạo.
 *
 * Mọi lỗi (queue chưa bật, worker chưa chạy, bảng jobs thiếu) đều bị nuốt:
 * việc sinh slug vẫn được worker quét lại theo slug_generated = false nên
 * không bao giờ mất post. Queue chỉ là đường nhanh, không phải đường duy nhất.
 */
final class SlugDispatcher
{
    public const QUEUE = 'social';

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function dispatch(string $businessId, string $externalId): void
    {
        if ($externalId === '') {
            return;
        }

        // Driver `sync` chạy job ngay trong request -> không còn là "nền" nữa
        // và làm chậm API tạo post. Khi đó để worker `social-feed:generate-slugs`
        // (cron) quét theo slug_generated = false.
        $connection = (string) $this->app->config('queue.default', 'database');
        if ($connection === 'sync' || $connection === 'null') {
            return;
        }

        try {
            $queue = $this->app->make(QueueManager::class);
            if (!$queue instanceof QueueManager) {
                return;
            }

            $job = new GenerateSocialPostSlug($businessId, $externalId);
            $queue->push($job->onQueue(self::QUEUE), self::QUEUE);
        } catch (\Throwable) {
            // Queue không khả dụng — worker `social-feed:generate-slugs` sẽ quét lại.
        }
    }
}
