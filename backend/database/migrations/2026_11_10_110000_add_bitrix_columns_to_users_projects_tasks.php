<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_user_id')->nullable()->unique()->after('id');
            // Encrypted columns are text: ciphertext is longer than the raw value.
            $table->text('bitrix_access_token')->nullable();
            $table->text('bitrix_refresh_token')->nullable();
            $table->timestamp('bitrix_token_expires_at')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('bitrix_synced_at')->nullable();
        });

        // Employees imported from Bitrix have no local password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_group_id')->nullable()->unique()->after('id');
            $table->timestamp('bitrix_synced_at')->nullable();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_task_id')->nullable()->unique()->after('id');
            $table->timestamp('bitrix_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['bitrix_task_id']);
            $table->dropColumn(['bitrix_task_id', 'bitrix_synced_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['bitrix_group_id']);
            $table->dropColumn(['bitrix_group_id', 'bitrix_synced_at']);
        });

        // password becomes required again, so passwordless (Bitrix-only)
        // users get an unusable random one.
        DB::table('users')->whereNull('password')->lazyById()->each(
            fn (object $user) => DB::table('users')->where('id', $user->id)->update(['password' => Hash::make(Str::random(40))])
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['bitrix_user_id']);
            $table->dropColumn([
                'bitrix_user_id',
                'bitrix_access_token',
                'bitrix_refresh_token',
                'bitrix_token_expires_at',
                'avatar_url',
                'position',
                'is_active',
                'bitrix_synced_at',
            ]);
        });
    }
};
