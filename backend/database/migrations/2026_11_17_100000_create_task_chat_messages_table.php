<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitrix24 step 4.7: the task chat replaces comments. Existing comments
     * move into the chat as local messages, then the comments table goes.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_chat_id')->nullable()->after('bitrix_task_id');
        });

        Schema::create('task_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_message_id')->nullable()->unique();
            // Null for Bitrix's own system lines ("status changed", "file added").
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->text('text');
            // Files attached in Bitrix: [{id, name, size, type}], id = Bitrix Drive file ID.
            $table->json('files')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        DB::table('task_chat_messages')->insertUsing(
            ['task_id', 'author_id', 'text', 'created_at', 'updated_at'],
            DB::table('comments')->select('task_id', 'user_id', 'body', 'created_at', 'updated_at')->orderBy('id'),
        );

        Schema::dropIfExists('comments');
    }

    public function down(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        DB::table('comments')->insertUsing(
            ['task_id', 'user_id', 'body', 'created_at', 'updated_at'],
            DB::table('task_chat_messages')->whereNull('bitrix_message_id')->whereNotNull('author_id')
                ->select('task_id', 'author_id', 'text', 'created_at', 'updated_at')->orderBy('id'),
        );

        Schema::dropIfExists('task_chat_messages');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('bitrix_chat_id');
        });
    }
};
