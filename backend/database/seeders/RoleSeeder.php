<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Super administrator with full system access',
            ],
            [
                'name' => 'Project Manager',
                'slug' => 'project_manager',
                'description' => 'Manages assigned projects and teams',
            ],
            [
                'name' => 'Team Lead',
                'slug' => 'team_lead',
                'description' => 'Leads team and approves reports',
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Regular employee who works on projects',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
