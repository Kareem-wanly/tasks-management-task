<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitrix_portals', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique();
            $table->string('domain');
            // Encrypted columns are text: ciphertext is longer than the raw value.
            $table->text('application_token');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('app_info')->nullable();
            $table->json('installer')->nullable();
            $table->boolean('installer_is_admin')->default(false);
            $table->json('departments')->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_portals');
    }
};
