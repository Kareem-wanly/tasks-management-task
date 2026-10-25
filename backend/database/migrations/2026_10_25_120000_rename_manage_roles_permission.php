<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('permissions')->where('name', 'users.manage-roles')->first();
        if (! $old) {
            return;
        }

        $new = DB::table('permissions')->where('name', 'users.manage_roles')->first();

        if (! $new) {
            DB::table('permissions')->where('id', $old->id)->update(['name' => 'users.manage_roles']);

            return;
        }

        // The seeder already created the new row: move the role links, then drop the old row.
        $alreadyLinked = DB::table('permission_role')->where('permission_id', $new->id)->pluck('role_id');
        DB::table('permission_role')->where('permission_id', $old->id)->whereIn('role_id', $alreadyLinked)->delete();
        DB::table('permission_role')->where('permission_id', $old->id)->update(['permission_id' => $new->id]);
        DB::table('permissions')->where('id', $old->id)->delete();
    }

    public function down(): void
    {
        // Intentionally empty: the old name was a typo and is not restored.
    }
};
