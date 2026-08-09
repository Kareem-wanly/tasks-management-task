<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

        ['name' => 'users.view', 'description' => 'Allows user to users view'],
            ['name' => 'users.create', 'description' => 'Allows user to users create'],
            ['name' => 'users.update', 'description' => 'Allows user to users update'],
            ['name' => 'users.delete', 'description' => 'Allows user to users delete'],

            ['name' => 'roles.view', 'description' => 'Allows user to roles view'],
            ['name' => 'roles.create', 'description' => 'Allows user to roles create'],
            ['name' => 'roles.update', 'description' => 'Allows user to roles update'],
            ['name' => 'roles.delete', 'description' => 'Allows user to roles delete'],
            ['name' => 'roles.assign', 'description' => 'Allows user to roles assign'],
            ['name' => 'permissions.view', 'description' => 'Allows user to permissions view'],
            ['name' => 'permissions.assign', 'description' => 'Allows user to permissions assign'],

            ['name' => 'projects.view', 'description' => 'Allows user to projects view'],
            ['name' => 'projects.create', 'description' => 'Allows user to projects create'],
            ['name' => 'projects.update', 'description' => 'Allows user to projects update'],
            ['name' => 'projects.delete', 'description' => 'Allows user to projects delete'],
            ['name' => 'projects.archive', 'description' => 'Allows user to projects archive'],
            ['name' => 'projects.manage_members', 'description' => 'Allows user to projects manage_members'],

            
            ['name' => 'tasks.view', 'description' => 'Allows user to tasks view'],
            ['name' => 'tasks.create', 'description' => 'Allows user to tasks create'],
            ['name' => 'tasks.update', 'description' => 'Allows user to tasks update'],
            ['name' => 'tasks.delete', 'description' => 'Allows user to tasks delete'],
            ['name' => 'tasks.assign', 'description' => 'Allows user to tasks assign'],
            ['name' => 'tasks.change_status', 'description' => 'Allows user to tasks change_status'],

            
            ['name' => 'comments.view', 'description' => 'Allows user to comments view'],
            ['name' => 'comments.create', 'description' => 'Allows user to comments create'],
            ['name' => 'comments.update', 'description' => 'Allows user to comments update'],
            ['name' => 'comments.delete', 'description' => 'Allows user to comments delete'],
            ['name' => 'comments.manage_all', 'description' => 'Allows user to comments manage_all'],

            
            ['name' => 'activities.view', 'description' => 'Allows user to activities view'],
            ['name' => 'roles.manage', 'description' => 'Can create, update, and delete roles'],
            ['name' => 'users.manage-roles', 'description' => 'Can assign or revoke roles from users'],
        ];

        
        $createdPermissions = [];
        foreach ($permissions as $perm) {
            $createdPermissions[$perm['name']] = Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }

        
        $adminRole  = Role::firstOrCreate(['name' => 'Administrator', 'display_name' => 'System Administrator', 'description' => 'Has full access to all system resources']);
        $pmRole     = Role::firstOrCreate(['name' => 'Project Manager', 'display_name' => 'Project Manager', 'description' => 'Can manage assigned projects and tasks']);
        $memberRole = Role::firstOrCreate(['name' => 'Member', 'display_name' => 'Team Member', 'description' => 'Can view projects and work on assigned tasks']);

        
        $adminRole->permissions()->sync(collect($createdPermissions)->pluck('id')->toArray());

        $pmRole->permissions()->sync([
            $createdPermissions['projects.view']->id,
            $createdPermissions['projects.create']->id,
            $createdPermissions['projects.update']->id,
            $createdPermissions['projects.delete']->id,
            $createdPermissions['tasks.view']->id,
            $createdPermissions['tasks.create']->id,
            $createdPermissions['tasks.update']->id,
            $createdPermissions['tasks.delete']->id,
        ]);

        $memberRole->permissions()->sync([
            $createdPermissions['projects.view']->id,
            $createdPermissions['tasks.view']->id,
            $createdPermissions['tasks.update']->id,
        ]);

        
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('Password123!'),
            ]
        );

        $adminUser->roles()->sync([$adminRole->id]);
    }
}