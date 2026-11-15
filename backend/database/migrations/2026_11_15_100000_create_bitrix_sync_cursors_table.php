<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per synced entity (users, projects, tasks): progress of the
        // current/last import, and when it last finished.
        Schema::create('bitrix_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->unique();
            $table->string('status')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_sync_cursors');
    }
};
