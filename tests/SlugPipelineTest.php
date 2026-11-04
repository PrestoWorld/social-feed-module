<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Tests;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;
use PrestoWorld\Modules\SocialFeed\Contracts\GroqClientInterface;
use PrestoWorld\Modules\SocialFeed\Services\SlugComposer;
use PrestoWorld\Modules\SocialFeed\Services\SlugProcessor;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\SocialPostRepository;

/**
 * Pipeline slug trên SQLite in-memory, chạy đúng migration 001/002/003.
 *
 * Luồng kiểm chứng:
 *   post tạo  -> slug = UUID, slug_generated = false, retries = 0
 *   worker    -> pendingSlug() lấy slug_generated = false, order by retries ASC
 *   Groq ok   -> slug = compose(...), slug_generated = true, hết việc
 *   Groq fail -> slug giữ nguyên, slug_generated = false, retries += 1
 */
class SlugPipelineTest extends TestCase
{
    private const PREFIX = 'bow_';
    private const BUSINESS = 'shop-1';

    private DatabaseInterface $db;
    private SocialPostRepository $posts;

    protected function setUp(): void
    {
        $manager = new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => ['default' => ['connection' => 'default']],
            'connections' => ['default' => new SQLiteDriverConfig(connection: new MemoryConnectionConfig())],
        ]));
        $this->db = $manager->database();

        foreach (['001_social_feed_tables.php', '002_social_feed_uuid.php', '003_social_feed_slug.php'] as $file) {
            $migration = require dirname(__DIR__) . '/Migrations/' . $file;
            $migration->up($this->db, self::PREFIX);
        }

        $this->posts = new SocialPostRepository($this->db, self::PREFIX);
    }

    public function testNewPostStartsWithUuidSlugAndPendingFlag(): void
    {
        $saved = $this->posts->save(self::BUSINESS, [
            'external_id' => 'post-new-1',
            'content' => 'Sách mới về cửa hàng',
        ]);

        self::assertSame($saved['id'], $saved['slug'], 'slug mặc định chính là UUID của post');
        self::assertFalse((bool) $saved['slug_generated']);
        self::assertSame(0, (int) $saved['retries']);
        self::assertLessThanOrEqual(60, strlen((string) $saved['slug']));
    }

    public function testPendingSlugOnlyReturnsUnflaggedPostsOrderedByRetriesAsc(): void
    {
        $this->posts->save(self::BUSINESS, ['external_id' => 'p-0', 'content' => 'zero retry']);
        $this->posts->save(self::BUSINESS, ['external_id' => 'p-1', 'content' => 'one retry']);
        $this->posts->save(self::BUSINESS, ['external_id' => 'p-3', 'content' => 'three retries']);
        $this->posts->save(self::BUSINESS, ['external_id' => 'p-done', 'content' => 'already generated']);

        // p-3: 3 lần fail, p-1: 1 lần fail, p-0: chưa fail -> thứ tự 0, 1, 3.
        foreach (['p-1' => 1, 'p-3' => 3] as $externalId => $times) {
            for ($i = 0; $i < $times; $i++) {
                $row = $this->findRow($externalId);
                $this->posts->markSlugFailed((string) $row['id']);
            }
        }

        $done = $this->findRow('p-done');
        $this->posts->markSlugGenerated((string) $done['id'], 'da-co-slug-shop-1-abc123');

        $pending = $this->posts->pendingSlug(10);
        $order = array_map(static fn (array $row): string => (string) $row['external_id'], $pending);

        self::assertSame(['p-0', 'p-1', 'p-3'], $order, 'retries ASC, post đã sinh xong không còn trong hàng đợi');
    }

    public function testMarkSlugGeneratedSetsSlugAndClearsPending(): void
    {
        $this->createPost('p-ok');
        $row = $this->findRow('p-ok');

        self::assertTrue($this->posts->markSlugGenerated((string) $row['id'], 'sach-moi-shop-1-abc123'));

        $updated = $this->findRow('p-ok');
        self::assertSame('sach-moi-shop-1-abc123', $updated['slug']);
        self::assertSame(1, (int) $updated['slug_generated']);
        self::assertSame([], $this->posts->pendingSlug(10));
    }

    public function testMarkSlugFailedKeepsSlugAndIncrementsRetries(): void
    {
        $this->createPost('p-fail');
        $row = $this->findRow('p-fail');
        $uuidSlug = (string) $row['slug'];

        $this->posts->markSlugFailed((string) $row['id']);
        $this->posts->markSlugFailed((string) $row['id']);

        $updated = $this->findRow('p-fail');
        self::assertSame($uuidSlug, $updated['slug'], 'slug không bị xoá khi sinh lỗi');
        self::assertSame(0, (int) $updated['slug_generated']);
        self::assertSame(2, (int) $updated['retries']);
        self::assertCount(1, $this->posts->pendingSlug(10));
    }

    public function testProcessorGeneratesSlugOnGroqSuccess(): void
    {
        $this->createPost('p-success');

        $groq = new FakeGroqClient(true, [
            'ok' => true,
            'text' => 'sach-moi-doc-nhat',
            'retry_after' => null,
            'error' => null,
        ]);
        $result = (new SlugProcessor($this->posts, $groq, new SlugComposer()))->processPending(10);

        $row = $this->findRow('p-success');

        self::assertSame(1, $result['generated']);
        self::assertSame(0, $result['failed']);
        self::assertFalse($result['has_more']);
        self::assertSame(1, (int) $row['slug_generated']);
        self::assertSame(0, (int) $row['retries']);
        self::assertStringEndsWith('-shop-1-' . substr(md5(self::BUSINESS . '|p-success|' . $row['id']), 0, 6), (string) $row['slug']);
        self::assertLessThanOrEqual(60, strlen((string) $row['slug']));
        self::assertCount(1, $groq->calls, 'Groq được gọi đúng một lần');
    }

    public function testProcessorKeepsUuidAndCountsRetriesWhenGroqFails(): void
    {
        $this->createPost('p-groq-fail');
        $uuidSlug = (string) $this->findRow('p-groq-fail')['slug'];

        $groq = new FakeGroqClient(true, [
            'ok' => false,
            'text' => null,
            'retry_after' => 30,
            'error' => 'groq: HTTP 429 rate_limit_exceeded',
        ]);

        $result = (new SlugProcessor($this->posts, $groq, new SlugComposer()))->processPending(10);

        $row = $this->findRow('p-groq-fail');

        self::assertSame(0, $result['generated']);
        self::assertSame(1, $result['failed']);
        self::assertSame(30, $result['retry_after'], 'trả retry-after về cho caller tôn trọng rate limit');
        self::assertSame($uuidSlug, $row['slug']);
        self::assertSame(0, (int) $row['slug_generated']);
        self::assertSame(1, (int) $row['retries']);
    }

    public function testProcessorStopsBatchAfterRateLimit(): void
    {
        $this->createPost('p-rl-1');
        $this->createPost('p-rl-2');

        $groq = new FakeGroqClient(true, [
            'ok' => false,
            'text' => null,
            'retry_after' => 45,
            'error' => 'groq: HTTP 429',
        ]);

        $result = (new SlugProcessor($this->posts, $groq, new SlugComposer()))->processPending(10);

        self::assertSame(1, $result['failed'], 'dừng ngay sau lần 429 đầu tiên');
        self::assertCount(1, $groq->calls);
        self::assertSame(45, $result['retry_after']);
    }

    public function testProcessorSkipsApiWhenKeyMissing(): void
    {
        $this->createPost('p-no-key');
        $uuidSlug = (string) $this->findRow('p-no-key')['slug'];

        $groq = new FakeGroqClient(false, []);
        $processor = new SlugProcessor($this->posts, $groq, new SlugComposer());

        self::assertFalse($processor->isConfigured());

        $result = $processor->processPending(10);
        $row = $this->findRow('p-no-key');

        self::assertCount(0, $groq->calls, 'không gọi API khi thiếu GROQ_API_KEY');
        self::assertSame(0, $result['generated']);
        self::assertSame($uuidSlug, $row['slug']);
        self::assertSame(1, (int) $row['retries'], 'vẫn count up retries để không quét lại vô hạn');
    }

    public function testProcessorIsIdempotentForAlreadyGeneratedPosts(): void
    {
        $this->createPost('p-idem');
        $row = $this->findRow('p-idem');
        $this->posts->markSlugGenerated((string) $row['id'], 'slug-da-sinh-shop-1-abc123');

        $groq = new FakeGroqClient(true, ['ok' => true, 'text' => 'khong-phai-slug', 'retry_after' => null, 'error' => null]);
        $result = (new SlugProcessor($this->posts, $groq, new SlugComposer()))->processPending(10);

        self::assertSame(0, $result['generated']);
        self::assertCount(0, $groq->calls);
        self::assertSame('slug-da-sinh-shop-1-abc123', $this->findRow('p-idem')['slug']);
    }

    private function createPost(string $externalId): string
    {
        $saved = $this->posts->save(self::BUSINESS, [
            'external_id' => $externalId,
            'content' => 'Nội dung bài viết ' . $externalId,
        ]);

        return (string) $saved['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findRow(string $externalId): array
    {
        $row = $this->db->select('*')
            ->from(self::PREFIX . 'social_posts')
            ->where('external_id', $externalId)
            ->run()
            ->fetch();

        self::assertIsArray($row, "không tìm thấy post {$externalId}");

        return $row;
    }
}

/**
 * Groq giả — không HTTP, trả đúng shape mà GroqClient::complete() trả về.
 */
final class FakeGroqClient implements GroqClientInterface
{
    /** @var list<list<array{role: string, content: string}>> */
    public array $calls = [];

    public function __construct(
        private readonly bool $configured,
        private readonly array $response,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function complete(array $messages): array
    {
        $this->calls[] = $messages;

        return $this->response;
    }
}
