<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Admin role if missing and assigns it to Pankaj (user_id 1 / pankaj@yopmail.com).
     */
    public function up(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if (!$adminRole) {
            $id = DB::table('roles')->insertGetId([
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full company access – users, teams, projects, tasks, leave, EOD, approvals',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $adminRole = (object) ['id' => $id];
        } else {
            $adminRole = (object) $adminRole;
        }

        $pankaj = DB::table('users')->where('email', 'pankaj@yopmail.com')->orWhere('id', 1)->first();
        if (!$pankaj) {
            return;
        }
        $userId = $pankaj->id;
        $exists = DB::table('user_roles')->where('user_id', $userId)->where('role_id', $adminRole->id)->exists();
        if (!$exists) {
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $adminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $pankaj = DB::table('users')->where('email', 'pankaj@yopmail.com')->orWhere('id', 1)->first();
        if ($pankaj) {
            $adminRole = DB::table('roles')->where('slug', 'admin')->first();
            if ($adminRole) {
                DB::table('user_roles')->where('user_id', $pankaj->id)->where('role_id', $adminRole->id)->delete();
            }
        }
        // Optionally remove Admin role: DB::table('roles')->where('slug', 'admin')->delete();
    }
};
