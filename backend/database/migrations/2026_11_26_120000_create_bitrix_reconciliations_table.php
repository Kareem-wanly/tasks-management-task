<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5.3: the nightly reconciliation. After each full import of users,
 * projects and tasks, the Bitrix24 IDs it listed are compared with ours;
 * one row per check, so a week without differences can be shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitrix_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 20);
            $table->unsignedInteger('bitrix_count');
            $table->unsignedInteger('local_count');
            // In Bitrix but not here (skipped by the import), and here but not in Bitrix (not deleted).
            $table->unsignedInteger('missing_here_count');
            $table->unsignedInteger('extra_here_count');
            // The first IDs of each, to look them up; the counts say how many there are.
            $table->json('missing_here');
            $table->json('extra_here');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['entity', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_reconciliations');
    }
};
