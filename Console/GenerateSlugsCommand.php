<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Console;

use PrestoWorld\Modules\SocialFeed\Services\SlugProcessor;
use Witals\Framework\Console\Command;

/**
 * Worker quét post chờ sinh slug AI và gọi Groq.
 *
 * Lấy việc đúng theo spec: `slug_generated = false` ORDER BY `retries` ASC —
 * post thất bại nhiều lần bị đẩy ra sau, post mới được xử lý sớm.
 *
 * Chạy định kỳ (cron / scheduler):
 *   php witals social-feed:generate-slugs --limit=20
 */
final class GenerateSlugsCommand extends Command
{
    protected string $name = 'social-feed:generate-slugs';
    protected string $description = 'Generate AI slugs (Groq) for social feed posts that are still slug_generated = false';
    protected array $options = [
        '--limit=MAX' => 'Number of posts to process per run (default: 20)',
        '--once' => 'Stop after the first batch even when more posts are pending (default: off)',
    ];

    public function handle(array $args): int
    {
        $processor = $this->app->make(SlugProcessor::class);
        if (!$processor instanceof SlugProcessor) {
            $this->error('SlugProcessor is not bound in the container.');

            return 1;
        }

        if (!$processor->isConfigured()) {
            $this->error('GROQ_API_KEY is not configured — slug generation is disabled.');

            return 1;
        }

        $rawLimit = $this->getOption($args, 'limit', 20);
        $limit = is_numeric($rawLimit) ? max(1, (int) $rawLimit) : 20;
        $once = (bool) $this->getOption($args, 'once', false);

        $generated = 0;
        $failed = 0;
        $batches = 0;
        $retryAfter = null;

        do {
            $result = $processor->processPending($limit);

            $generated += $result['generated'];
            $failed += $result['failed'];
            $batches++;

            // Không có tiến bộ (không sinh được, không đánh lỗi được) -> thoát,
            // tránh quét lại cùng một tập bản ghi vô hạn.
            if ($result['generated'] === 0 && $result['failed'] === 0) {
                break;
            }

            $retryAfter = $result['retry_after'];

            // 429 rate limit -> dừng, tôn trọng retry-after của Groq.
            if ($retryAfter !== null) {
                $this->warn("Rate limited by Groq — retry after {$retryAfter}s.");
                break;
            }

            if ($once) {
                break;
            }
        } while ($result['has_more'] && $batches < 100);

        $this->info("Generated {$generated} slug(s), {$failed} failed after {$batches} batch(es).");

        if ($failed > 0) {
            $this->line('Failed posts keep slug_generated = false and will be retried on the next run.');
        }

        return $failed > 0 ? 1 : 0;
    }
}
