<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Database\MigrationInterface;

/**
 * Thêm cột slug + cờ sinh slug bằng AI vào social_posts.
 *
 * - `slug`            VARCHAR(60) NOT NULL DEFAULT '' — slug URL. Khi tạo post,
 *                    mặc định điền = UUID PK, worker sẽ ghi đè bằng slug do Groq sinh.
 * - `slug_generated`  BOOLEAN NOT NULL DEFAULT FALSE — cờ báo slug đã do AI sinh xong.
 *                    FALSE nghĩa là post vẫn chờ sinh (hoặc sinh lỗi và sẽ thử lại).
 * - `retries`         INTEGER NOT NULL DEFAULT 0 — số lần thử sinh slug đã thất bại.
 *                    Worker lấy việc theo `slug_generated = false` order by `retries` ASC,
 *                    nên post nào đã thất bại nhiều lần sẽ bị đẩy ra sau.
 *
 * Index (slug_generated, retries) phục vụ đúng câu lệnh lấy việc của worker.
 *
 * Hỗ trợ mysql / pgsql / sqlite. Ba cột đều thêm được bằng ALTER ADD COLUMN
 * (không cần rebuild bảng như migration 002).
 *
 * Lưu ý quote identifier: mysql dùng backtick (ANSI_QUOTES thường tắt),
 * pgsql / sqlite dùng dấu nháy kép.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $driver = strtolower($db->getType());
        $table = $prefix . 'social_posts';

        if (!$this->tableExists($db, $table)) {
            return;
        }

        $q = fn (string $identifier): string => $this->quote($driver, $identifier);
        $t = $q($table);

        if (!$this->tableHasColumn($db, $table, 'slug')) {
            $db->execute("ALTER TABLE {$t} ADD COLUMN {$q('slug')} VARCHAR(60) NOT NULL DEFAULT ''");
        }

        if (!$this->tableHasColumn($db, $table, 'slug_generated')) {
            $db->execute("ALTER TABLE {$t} ADD COLUMN {$q('slug_generated')} BOOLEAN NOT NULL DEFAULT FALSE");
        }

        if (!$this->tableHasColumn($db, $table, 'retries')) {
            $db->execute("ALTER TABLE {$t} ADD COLUMN {$q('retries')} INTEGER NOT NULL DEFAULT 0");
        }

        $slugIndex = $table . '_slug_generated_retries';
        if (!$this->indexExists($db, $driver, $slugIndex)) {
            $db->execute("CREATE INDEX {$q($slugIndex)} ON {$t} ({$q('slug_generated')}, {$q('retries')})");
        }

        $businessIndex = $table . '_business_id_slug';
        if (!$this->indexExists($db, $driver, $businessIndex)) {
            $db->execute("CREATE INDEX {$q($businessIndex)} ON {$t} ({$q('business_id')}, {$q('slug')})");
        }

        // Bản ghi cũ: slug tạm = UUID PK, slug_generated = false -> đưa vào hàng đợi sinh slug AI.
        $db->execute("UPDATE {$t} SET {$q('slug')} = {$q('id')} WHERE {$q('slug')} = ''");
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $driver = strtolower($db->getType());
        $table = $prefix . 'social_posts';

        if (!$this->tableExists($db, $table)) {
            return;
        }

        $q = fn (string $identifier): string => $this->quote($driver, $identifier);
        $t = $q($table);

        foreach ([$table . '_slug_generated_retries', $table . '_business_id_slug'] as $index) {
            if (!$this->indexExists($db, $driver, $index)) {
                continue;
            }

            // MySQL: DROP INDEX <name> ON <table>; pgsql / sqlite: DROP INDEX <name>.
            $db->execute($driver === 'mysql'
                ? "DROP INDEX {$q($index)} ON {$t}"
                : "DROP INDEX {$q($index)}");
        }

        // SQLite cần 3.35+ cho DROP COLUMN (PHP mặc định bundle bản mới hơn).
        foreach (['slug_generated', 'retries', 'slug'] as $column) {
            if ($this->tableHasColumn($db, $table, $column)) {
                $db->execute("ALTER TABLE {$t} DROP COLUMN {$q($column)}");
            }
        }
    }

    private function quote(string $driver, string $identifier): string
    {
        return $driver === 'mysql' ? "`{$identifier}`" : "\"{$identifier}\"";
    }

    /**
     * Kiểm tra bảng tồn tại. Với sqlite dùng PRAGMA (hasTable() giữ read lock).
     */
    private function tableExists(DatabaseInterface $db, string $table): bool
    {
        if (strtolower($db->getType()) === 'sqlite') {
            return $this->sqliteColumns($db, $table) !== [];
        }

        return $db->hasTable($table);
    }

    private function tableHasColumn(DatabaseInterface $db, string $table, string $column): bool
    {
        return in_array($column, $this->readColumns($db, $table), true);
    }

    /**
     * @return list<string> tên cột
     */
    private function readColumns(DatabaseInterface $db, string $table): array
    {
        if (strtolower($db->getType()) === 'sqlite') {
            return array_map(static fn (array $col): string => (string) $col['name'], $this->sqliteColumns($db, $table));
        }

        $schema = $db->table($table)->getSchema();
        // getSchema() trả driver table (vd. MySQLTable) extends Schema\AbstractTable,
        // không phải Cycle\Database\Table.
        if (!$schema instanceof \Cycle\Database\Schema\AbstractTable) {
            return [];
        }

        $columns = [];
        foreach ($schema->getColumns() as $column) {
            $columns[] = $column->getName();
        }

        return $columns;
    }

    private function indexExists(DatabaseInterface $db, string $driver, string $index): bool
    {
        try {
            if ($driver === 'sqlite') {
                $row = $db->query('SELECT name FROM sqlite_master WHERE type = ? AND name = ?', ['index', $index])->fetch();
            } elseif ($driver === 'pgsql') {
                $row = $db->query('SELECT 1 FROM pg_class WHERE relname = ?', [$index])->fetch();
            } else {
                $row = $db->query(
                    'SELECT 1 FROM information_schema.statistics WHERE index_name = ? AND table_schema = DATABASE()',
                    [$index],
                )->fetch();
            }
        } catch (\Throwable) {
            return false;
        }

        return is_array($row) && $row !== [];
    }

    /**
     * @return list<array{name: string}>
     */
    private function sqliteColumns(DatabaseInterface $db, string $table): array
    {
        $stmt = $db->query('PRAGMA table_info("' . $table . '")');
        $rows = $stmt->fetchAll();
        $stmt->close();

        $columns = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['name'])) {
                $columns[] = ['name' => (string) $row['name']];
            }
        }

        return $columns;
    }
};
