<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('status', ['active', 'completed', 'archived'])->default('active');
    
    $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
    
    $table->date('start_date')->nullable();
    $table->date('due_date')->nullable();
    
    $table->softDeletes();
    $table->timestamps();
});
    }

    
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
