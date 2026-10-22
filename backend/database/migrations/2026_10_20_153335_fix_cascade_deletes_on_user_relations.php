<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $databaseName = DB::getDatabaseName();
        $exists = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$databaseName, $table, $foreignKey]
        );

        if (! empty($exists)) {
            Schema::table($table, function (Blueprint $t) use ($foreignKey) {
                $t->dropForeign($foreignKey);
            });
        }
    }

    public function up(): void
    {
        $this->dropForeignKeyIfExists('projects', 'projects_owner_id_foreign');
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        $this->dropForeignKeyIfExists('tasks', 'tasks_assigned_to_foreign');
        $this->dropForeignKeyIfExists('tasks', 'tasks_created_by_foreign');

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->change();

            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $this->dropForeignKeyIfExists('comments', 'comments_user_id_foreign');

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('projects', 'projects_owner_id_foreign');
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->dropForeignKeyIfExists('tasks', 'tasks_assigned_to_foreign');
        $this->dropForeignKeyIfExists('tasks', 'tasks_created_by_foreign');
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('assigned_to')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->dropForeignKeyIfExists('comments', 'comments_user_id_foreign');
        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
