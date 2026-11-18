<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitrix24 step 4.5: files attached to a task. In Bitrix a file lives
        // on the uploader's Drive (bitrix_disk_file_id) and is attached to the
        // task (attached_object_id). Tasks outside Bitrix keep theirs in `path`.
        Schema::create('task_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_disk_file_id')->nullable();
            $table->unsignedBigInteger('attached_object_id')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->string('path')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'attached_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_files');
    }
};
