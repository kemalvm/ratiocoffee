<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // instagram, tiktok
            $table->string('account_name');
            $table->string('handle');
            $table->string('avatar_url')->nullable();
            $table->text('access_token')->nullable();
            $table->string('status')->default('active'); // active, expired, disconnected
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained('social_accounts')->onDelete('cascade');
            $table->string('platform'); // instagram, tiktok
            $table->string('post_id')->unique();
            $table->string('post_type')->default('post'); // post, reel, video
            $table->text('thumbnail_url')->nullable();
            $table->text('caption')->nullable();
            $table->string('permalink')->nullable();
            
            // Metrics
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('saves_count')->default(0);
            $table->unsignedBigInteger('reach_count')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0.00);

            // POS Promo correlation
            $table->string('promo_code')->nullable(); // e.g. PROMORATIO50
            $table->decimal('attributed_revenue', 12, 2)->default(0.00);
            $table->unsignedInteger('attributed_transactions_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
