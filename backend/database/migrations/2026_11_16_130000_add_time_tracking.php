<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitrix24 step 4.4: time tracking. Seconds throughout, as in Bitrix.
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('allow_time_tracking')->default(false)->after('due_date');
            // Planned time (TIME_ESTIMATE).
            $table->unsignedInteger('time_estimate')->default(0)->after('allow_time_tracking');
            // Sum of the time log (TIME_SPENT_IN_LOGS), read from Bitrix.
            $table->unsignedInteger('time_spent')->default(0)->after('time_estimate');
        });

        Schema::create('task_elapsed_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_item_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('seconds');
            $table->text('comment')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'bitrix_item_id']);
        });

        // Our own timer: Bitrix has no REST method for its timer. One per user.
        Schema::create('running_timers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('running_timers');
        Schema::dropIfExists('task_elapsed_items');
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['allow_time_tracking', 'time_estimate', 'time_spent']);
        });
    }
};
