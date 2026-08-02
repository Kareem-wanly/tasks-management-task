<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // عنوان المهمة
            $table->text('description')->nullable(); // تفاصيل المهمة
            
            $table->enum('status', ['todo', 'in_progress', 'in_review', 'completed'])->default('todo');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            $table->timestamp('due_date')->nullable(); // موعد التسليم النهائية
            
            $table->foreignId('project_id')->constrained()->onDelete('cascade'); // المشروع التابعة له
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // الشخص المكلف بالمهمة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // منشئ المهمة
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
