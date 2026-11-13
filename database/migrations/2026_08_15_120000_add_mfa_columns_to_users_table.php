<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_enabled')->default(false)->after('status');
            $table->string('mfa_pin')->nullable()->after('mfa_enabled');
            $table->boolean('mfa_email_otp_enabled')->default(false)->after('mfa_pin');
            $table->text('mfa_fingerprint_credential')->nullable()->after('mfa_email_otp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mfa_enabled',
                'mfa_pin',
                'mfa_email_otp_enabled',
                'mfa_fingerprint_credential'
            ]);
        });
    }
};
