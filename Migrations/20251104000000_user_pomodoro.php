<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Database\MigrationInterface;

/**
 * 20251104000000_user_pomodoro
 *
 * Ghi chú: file gốc viết theo API Cycle cũ (`extends Cycle\Migrations\Migration`)
 * — class đó không còn trong bản Cycle hiện tại nên `require` file này ném
 * "Class not found" và chặn toàn bộ `migrate`. Viết lại theo
 * MigrationInterface của project; schema giữ nguyên như định nghĩa gốc
 * (PomodoroController đọc/ghi các cột này).
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'user_pomodoro';
        if ($db->hasTable($table)) {
            return;
        }

        $schema = $db->table($table)->getSchema();
        $schema->primary('id');
        $schema->column('user_id')->string(64);
        $schema->column('work_minutes')->integer()->defaultValue(25);
        $schema->column('short_break_minutes')->integer()->defaultValue(5);
        $schema->column('long_break_minutes')->integer()->defaultValue(15);
        $schema->column('long_break_interval')->integer()->defaultValue(4);
        $schema->column('auto_start_breaks')->boolean()->defaultValue(false);
        $schema->column('auto_start_pomodoros')->boolean()->defaultValue(false);
        $schema->column('sound_enabled')->boolean()->defaultValue(true);
        $schema->column('sessions_completed_today')->integer()->defaultValue(0);
        $schema->column('total_sessions')->integer()->defaultValue(0);
        $schema->column('total_minutes')->integer()->defaultValue(0);
        $schema->column('last_session_at')->datetime()->nullable();
        $schema->column('current_state')->string(16)->defaultValue('work');
        $schema->column('current_cycle')->integer()->defaultValue(1);
        $schema->column('updated_at')->datetime()->nullable();
        $schema->column('created_at')->datetime()->nullable();
        $schema->index(['user_id']);
        $schema->save();
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'user_pomodoro';
        if ($db->hasTable($table)) {
            $schema = $db->table($table)->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }
};
