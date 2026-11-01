<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Core\Uuid;
use PrestoWorld\Database\MigrationInterface;

/**
 * Chuyển PK của các bảng Social Feed từ số tự tăng (integer) sang UUID string.
 *
 * - Cột `id` của social_posts / social_comments / social_reactions /
 *   social_collections / social_collection_items: BIGINT AUTO_INCREMENT -> VARCHAR(36) UUID.
 * - Cột `social_comments.parent_id`: BIGINT -> VARCHAR(36) (UUID của comment cha).
 * - Bản ghi cũ được backfill bằng UUID v5 deterministic (namespace + bảng + id cũ),
 *   nên chạy lại hay tính lại ở nơi khác luôn cho cùng một giá trị.
 *
 * Hỗ trợ mysql / pgsql / sqlite. SQLite không có ALTER COLUMN nên phải rebuild bảng.
 */
return new class implements MigrationInterface {
    /** @var list<string> */
    private const UUID_PK_TABLES = [
        'social_posts',
        'social_comments',
        'social_reactions',
        'social_collections',
        'social_collection_items',
    ];

    /**
     * Index của 001_social_feed_tables — dùng lại khi phải rebuild bảng (sqlite / down).
     *
     * @var array<string, list<array{columns: list<string>, unique?: bool}>>
     */
    private const INDEXES = [
        'social_posts' => [
            ['columns' => ['business_id', 'external_id'], 'unique' => true],
            ['columns' => ['business_id', 'user_id']],
            ['columns' => ['business_id', 'type']],
            ['columns' => ['business_id', 'status']],
            ['columns' => ['created_at']],
        ],
        'social_comments' => [
            ['columns' => ['business_id', 'external_id'], 'unique' => true],
            ['columns' => ['business_id', 'post_external_id']],
            ['columns' => ['parent_id']],
        ],
        'social_reactions' => [
            ['columns' => ['business_id', 'external_id'], 'unique' => true],
            ['columns' => ['business_id', 'user_id', 'post_external_id'], 'unique' => true],
            ['columns' => ['post_external_id', 'type']],
        ],
        'social_collections' => [
            ['columns' => ['business_id', 'external_id'], 'unique' => true],
            ['columns' => ['business_id', 'is_default']],
        ],
        'social_collection_items' => [
            ['columns' => ['collection_id', 'post_external_id'], 'unique' => true],
        ],
    ];

    /**
     * Cột của 001_social_feed_tables — dùng khi down() rebuild về integer PK.
     *
     * @var array<string, string>
     */
    private const LEGACY_SCHEMA = [
        'social_posts' => '"id" BIGINT NOT NULL PRIMARY KEY, "business_id" VARCHAR(64) NOT NULL, "external_id" VARCHAR(64) NOT NULL, "user_id" VARCHAR(64) NOT NULL DEFAULT \'\', "user_name" VARCHAR(255) NOT NULL DEFAULT \'\', "user_avatar" VARCHAR(500) NULL, "type" VARCHAR(32) NOT NULL DEFAULT \'status\', "content" TEXT NOT NULL, "images" TEXT NULL, "gallery" TEXT NULL, "link_url" VARCHAR(500) NULL, "link_title" VARCHAR(255) NULL, "link_image" VARCHAR(500) NULL, "media_url" VARCHAR(500) NULL, "embed_url" VARCHAR(500) NULL, "thumbnail_url" VARCHAR(500) NULL, "platform" VARCHAR(32) NULL, "duration" BIGINT NOT NULL DEFAULT 0, "status" VARCHAR(32) NOT NULL DEFAULT \'published\', "comment_count" BIGINT NOT NULL DEFAULT 0, "reaction_count" BIGINT NOT NULL DEFAULT 0, "share_count" BIGINT NOT NULL DEFAULT 0, "save_count" BIGINT NOT NULL DEFAULT 0, "view_count" BIGINT NOT NULL DEFAULT 0, "click_count" BIGINT NOT NULL DEFAULT 0, "play_count" BIGINT NOT NULL DEFAULT 0, "like_count" BIGINT NOT NULL DEFAULT 0, "payload" TEXT NULL, "created_at" BIGINT NOT NULL DEFAULT 0, "updated_at" BIGINT NOT NULL DEFAULT 0',
        'social_comments' => '"id" BIGINT NOT NULL PRIMARY KEY, "business_id" VARCHAR(64) NOT NULL, "external_id" VARCHAR(64) NOT NULL, "post_external_id" VARCHAR(64) NOT NULL, "user_id" VARCHAR(64) NOT NULL DEFAULT \'\', "user_name" VARCHAR(255) NOT NULL DEFAULT \'\', "user_avatar" VARCHAR(500) NULL, "content" TEXT NOT NULL, "parent_id" BIGINT NOT NULL DEFAULT 0, "status" VARCHAR(32) NOT NULL DEFAULT \'published\', "payload" TEXT NULL, "created_at" BIGINT NOT NULL DEFAULT 0, "updated_at" BIGINT NOT NULL DEFAULT 0',
        'social_reactions' => '"id" BIGINT NOT NULL PRIMARY KEY, "external_id" VARCHAR(64) NOT NULL, "business_id" VARCHAR(64) NOT NULL, "user_id" VARCHAR(64) NOT NULL DEFAULT \'\', "post_external_id" VARCHAR(64) NOT NULL, "type" VARCHAR(32) NOT NULL DEFAULT \'like\', "created_at" BIGINT NOT NULL DEFAULT 0, "updated_at" BIGINT NOT NULL DEFAULT 0',
        'social_collections' => '"id" BIGINT NOT NULL PRIMARY KEY, "business_id" VARCHAR(64) NOT NULL, "external_id" VARCHAR(64) NOT NULL, "name" VARCHAR(255) NOT NULL DEFAULT \'Yêu thích\', "is_default" BOOLEAN NOT NULL DEFAULT FALSE, "item_count" BIGINT NOT NULL DEFAULT 0, "payload" TEXT NULL, "created_at" BIGINT NOT NULL DEFAULT 0, "updated_at" BIGINT NOT NULL DEFAULT 0',
        'social_collection_items' => '"id" BIGINT NOT NULL PRIMARY KEY, "collection_id" VARCHAR(64) NOT NULL, "post_external_id" VARCHAR(64) NOT NULL, "created_at" BIGINT NOT NULL DEFAULT 0',
    ];

    public function up(DatabaseInterface $db, string $prefix): void
    {
        $driver = $db->getType();

        foreach (self::UUID_PK_TABLES as $name) {
            $table = $prefix . $name;
            if (!$db->hasTable($table)) {
                continue;
            }

            if ($name === 'social_posts') {
                $this->addBookReferenceColumn($db, $table, $driver);
            }

            if ($this->idIsUuid($db, $table)) {
                continue;
            }

            $idMap = $driver === 'sqlite'
                ? $this->rebuildSqlite($db, $table)
                : $this->convertPrimaryKey($db, $table, $driver);

            if ($name === 'social_comments') {
                $this->convertParentId($db, $table, $driver, $idMap);
            }
        }
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        foreach (self::UUID_PK_TABLES as $name) {
            $table = $prefix . $name;
            if (!$db->hasTable($table)) {
                continue;
            }

            $this->rebuildToIntPrimaryKey($db, $table, $name);
        }
    }

    /**
     * Cột `id` đã là string (UUID) chưa?
     */
    private function idIsUuid(DatabaseInterface $db, string $table): bool
    {
        $schema = $db->table($table)->getSchema();
        if (!$schema instanceof \Cycle\Database\Table) {
            return false;
        }

        foreach ($schema->getColumns() as $column) {
            if ($column->getName() === 'id') {
                return $column->getAbstractType() === 'string';
            }
        }

        return false;
    }

    /**
     * Thêm cột nullable `book_id` vào social_posts: reference tới sách
     * (bow_product_masters.id hoặc external code). NULL với post thường;
     * điền khi post/quote gắn với một cuốn sách cụ thể.
     */
    private function addBookReferenceColumn(DatabaseInterface $db, string $table, string $driver): void
    {
        if ($this->hasColumn($db, $table, 'book_id')) {
            return;
        }

        $quoted = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";
        $db->execute("ALTER TABLE {$quoted} ADD COLUMN \"book_id\" VARCHAR(64) NULL");
        $db->execute("CREATE INDEX \"{$table}_book_id\" ON {$quoted} (\"book_id\")");
    }

    private function hasColumn(DatabaseInterface $db, string $table, string $column): bool
    {
        $schema = $db->table($table)->getSchema();
        if (!$schema instanceof \Cycle\Database\Table) {
            return false;
        }

        foreach ($schema->getColumns() as $existing) {
            if ($existing->getName() === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * mysql / pgsql: đổi PK `id` integer -> VARCHAR(36) UUID.
     *
     * @return array<string, string> map id cũ -> UUID mới
     */
    private function convertPrimaryKey(DatabaseInterface $db, string $table, string $driver): array
    {
        $quoted = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";
        $idCol = $driver === 'mysql' ? '`id`' : '"id"';
        $tmpCol = $driver === 'mysql' ? '`id_uuid`' : '"id_uuid"';

        $db->execute("ALTER TABLE {$quoted} ADD COLUMN {$tmpCol} VARCHAR(36) NULL");
        $map = $this->backfillUuid($db, $table, $driver, $tmpCol);

        if ($driver === 'mysql') {
            // MySQL không cho DROP PK khi cột còn AUTO_INCREMENT.
            $db->execute("ALTER TABLE {$quoted} MODIFY {$idCol} BIGINT NOT NULL");
            $db->execute("ALTER TABLE {$quoted} DROP PRIMARY KEY");
            $db->execute("ALTER TABLE {$quoted} DROP COLUMN {$idCol}");
            $db->execute("ALTER TABLE {$quoted} CHANGE {$tmpCol} {$idCol} VARCHAR(36) NOT NULL");
            $db->execute("ALTER TABLE {$quoted} ADD PRIMARY KEY ({$idCol})");

            return $map;
        }

        // PostgreSQL: tên PK constraint lấy động cho chắc.
        $row = $db->query(
            "SELECT conname FROM pg_constraint WHERE conrelid = to_regclass(?) AND contype = 'p'",
            [$table],
        )->fetch();
        if (is_array($row) && !empty($row['conname'])) {
            $db->execute("ALTER TABLE {$quoted} DROP CONSTRAINT \"" . $row['conname'] . "\"");
        }

        $db->execute("ALTER TABLE {$quoted} DROP COLUMN {$idCol}");
        $db->execute("ALTER TABLE {$quoted} RENAME COLUMN {$tmpCol} TO {$idCol}");
        $db->execute("ALTER TABLE {$quoted} ALTER COLUMN {$idCol} SET NOT NULL");
        $db->execute("ALTER TABLE {$quoted} ADD PRIMARY KEY ({$idCol})");

        return $map;
    }

    /**
     * Gán UUID v5 deterministic cho từng dòng đang có id integer.
     *
     * @return array<string, string>
     */
    private function backfillUuid(DatabaseInterface $db, string $table, string $driver, string $tmpCol): array
    {
        $quoted = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";
        $idCol = $driver === 'mysql' ? '`id`' : '"id"';

        $rows = $db->query("SELECT {$idCol} FROM {$quoted}")->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $legacy = (string) $row['id'];
            $uuid = Uuid::v5(Uuid::NS_URL, $table . ':' . $legacy);
            $db->execute("UPDATE {$quoted} SET {$tmpCol} = ? WHERE {$idCol} = ?", [$uuid, $legacy]);
            $map[$legacy] = $uuid;
        }

        return $map;
    }

    /**
     * social_comments.parent_id: BIGINT -> VARCHAR(36) UUID của comment cha.
     * SQLite đã remap luôn trong rebuild, không đi qua đây.
     *
     * @param array<string, string> $idMap
     */
    private function convertParentId(DatabaseInterface $db, string $table, string $driver, array $idMap): void
    {
        if ($driver === 'mysql') {
            $db->execute("ALTER TABLE `{$table}` MODIFY `parent_id` VARCHAR(36) NULL DEFAULT NULL");
            $idSql = '`id`';
            $parentSql = '`parent_id`';
            $quoted = "`{$table}`";
        } else {
            $db->execute("ALTER TABLE \"{$table}\" ALTER COLUMN \"parent_id\" DROP DEFAULT");
            $db->execute("ALTER TABLE \"{$table}\" ALTER COLUMN \"parent_id\" TYPE VARCHAR(36) USING NULLIF(parent_id::text, '0')");
            $idSql = '"id"';
            $parentSql = '"parent_id"';
            $quoted = "\"{$table}\"";
        }

        $rows = $db->query("SELECT {$idSql}, {$parentSql} FROM {$quoted}")->fetchAll();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $parent = (string) ($row['parent_id'] ?? '');
            if ($parent === '' || $parent === '0') {
                $db->execute("UPDATE {$quoted} SET {$parentSql} = NULL WHERE {$idSql} = ?", [(string) $row['id']]);
                continue;
            }

            if (isset($idMap[$parent])) {
                $db->execute("UPDATE {$quoted} SET {$parentSql} = ? WHERE {$idSql} = ?", [$idMap[$parent], (string) $row['id']]);
            }
        }
    }

    /**
     * SQLite: không có ALTER COLUMN -> rebuild bảng với id TEXT PRIMARY KEY.
     *
     * @return array<string, string>
     */
    private function rebuildSqlite(DatabaseInterface $db, string $table): array
    {
        $columns = $this->sqliteColumns($db, $table);
        $newTable = $table . '__uuid';

        $defs = [];
        foreach ($columns as $col) {
            if ($col['name'] === 'id') {
                $defs[] = '"id" TEXT NOT NULL PRIMARY KEY';
                continue;
            }

            $type = strtoupper($col['type']);
            if ($col['name'] === 'parent_id') {
                $type = 'TEXT';
            }

            $def = '"' . $col['name'] . '" ' . $type;
            if ($col['notnull']) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt'] !== null) {
                $def .= ' DEFAULT ' . $col['dflt'];
            }
            $defs[] = $def;
        }

        $db->execute('CREATE TABLE "' . $newTable . '" (' . implode(', ', $defs) . ')');

        $legacyRows = $db->query('SELECT "id" FROM "' . $table . '"')->fetchAll();
        $map = [];
        foreach ($legacyRows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $legacy = (string) $row['id'];
                $map[$legacy] = Uuid::v5(Uuid::NS_URL, $table . ':' . $legacy);
            }
        }

        $rows = $db->query('SELECT * FROM "' . $table . '"')->fetchAll();
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $legacy = (string) $row['id'];
            $row['id'] = $map[$legacy] ?? Uuid::v5(Uuid::NS_URL, $table . ':' . $legacy);

            if (array_key_exists('parent_id', $row)) {
                $parent = (string) ($row['parent_id'] ?? '');
                $row['parent_id'] = ($parent === '' || $parent === '0') ? null : ($map[$parent] ?? null);
            }

            $cols = array_map(static fn (string $c): string => '"' . $c . '"', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $db->execute(
                'INSERT INTO "' . $newTable . '" (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')',
                array_values($row),
            );
        }

        $db->execute('DROP TABLE "' . $table . '"');
        $db->execute('ALTER TABLE "' . $newTable . '" RENAME TO "' . $table . '"');
        $this->recreateIndexes($db, $table);

        return $map;
    }

    /**
     * down(): rebuild bảng về integer PK (best effort — UUID không ngược được về id cũ,
     * nên id mới đánh lại tuần tự và parent_id reset về 0).
     */
    private function rebuildToIntPrimaryKey(DatabaseInterface $db, string $table, string $name): void
    {
        $columns = $this->sqliteColumns($db, $table);
        $newTable = $table . '__int';
        $defs = self::LEGACY_SCHEMA[$name] ?? null;
        if ($defs === null) {
            return;
        }

        $db->execute('CREATE TABLE "' . $newTable . '" (' . $defs . ')');

        $order = $this->hasColumn($columns, 'created_at')
            ? 'ORDER BY "created_at", "id"'
            : 'ORDER BY "id"';
        $rows = $db->query('SELECT * FROM "' . $table . '" ' . $order)->fetchAll();

        $legacyColumns = $this->legacyColumnNames($name);
        $seq = 1;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $values = [];
            foreach ($legacyColumns as $column) {
                $value = $row[$column] ?? null;
                if ($column === 'id') {
                    $value = $seq;
                } elseif ($column === 'parent_id') {
                    $value = 0;
                }
                $values[] = $value;
            }

            $quotedCols = implode(', ', array_map(static fn (string $c): string => '"' . $c . '"', $legacyColumns));
            $placeholders = implode(', ', array_fill(0, count($legacyColumns), '?'));
            $db->execute('INSERT INTO "' . $newTable . '" (' . $quotedCols . ') VALUES (' . $placeholders . ')', $values);
            $seq++;
        }

        $db->execute('DROP TABLE "' . $table . '"');
        $db->execute('ALTER TABLE "' . $newTable . '" RENAME TO "' . $table . '"');
        $this->recreateIndexes($db, $table);
    }

    /**
     * Tên cột theo LEGACY_SCHEMA (giữ đúng thứ tự của 001).
     *
     * @return list<string>
     */
    private function legacyColumnNames(string $name): array
    {
        $defs = self::LEGACY_SCHEMA[$name] ?? '';
        preg_match_all('/"([a-z_]+)"\s+[A-Z]/', $defs, $matches);

        return $matches[1];
    }

    /**
     * Đọc schema của bảng qua PRAGMA (chỉ sqlite, nhưng hoạt động với mọi bảng).
     *
     * @return list<array{name: string, type: string, notnull: bool, dflt: ?string}>
     */
    private function sqliteColumns(DatabaseInterface $db, string $table): array
    {
        $rows = $db->query('PRAGMA table_info("' . $table . '")')->fetchAll();
        $columns = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['name'])) {
                continue;
            }
            $columns[] = [
                'name' => (string) $row['name'],
                'type' => (string) ($row['type'] ?? 'TEXT'),
                'notnull' => !empty($row['notnull']),
                'dflt' => isset($row['dflt_value']) && $row['dflt_value'] !== null ? (string) $row['dflt_value'] : null,
            ];
        }

        return $columns;
    }

    /**
     * @param list<array{name: string, type: string, notnull: bool, dflt: ?string}> $columns
     */
    private function hasColumn(array $columns, string $name): bool
    {
        foreach ($columns as $column) {
            if ($column['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tạo lại index của 001 sau khi rebuild bảng.
     */
    private function recreateIndexes(DatabaseInterface $db, string $table): void
    {
        $name = null;
        foreach (self::INDEXES as $key => $_) {
            if (str_ends_with($table, $key)) {
                $name = $key;
                break;
            }
        }

        if ($name === null) {
            return;
        }

        foreach (self::INDEXES[$name] as $index) {
            $unique = !empty($index['unique']) ? 'UNIQUE ' : '';
            $cols = implode(', ', array_map(static fn (string $c): string => '"' . $c . '"', $index['columns']));
            $db->execute(
                'CREATE ' . $unique . 'INDEX "' . $table . '_' . implode('_', $index['columns']) . '" ON "' . $table . '" (' . $cols . ')',
            );
        }
    }
};
