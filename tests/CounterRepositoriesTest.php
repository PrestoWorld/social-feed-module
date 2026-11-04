<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Tests;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\CollectionRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\SocialPostRepository;

/**
 * Regression cho các counter: trước đây chúng dùng
 * `new \Cycle\Database\Expression\Raw(...)` — class đó KHÔNG tồn tại trong
 * cycle/database 2.23 nên incrementView/Click/Play và addItem/removeItem
 * fatal "Class ... Expression\Raw not found" ngay khi được gọi.
 *
 * Bây giờ tất cả đều là SQL thô portable, nên test chạy trên sqlite in-memory
 * (mysql / pgsql dùng đúng cùng chuỗi SQL).
 */
class CounterRepositoriesTest extends TestCase
{
    private const PREFIX = 'bow_';
    private const BUSINESS = 'shop-1';

    private DatabaseInterface $db;
    private SocialPostRepository $posts;
    private CollectionRepository $collections;

    protected function setUp(): void
    {
        $manager = new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => ['default' => ['connection' => 'default']],
            'connections' => ['default' => new SQLiteDriverConfig(connection: new MemoryConnectionConfig())],
        ]));
        $this->db = $manager->database();

        foreach (['001_social_feed_tables.php', '002_social_feed_uuid.php', '003_social_feed_slug.php'] as $file) {
            (require dirname(__DIR__) . '/Migrations/' . $file)->up($this->db, self::PREFIX);
        }

        $this->posts = new SocialPostRepository($this->db, self::PREFIX);
        $this->collections = new CollectionRepository($this->db, self::PREFIX);
    }

    public function testIncrementViewClickPlayDoNotFatalAndBumpCounters(): void
    {
        $this->posts->save(self::BUSINESS, ['external_id' => 'c-1', 'content' => 'bai viet']);

        $this->posts->incrementView('c-1');
        $this->posts->incrementView('c-1');
        $this->posts->incrementClick('c-1');
        $this->posts->incrementPlay('c-1');

        $row = $this->postRow('c-1');

        self::assertSame(2, (int) $row['view_count']);
        self::assertSame(1, (int) $row['click_count']);
        self::assertSame(1, (int) $row['play_count']);
        self::assertGreaterThan(0, (int) $row['updated_at'], 'counter bump cũng cập nhật updated_at');
    }

    public function testIncrementOnUnknownPostIsSilentlyIgnored(): void
    {
        $this->posts->incrementView('khong-ton-tai');
        $this->posts->incrementClick('khong-ton-tai');
        $this->posts->incrementPlay('khong-ton-tai');

        self::assertSame([], $this->db->select('id')->from(self::PREFIX . 'social_posts')->run()->fetchAll());
    }

    public function testCollectionItemCounterFollowsAddAndRemove(): void
    {
        $collection = $this->collections->save(self::BUSINESS, ['external_id' => 'col-1', 'name' => 'Yêu thích']);
        $id = (string) $collection['external_id'];

        self::assertTrue($this->collections->addItem($id, 'post-a'));
        self::assertSame(1, $this->itemCount($id), 'addItem tăng item_count');

        // Thêm trùng -> không tăng thêm (đã có guard exists).
        self::assertTrue($this->collections->addItem($id, 'post-a'));
        self::assertSame(1, $this->itemCount($id), 'addItem idempotent');

        self::assertTrue($this->collections->addItem($id, 'post-b'));
        self::assertSame(2, $this->itemCount($id));

        self::assertTrue($this->collections->removeItem($id, 'post-a'));
        self::assertSame(1, $this->itemCount($id), 'removeItem giảm item_count');

        // Xoá lần nữa khi item đã hết -> false và không đụng tới item_count.
        self::assertFalse($this->collections->removeItem($id, 'post-a'));
        self::assertSame(1, $this->itemCount($id));
    }

    public function testCollectionItemCounterNeverGoesBelowZero(): void
    {
        $collection = $this->collections->save(self::BUSINESS, ['external_id' => 'col-2', 'name' => 'Yêu thích']);
        $id = (string) $collection['external_id'];

        $this->collections->addItem($id, 'post-a');

        // Ép item_count về 0 trong khi row item vẫn còn, để rơi vào nhánh
        // CASE WHEN ... ELSE 0 (thay cho GREATEST trước đây).
        $this->db->execute(
            'UPDATE ' . self::PREFIX . 'social_collections SET item_count = 0 WHERE external_id = ?',
            [$id],
        );

        self::assertTrue($this->collections->removeItem($id, 'post-a'));
        self::assertSame(0, $this->itemCount($id), 'item_count không được xuống âm');
    }

    /**
     * @return array<string, mixed>
     */
    private function postRow(string $externalId): array
    {
        $row = $this->db->select('*')->from(self::PREFIX . 'social_posts')
            ->where('external_id', $externalId)->run()->fetch();
        self::assertIsArray($row, "không tìm thấy post {$externalId}");

        return $row;
    }

    private function itemCount(string $collectionExternalId): int
    {
        $row = $this->db->select('item_count')->from(self::PREFIX . 'social_collections')
            ->where('external_id', $collectionExternalId)->run()->fetch();
        self::assertIsArray($row, "không tìm thấy collection {$collectionExternalId}");

        return (int) $row['item_count'];
    }
}
