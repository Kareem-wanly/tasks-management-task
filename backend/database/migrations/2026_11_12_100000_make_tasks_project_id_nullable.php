<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitrix tasks may have no project (GROUP_ID = 0); ours may too.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse rather than silently delete tasks that have no project.
        if (DB::table('tasks')->whereNull('project_id')->exists()) {
            throw new RuntimeException('Tasks without a project exist; move or delete them before rolling back.');
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
