<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitrix24 step 4.2: subtasks (Bitrix PARENT_ID).
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('project_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
