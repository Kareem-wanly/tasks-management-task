<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5.2 (dead letters): background Bitrix24 work that failed after all
 * its tries, shown on the sync status page with "Retry" until it succeeds.
 * One open row per operation and subject: failing again raises attempts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitrix_sync_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('operation', 20);
            $table->string('subject', 50);
            $table->text('error');
            $table->unsignedInteger('attempts')->default(1);
            $table->string('status', 20)->default('pending');
            // The failed_jobs row of the last failure, forgotten once this one is retried or closed.
            $table->uuid('failed_job_uuid')->nullable();
            $table->timestamp('last_failed_at');
            $table->timestamp('retried_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['operation', 'subject', 'status']);
            $table->index(['status', 'last_failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_sync_failures');
    }
};
