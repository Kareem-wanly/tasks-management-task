<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); 
            $table->text('description')->nullable(); 
            
            $table->enum('status', ['todo', 'in_progress', 'in_review', 'completed'])->default('todo');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            $table->timestamp('due_date')->nullable(); 
            
            $table->foreignId('project_id')->constrained()->onDelete('cascade'); 
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); 
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); 
            
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
