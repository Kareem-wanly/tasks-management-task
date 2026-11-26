<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 5.1: the permission of the sync status page. The seeder adds it on
 * fresh installs; existing databases get it here. Administrators pass
 * every check anyway.
 */
return new class extends Migration
{
    private const PERMISSION = 'bitrix_sync.manage';

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'description' => 'Can see the Bitrix24 sync status and start a sync',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('name', self::PERMISSION)->first();
        if ($permission !== null) {
            DB::table('permission_role')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
