<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'in_review', 'review', 'completed') NOT NULL DEFAULT 'todo'");

        DB::table('tasks')->where('status', 'in_review')->update(['status' => 'review']);

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'review', 'completed') NOT NULL DEFAULT 'todo'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'in_review', 'review', 'completed') NOT NULL DEFAULT 'todo'");

        DB::table('tasks')->where('status', 'review')->update(['status' => 'in_review']);

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'in_review', 'completed') NOT NULL DEFAULT 'todo'");
    }
};
