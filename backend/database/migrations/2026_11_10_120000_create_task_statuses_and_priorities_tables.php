<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Keys match the old enum values so existing tasks and the frontend keep working.
    // bitrix_id is the STATUS / PRIORITY value of tasks.task.getFields.
    private const STATUSES = [
        ['key' => 'todo', 'name' => 'To Do', 'bitrix_id' => 2, 'is_final' => false, 'sort' => 1, 'color' => 'neutral'],
        ['key' => 'in_progress', 'name' => 'In Progress', 'bitrix_id' => 3, 'is_final' => false, 'sort' => 2, 'color' => 'info'],
        ['key' => 'review', 'name' => 'Review', 'bitrix_id' => 4, 'is_final' => false, 'sort' => 3, 'color' => 'brand'],
        ['key' => 'completed', 'name' => 'Completed', 'bitrix_id' => 5, 'is_final' => true, 'sort' => 4, 'color' => 'success'],
        ['key' => 'deferred', 'name' => 'Deferred', 'bitrix_id' => 6, 'is_final' => false, 'sort' => 5, 'color' => 'warning'],
    ];

    // "urgent" is ours: Bitrix has no such priority, so it is sent as High (2).
    private const PRIORITIES = [
        ['key' => 'low', 'name' => 'Low', 'bitrix_id' => 0, 'is_system' => true, 'sort' => 1, 'color' => 'neutral'],
        ['key' => 'medium', 'name' => 'Medium', 'bitrix_id' => 1, 'is_system' => true, 'sort' => 2, 'color' => 'info'],
        ['key' => 'high', 'name' => 'High', 'bitrix_id' => 2, 'is_system' => true, 'sort' => 3, 'color' => 'warning'],
        ['key' => 'urgent', 'name' => 'Urgent', 'bitrix_id' => 2, 'is_system' => false, 'sort' => 4, 'color' => 'danger'],
    ];

    private const PERMISSION = 'task_settings.manage';

    public function up(): void
    {
        foreach (['task_statuses', 'task_priorities'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('key', 50)->unique();
                $table->string('name');
                $table->unsignedTinyInteger('bitrix_id')->nullable();
                $table->boolean('is_system')->default(false);
                if ($name === 'task_statuses') {
                    $table->boolean('is_final')->default(false);
                }
                $table->unsignedSmallInteger('sort')->default(0);
                $table->string('color', 20)->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        DB::table('task_statuses')->insert(array_map(
            fn ($row) => $row + ['is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            self::STATUSES
        ));
        DB::table('task_priorities')->insert(array_map(
            fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now],
            self::PRIORITIES
        ));

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 50)->default('todo')->change();
            $table->string('priority', 50)->default('medium')->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('status')->references('key')->on('task_statuses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('priority')->references('key')->on('task_priorities')->cascadeOnUpdate()->restrictOnDelete();
        });

        // The seeder adds it on fresh installs; existing databases get it here.
        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'description' => 'Can manage task statuses and priorities',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['status']);
            $table->dropForeign(['priority']);
        });

        // Values the old enums don't know fall back to the defaults.
        DB::table('tasks')->whereNotIn('status', ['todo', 'in_progress', 'review', 'completed'])->update(['status' => 'todo']);
        DB::table('tasks')->whereNotIn('priority', ['low', 'medium', 'high', 'urgent'])->update(['priority' => 'medium']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['todo', 'in_progress', 'review', 'completed'])->default('todo')->change();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->change();
        });

        Schema::dropIfExists('task_priorities');
        Schema::dropIfExists('task_statuses');

        $permission = DB::table('permissions')->where('name', self::PERMISSION)->first();
        if ($permission) {
            DB::table('permission_role')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
