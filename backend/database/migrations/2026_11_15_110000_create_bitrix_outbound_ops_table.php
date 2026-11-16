<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every change we send to Bitrix24, so the event Bitrix sends back for
        // it can be recognised (confirmed) or caught as a conflict.
        Schema::create('bitrix_outbound_ops', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->unsignedBigInteger('bitrix_id');
            $table->string('action');
            // Hash of the record as we left it; null for a delete.
            $table->string('fingerprint', 64)->nullable();
            $table->string('status')->default('sent');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['entity', 'bitrix_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_outbound_ops');
    }
};
