<?php

namespace Database\Seeders;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SocialMediaSeeder extends Seeder
{
    public function run(): void
    {
        if (SocialAccount::count() > 0) {
            return;
        }

        // 1. Create Instagram Account
        $ig = SocialAccount::create([
            'platform' => 'instagram',
            'account_name' => 'Ratio Coffee Official',
            'handle' => '@ratiocoffee.id',
            'avatar_url' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=150',
            'access_token' => 'IGQVJ...MOCK_OAUTH_TOKEN',
            'status' => 'active',
            'connected_at' => Carbon::now()->subMonths(3),
        ]);

        // 2. Create TikTok Account
        $tt = SocialAccount::create([
            'platform' => 'tiktok',
            'account_name' => 'Ratio Coffee ID',
            'handle' => '@ratiocoffee_tiktok',
            'avatar_url' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=150',
            'access_token' => 'TT_ACT...MOCK_OAUTH_TOKEN',
            'status' => 'active',
            'connected_at' => Carbon::now()->subMonths(2),
        ]);

        // 3. Create Posts for Instagram
        SocialPost::create([
            'social_account_id' => $ig->id,
            'platform' => 'instagram',
            'post_id' => 'ig_post_001',
            'post_type' => 'reel',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1511920170033-f8396924c348?w=500',
            'caption' => 'Rahasia kenikmatan Espresso Blend khas Ratio Coffee! ☕🔥 Gunakan voucher promo #RATIO50 untuk diskon 50% di POS Kasir!',
            'permalink' => 'https://instagram.com/p/mock1',
            'views_count' => 45200,
            'likes_count' => 3820,
            'comments_count' => 240,
            'shares_count' => 510,
            'saves_count' => 890,
            'reach_count' => 52100,
            'engagement_rate' => 10.48,
            'promo_code' => 'RATIO50',
            'attributed_revenue' => 4850000.00,
            'attributed_transactions_count' => 97,
            'published_at' => Carbon::now()->subDays(5),
        ]);

        SocialPost::create([
            'social_account_id' => $ig->id,
            'platform' => 'instagram',
            'post_id' => 'ig_post_002',
            'post_type' => 'post',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=500',
            'caption' => 'Suasana sore hari di Ratio HQ. Cocok untuk WFC sambil nikmatin Iced Caramel Latte ✨',
            'permalink' => 'https://instagram.com/p/mock2',
            'views_count' => 12800,
            'likes_count' => 1140,
            'comments_count' => 45,
            'shares_count' => 32,
            'saves_count' => 120,
            'reach_count' => 14500,
            'engagement_rate' => 9.22,
            'published_at' => Carbon::now()->subDays(12),
        ]);

        SocialPost::create([
            'social_account_id' => $ig->id,
            'platform' => 'instagram',
            'post_id' => 'ig_post_003',
            'post_type' => 'reel',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500',
            'caption' => 'Manual Brew V60 Beans Gayo Single Origin! Klaim diskon khusus dengan promo V60FRESH di kasir POS!',
            'permalink' => 'https://instagram.com/p/mock3',
            'views_count' => 28900,
            'likes_count' => 2410,
            'comments_count' => 180,
            'shares_count' => 320,
            'saves_count' => 410,
            'reach_count' => 31000,
            'engagement_rate' => 10.71,
            'promo_code' => 'V60FRESH',
            'attributed_revenue' => 2100000.00,
            'attributed_transactions_count' => 42,
            'published_at' => Carbon::now()->subDays(18),
        ]);

        // 4. Create Posts for TikTok
        SocialPost::create([
            'social_account_id' => $tt->id,
            'platform' => 'tiktok',
            'post_id' => 'tt_post_001',
            'post_type' => 'video',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=500',
            'caption' => 'POV: Barista Ratio bikinin pesanan favorit kalian dalam 60 detik! ⚡ Jangan lupa klaim promo #TIKTOKRATIO!',
            'permalink' => 'https://tiktok.com/@ratiocoffee/video/mock1',
            'views_count' => 125000,
            'likes_count' => 14200,
            'comments_count' => 890,
            'shares_count' => 2100,
            'saves_count' => 1950,
            'reach_count' => 138000,
            'engagement_rate' => 13.87,
            'promo_code' => 'TIKTOKRATIO',
            'attributed_revenue' => 7600000.00,
            'attributed_transactions_count' => 152,
            'published_at' => Carbon::now()->subDays(3),
        ]);

        SocialPost::create([
            'social_account_id' => $tt->id,
            'platform' => 'tiktok',
            'post_id' => 'tt_post_002',
            'post_type' => 'video',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1507133750040-4a8f57021571?w=500',
            'caption' => 'Review sejujurnya dari pengunjung pas pertama kali coba Creamy Milk Coffee Ratio! 🤤',
            'permalink' => 'https://tiktok.com/@ratiocoffee/video/mock2',
            'views_count' => 68400,
            'likes_count' => 5890,
            'comments_count' => 310,
            'shares_count' => 430,
            'saves_count' => 820,
            'reach_count' => 74000,
            'engagement_rate' => 10.75,
            'published_at' => Carbon::now()->subDays(8),
        ]);
    }
}
