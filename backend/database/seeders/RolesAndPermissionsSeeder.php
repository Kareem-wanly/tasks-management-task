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
            ['name' => 'users.view', 'description' => 'Allows user to view users'],
            ['name' => 'users.create', 'description' => 'Allows user to create users'],
            ['name' => 'users.update', 'description' => 'Allows user to update users'],
            ['name' => 'users.delete', 'description' => 'Allows user to delete users'],

            ['name' => 'roles.view', 'description' => 'Allows user to view roles'],
            ['name' => 'roles.create', 'description' => 'Allows user to create roles'],
            ['name' => 'roles.update', 'description' => 'Allows user to update roles'],
            ['name' => 'roles.delete', 'description' => 'Allows user to delete roles'],
            ['name' => 'roles.assign', 'description' => 'Allows user to assign roles'],
            ['name' => 'permissions.view', 'description' => 'Allows user to view permissions'],
            ['name' => 'permissions.assign', 'description' => 'Allows user to assign permissions'],

            ['name' => 'projects.view', 'description' => 'Allows user to view projects'],
            ['name' => 'projects.create', 'description' => 'Allows user to create projects'],
            ['name' => 'projects.update', 'description' => 'Allows user to update projects'],
            ['name' => 'projects.delete', 'description' => 'Allows user to delete projects'],
            ['name' => 'projects.archive', 'description' => 'Allows user to archive projects'],
            ['name' => 'projects.manage_members', 'description' => 'Allows user to manage project members'],

            ['name' => 'tasks.view', 'description' => 'Allows user to view tasks'],
            ['name' => 'tasks.create', 'description' => 'Allows user to create tasks'],
            ['name' => 'tasks.update', 'description' => 'Allows user to update tasks'],
            ['name' => 'tasks.delete', 'description' => 'Allows user to delete tasks'],
            ['name' => 'tasks.assign', 'description' => 'Allows user to assign tasks'],
            ['name' => 'tasks.change_status', 'description' => 'Allows user to change task status'],

            ['name' => 'comments.view', 'description' => 'Allows user to view comments'],
            ['name' => 'comments.create', 'description' => 'Allows user to create comments'],
            ['name' => 'comments.update', 'description' => 'Allows user to update comments'],
            ['name' => 'comments.delete', 'description' => 'Allows user to delete comments'],
            ['name' => 'comments.manage_all', 'description' => 'Allows user to manage all comments'],

            ['name' => 'activities.view', 'description' => 'Allows user to view activities'],
            ['name' => 'roles.manage', 'description' => 'Can create, update, and delete roles'],
            ['name' => 'users.manage-roles', 'description' => 'Can assign or revoke roles from users'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }

        $adminRole  = Role::firstOrCreate(['name' => 'Administrator', 'display_name' => 'System Administrator', 'description' => 'Has full access to all system resources']);
        $pmRole     = Role::firstOrCreate(['name' => 'Project Manager', 'display_name' => 'Project Manager', 'description' => 'Can manage assigned projects and tasks']);
        $memberRole = Role::firstOrCreate(['name' => 'Member', 'display_name' => 'Team Member', 'description' => 'Can view projects and work on assigned tasks']);

        // 1. Admin krijgt alle permissies
        $allPermissionIds = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($allPermissionIds);

        // 2. Project Manager permissies
        $pmPermissionNames = [
            'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.archive', 'projects.manage_members',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.change_status',
            'comments.view', 'comments.create', 'comments.update', 'comments.delete'
        ];
        $pmRole->permissions()->sync(Permission::whereIn('name', $pmPermissionNames)->pluck('id'));

        // 3. Member permissies
        $memberPermissionNames = [
            'projects.view',
            'tasks.view', 'tasks.update', 'tasks.change_status',
            'comments.view', 'comments.create'
        ];
        $memberRole->permissions()->sync(Permission::whereIn('name', $memberPermissionNames)->pluck('id'));

        // 4. Admin User
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