<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowedSlugs = ['ceo', 'employee', 'partner', 'consultant', 'operator'];

        // Get IDs of allowed roles
        $allowedRoleIds = DB::table('roles')
            ->whereIn('slug', $allowedSlugs)
            ->pluck('id', 'slug');

        $employeeRoleId = $allowedRoleIds['employee'] ?? null;

        if ($employeeRoleId) {
            // Reassign any users with non-allowed roles to Employee
            DB::table('users')
                ->whereNotIn('role_id', $allowedRoleIds->values())
                ->update(['role_id' => $employeeRoleId]);
        }

        // Delete non-allowed roles
        DB::table('roles')
            ->whereNotIn('slug', $allowedSlugs)
            ->delete();

        // Ensure the 5 roles exist with precise names and order
        $roles = [
            ['name' => 'CEO',        'slug' => 'ceo',        'description' => 'Akses keseluruhan Modul', 'is_system' => true],
            ['name' => 'Employee',   'slug' => 'employee',   'description' => 'Akses Employee Dashboard, Operations, Finance, Reports & Social', 'is_system' => true],
            ['name' => 'Partner',    'slug' => 'partner',    'description' => 'Akses Read-Only Dashboard saja', 'is_system' => true],
            ['name' => 'Consultant', 'slug' => 'consultant', 'description' => 'Akses Modul Research Product saja', 'is_system' => true],
            ['name' => 'Operator',   'slug' => 'operator',   'description' => 'Akses Keseluruhan Modul + Modul Revenue Sharing & Biaya Admin', 'is_system' => true],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $r['slug']],
                array_merge($r, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        // No down action needed
    }
};
