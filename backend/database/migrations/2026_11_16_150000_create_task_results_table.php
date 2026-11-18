<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitrix24 step 4.6: a task's official results (what was achieved).
        Schema::create('task_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_result_id')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('text');
            // Bitrix: open, or closed once the task is done.
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['task_id', 'bitrix_result_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_results');
    }
};
