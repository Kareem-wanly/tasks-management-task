<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitrix24 step 4.3: checklists. As in Bitrix, a checklist is an item
        // without a parent and its items hang under it.
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_item_id')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('task_checklist_items')->cascadeOnDelete();
            $table->string('title', 2000);
            $table->boolean('is_complete')->default(false);
            $table->integer('sort_index')->default(0);
            $table->timestamps();

            $table->unique(['task_id', 'bitrix_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
    }
};
