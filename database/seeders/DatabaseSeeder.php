<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Roles (5 System Roles) ──────────────────────────────────────────
        $roles = [
            ['name' => 'CEO',        'slug' => 'ceo',        'is_system' => true, 'description' => 'Akses keseluruhan Modul'],
            ['name' => 'Employee',   'slug' => 'employee',   'is_system' => true, 'description' => 'Akses Employee Dashboard, Operations, Finance, Reports & Social'],
            ['name' => 'Partner',    'slug' => 'partner',    'is_system' => true, 'description' => 'Akses Read-Only Dashboard saja'],
            ['name' => 'Consultant', 'slug' => 'consultant', 'is_system' => true, 'description' => 'Akses Modul Research Product saja'],
            ['name' => 'Operator',   'slug' => 'operator',   'is_system' => true, 'description' => 'Akses Keseluruhan Modul + Modul Revenue Sharing & Biaya Admin'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(['slug' => $roleData['slug']], $roleData);
        }

        $ceoRole        = Role::where('slug', 'ceo')->first();
        $employeeRole   = Role::where('slug', 'employee')->first();
        $partnerRole    = Role::where('slug', 'partner')->first();
        $consultantRole = Role::where('slug', 'consultant')->first();
        $operatorRole   = Role::where('slug', 'operator')->first();

        // ── 2. Permissions (System Master) ────────────────────────────────────
        $permissionDefs = [
            // Users & RBAC
            ['module' => 'users',       'action' => 'manage',    'slug' => 'user.manage'],
            ['module' => 'roles',       'action' => 'manage',    'slug' => 'role.manage'],
            ['module' => 'delegation',  'action' => 'manage',    'slug' => 'delegation.manage'],
            // Dashboard
            ['module' => 'dashboard',   'action' => 'view',      'slug' => 'dashboard.view'],
            // Outlets
            ['module' => 'outlets',     'action' => 'manage',    'slug' => 'outlet.manage'],
            // Inventory
            ['module' => 'inventory',   'action' => 'read',      'slug' => 'inventory.read'],
            ['module' => 'inventory',   'action' => 'create',    'slug' => 'inventory.create'],
            ['module' => 'inventory',   'action' => 'update',    'slug' => 'inventory.update'],
            ['module' => 'inventory',   'action' => 'delete',    'slug' => 'inventory.delete'],
            ['module' => 'inventory',   'action' => 'approve',   'slug' => 'inventory.approve'],
            ['module' => 'inventory',   'action' => 'export',    'slug' => 'inventory.export'],
            // Purchasing
            ['module' => 'purchase',    'action' => 'read',      'slug' => 'purchase.read'],
            ['module' => 'purchase',    'action' => 'create',    'slug' => 'purchase.create'],
            ['module' => 'purchase',    'action' => 'update',    'slug' => 'purchase.update'],
            ['module' => 'purchase',    'action' => 'approve',   'slug' => 'purchase.approve'],
            // Products & Recipes
            ['module' => 'product',     'action' => 'read',      'slug' => 'product.read'],
            ['module' => 'product',     'action' => 'create',    'slug' => 'product.create'],
            ['module' => 'product',     'action' => 'update',    'slug' => 'product.update'],
            ['module' => 'product',     'action' => 'delete',    'slug' => 'product.delete'],
            // POS
            ['module' => 'pos',         'action' => 'read',      'slug' => 'pos.read'],
            ['module' => 'pos',         'action' => 'create',    'slug' => 'pos.create'],
            ['module' => 'pos',         'action' => 'void',      'slug' => 'pos.void'],
            ['module' => 'pos',         'action' => 'refund',    'slug' => 'pos.refund'],
            // Finance
            ['module' => 'finance',     'action' => 'read',      'slug' => 'finance.read'],
            ['module' => 'finance',     'action' => 'create',    'slug' => 'finance.create'],
            ['module' => 'finance',     'action' => 'close',     'slug' => 'finance.close'],
            // Reports
            ['module' => 'report',      'action' => 'export',    'slug' => 'report.export'],
            // Activity Logs
            ['module' => 'activity',    'action' => 'view_all',  'slug' => 'activity.log.view_all'],
            ['module' => 'activity',    'action' => 'view_own',  'slug' => 'activity.log.view_own'],
            // Audit
            ['module' => 'audit',       'action' => 'view',      'slug' => 'audit.view'],
        ];

        $permissions = [];
        foreach ($permissionDefs as $pd) {
            $permissions[$pd['slug']] = Permission::firstOrCreate(['slug' => $pd['slug']], $pd);
        }

        // ── 3. Assign permissions to roles ────────────────────────────────────
        $allPermissionIds = Permission::pluck('id')->toArray();
        $ceoRole->permissions()->sync($allPermissionIds);
        $operatorRole->permissions()->sync($allPermissionIds);

        // Employee
        $empPerms = Permission::whereIn('module', ['dashboard', 'pos', 'inventory', 'purchase', 'product', 'finance', 'report', 'activity'])->pluck('id')->toArray();
        $employeeRole->permissions()->sync($empPerms);

        // Partner
        $partnerPerms = Permission::where('slug', 'dashboard.view')->pluck('id')->toArray();
        $partnerRole->permissions()->sync($partnerPerms);

        // Consultant
        $consultantPerms = Permission::whereIn('module', ['product', 'inventory'])->pluck('id')->toArray();
        $consultantRole->permissions()->sync($consultantPerms);

        // ── 4. Seed 5 Default System Accounts ──────────────────────────────────
        $users = [
            [
                'name' => 'Celino Rifky Atmodjo',
                'username' => 'Celino',
                'email' => 'ceo@ratiocoffee.id',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'position' => 'Chief Executive Officer',
                'role_id' => $ceoRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Kemal Virgie Muhammad',
                'username' => 'Kemal',
                'email' => 'kemalbhesat@gmail.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'position' => 'Operator Toko',
                'role_id' => $operatorRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Tifani',
                'username' => 'Fani',
                'email' => 'tifanidianisa7@gmail.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'position' => 'Employee Staf',
                'role_id' => $employeeRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Septian',
                'username' => 'Septian',
                'email' => 'septian@ratio.coffee',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'position' => 'Product R&D Consultant',
                'role_id' => $consultantRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Partner',
                'username' => 'Partner',
                'email' => 'partner@ratio.coffee',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'position' => 'Mitra Partner / Investor',
                'role_id' => $partnerRole->id,
                'status' => 'active',
            ],
        ];

        foreach ($users as $uData) {
            \App\Models\User::firstOrCreate(['email' => $uData['email']], $uData);
        }

        $this->command->info('✅ System Master Seeded (5 Roles & 5 Default Accounts created/synced).');
    }
}
