<?php

use Cycle\Migrations\Migration;

class UserPomodoroMigration extends Migration
{
    public function up(): void
    {
        $schema = $this->database->table('pw_user_pomodoro');
        $schema->addColumn('id')->bigPrimary();
        $schema->addColumn('user_id')->string(64)->index();
        $schema->addColumn('work_minutes')->integer()->defaultValue(25);
        $schema->addColumn('short_break_minutes')->integer()->defaultValue(5);
        $schema->addColumn('long_break_minutes')->integer()->defaultValue(15);
        $schema->addColumn('long_break_interval')->integer()->defaultValue(4);
        $schema->addColumn('auto_start_breaks')->boolean()->defaultValue(false);
        $schema->addColumn('auto_start_pomodoros')->boolean()->defaultValue(false);
        $schema->addColumn('sound_enabled')->boolean()->defaultValue(true);
        $schema->addColumn('sessions_completed_today')->integer()->defaultValue(0);
        $schema->addColumn('total_sessions')->integer()->defaultValue(0);
        $schema->addColumn('total_minutes')->integer()->defaultValue(0);
        $schema->addColumn('last_session_at')->timestamp()->nullable();
        $schema->addColumn('current_state')->string(16)->defaultValue('work');
        $schema->addColumn('current_cycle')->integer()->defaultValue(1);
        $schema->addColumn('updated_at')->timestamp()->nullable();
        $schema->addColumn('created_at')->timestamp()->nullable();
        $schema->create();
    }

    public function down(): void
    {
        $this->database->table('pw_user_pomodoro')->drop();
    }
}
