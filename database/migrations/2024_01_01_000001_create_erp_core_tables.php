<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Roles ────────────────────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // CEO, Manager, Cashier, etc.
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // system roles can't be deleted
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Permissions ──────────────────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module');                  // inventory, pos, finance …
            $table->string('action');                  // create, read, update, delete, approve, export, import
            $table->string('slug')->unique();          // inventory.create
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // ─── Role ↔ Permission pivot ───────────────────────────────────────
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
            $table->timestamps();
        });

        // ─── Outlets / Branches ───────────────────────────────────────────
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Users ────────────────────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('employee_id')->nullable()->unique(); // staff ID card
            $table->string('position')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role_id', 'status']);
            $table->index('outlet_id');
        });

        // ─── User ↔ Permission overrides (per-user extras/revokes) ────────
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['grant', 'revoke'])->default('grant');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        // ─── Password reset tokens ────────────────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Sessions ─────────────────────────────────────────────────────
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ─── Personal access tokens (Laravel Sanctum) ─────────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ─── Delegations ─────────────────────────────────────────────────
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->string('module');
            $table->json('permissions');              // ['create','read','update','delete','approve','export','import']
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'module', 'active']);
        });

        // ─── Activity Logs ────────────────────────────────────────────────
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');                  // login, create_product, void_order …
            $table->string('module')->nullable();      // inventory, pos, finance …
            $table->string('entity')->nullable();      // Product, Order, User …
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'action']);
            $table->index(['module', 'entity']);
            $table->index('created_at');
        });

        // ─── Notifications ────────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
