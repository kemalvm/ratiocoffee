<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Seed / refresh the 5 system roles ─────────────────────────
        $roles = [
            ['name'=>'CEO',        'slug'=>'ceo',        'description'=>'Full access – owner & executive',    'is_system'=>true],
            ['name'=>'Employee',   'slug'=>'employee',   'description'=>'Staff operational access',            'is_system'=>true],
            ['name'=>'Partner',    'slug'=>'partner',    'description'=>'Read-only dashboard view',            'is_system'=>true],
            ['name'=>'Consultant', 'slug'=>'consultant', 'description'=>'Research Product module only',        'is_system'=>true],
            ['name'=>'Operator',   'slug'=>'operator',   'description'=>'Full access + Biaya Admin module',   'is_system'=>true],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['slug' => $r['slug']], array_merge($r, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ── 2. Seed core permissions ──────────────────────────────────────
        $modules = ['dashboard','pos','inventory','suppliers','purchasing','products',
                    'finance','users','activity_logs','reports','social',
                    'research_product','admin_fee','tax_settings','hq'];
        $actions = ['read','create','update','delete','approve','export'];

        foreach ($modules as $mod) {
            foreach ($actions as $act) {
                $slug = "{$mod}.{$act}";
                DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                    'module'      => $mod,
                    'action'      => $act,
                    'slug'        => $slug,
                    'description' => ucfirst($act) . ' ' . $mod,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // Helper: get role id by slug, get permission ids by pattern
        $roleId = fn(string $slug) => DB::table('roles')->where('slug', $slug)->value('id');
        $permIds = fn(array $slugs) => DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        $allPerms = fn() => DB::table('permissions')->pluck('id');

        // ── 3. CEO — all permissions ──────────────────────────────────────
        $ceoId = $roleId('ceo');
        DB::table('role_permissions')->where('role_id', $ceoId)->delete();
        foreach ($allPerms() as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$ceoId,'permission_id'=>$pid,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 4. Employee permissions ──────────────────────────────────────
        $empId = $roleId('employee');
        DB::table('role_permissions')->where('role_id', $empId)->delete();
        $empSlugs = [
            'dashboard.read',
            'pos.read','pos.create',
            'inventory.read',
            'suppliers.read',
            'purchasing.read','purchasing.create',
            'products.read',
            'finance.read','finance.create',
            'reports.read','reports.export',
            'social.read',
            'activity_logs.read', // own only (filtered server-side)
        ];
        foreach ($permIds($empSlugs) as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$empId,'permission_id'=>$pid,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 5. Partner permissions (read-only dashboard) ──────────────────
        $partId = $roleId('partner');
        DB::table('role_permissions')->where('role_id', $partId)->delete();
        foreach ($permIds(['dashboard.read']) as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$partId,'permission_id'=>$pid,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 6. Consultant permissions (research_product + inventory.read + product.read) ─────────────
        $conId = $roleId('consultant');
        DB::table('role_permissions')->where('role_id', $conId)->delete();
        foreach ($permIds(['research_product.read','research_product.create','research_product.update','research_product.delete','inventory.read','product.read']) as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$conId,'permission_id'=>$pid,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 7. Operator — all + admin_fee ────────────────────────────────
        $opId = $roleId('operator');
        DB::table('role_permissions')->where('role_id', $opId)->delete();
        foreach ($allPerms() as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$opId,'permission_id'=>$pid,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 8. admin_fee_proposals table ──────────────────────────────────
        if (!Schema::hasTable('admin_fee_proposals')) {
            Schema::create('admin_fee_proposals', function (Blueprint $table) {
                $table->id();
                $table->decimal('proposed_rate', 5, 2); // e.g. 5.00 (%)
                $table->decimal('current_rate',  5, 2)->default(0);
                $table->enum('status', ['pending','approved','rejected','revised'])->default('pending');
                $table->text('notes')->nullable();          // CEO notes
                $table->text('operator_notes')->nullable(); // Operator feedback
                $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 9. admin_fee_settings table ───────────────────────────────────
        if (!Schema::hasTable('admin_fee_settings')) {
            Schema::create('admin_fee_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('rate', 5, 2)->default(0); // active % rate
                $table->timestamps();
            });
            // seed default
            DB::table('admin_fee_settings')->insert(['rate'=>0,'created_at'=>now(),'updated_at'=>now()]);
        }

        // ── 10. research_products table ───────────────────────────────────
        if (!Schema::hasTable('research_products')) {
            Schema::create('research_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->decimal('estimated_cost', 14, 2)->default(0);
                $table->decimal('target_price',   14, 2)->default(0);
                $table->enum('status', ['draft','testing','approved','rejected'])->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_products');
        Schema::dropIfExists('admin_fee_settings');
        Schema::dropIfExists('admin_fee_proposals');
    }
};
