<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Ingredient;

class SanitizeIngredientCosts extends Command
{
    protected $signature = 'inventory:sanitize-costs
                            {--dry-run : Show what would be changed without writing to the database}
                            {--threshold=5000 : Max acceptable cost-per-unit (IDR). Ingredients above this are likely using package price.}';

    protected $description = 'Sanitize ingredient average_cost / last_purchase_price that were recorded as '
                           . 'package-level prices instead of per-unit prices. '
                           . 'Corrects: average_cost = last_purchase_price / net_content.';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $threshold = (float) $this->option('threshold');

        $this->info('=== Ratio Coffee ERP — Inventory Cost Sanitisation ===');
        $this->info("Threshold: Rp " . number_format($threshold, 0, ',', '.') . " per unit");
        $this->newLine();

        if ($dryRun) {
            $this->warn('[DRY-RUN MODE] No changes will be written to the database.');
            $this->newLine();
        }

        // ── 1. Identify candidates ─────────────────────────────────────────────
        // A candidate is an active ingredient where:
        //   - net_content > 1  (i.e., the base unit is gram/ml/piece-of-pack)
        //   - average_cost or last_purchase_price exceeds the threshold per unit
        //     (strongly indicates a package price was stored as a per-unit cost)
        $candidates = Ingredient::whereNull('deleted_at')
            ->where('status', 'active')
            ->where('net_content', '>', 1)
            ->where(function ($q) use ($threshold) {
                $q->where('average_cost', '>', $threshold)
                  ->orWhere('last_purchase_price', '>', $threshold);
            })
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('✅  No suspect records found. All ingredient costs look healthy.');
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} ingredient(s) with potentially inflated cost:");
        $this->newLine();

        $headers = ['ID', 'Code', 'Name', 'net_content', 'avg_cost (before)', 'last_price (before)', 'corrected_avg_cost'];
        $rows    = [];
        $fixed   = 0;

        foreach ($candidates as $ing) {
            $net       = max(1.0, (float) $ing->net_content);
            $lastPrice = (float) $ing->last_purchase_price;
            $avgCost   = (float) $ing->average_cost;

            // Compute the corrected per-unit cost
            $correctedAvgCost = $lastPrice > 0
                ? round($lastPrice / $net, 6)
                : ($avgCost > $threshold ? round($avgCost / $net, 6) : $avgCost);

            $rows[] = [
                $ing->id,
                $ing->code,
                $ing->name,
                $net,
                number_format($avgCost, 2),
                number_format($lastPrice, 2),
                number_format($correctedAvgCost, 2),
            ];

            if (!$dryRun) {
                $updates = [];

                // Only correct average_cost if it exceeds the threshold
                if ($avgCost > $threshold) {
                    $updates['average_cost'] = $correctedAvgCost;
                }

                // Correct last_purchase_price if it exceeds threshold AND net_content > 1
                // Keep the original package price in a separate column for reference if needed —
                // here we simply leave last_purchase_price as-is (it represents the package cost).
                // average_cost is what drives valuations so fixing it is sufficient.

                if (!empty($updates)) {
                    $updates['updated_at'] = now();
                    $ing->updateQuietly($updates);
                    $fixed++;
                }

                // Also recompute ingredient_stocks unit_cost proportionally if the table exists
                if (\Illuminate\Support\Facades\Schema::hasTable('ingredient_stocks')) {
                    DB::table('ingredient_stocks')
                        ->where('ingredient_id', $ing->id)
                        ->where('unit_cost', '>', $threshold)
                        ->update([
                            'unit_cost'  => DB::raw("unit_cost / {$net}"),
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->warn("[DRY-RUN] {$candidates->count()} ingredient(s) would be corrected. Run without --dry-run to apply.");
        } else {
            $this->info("✅  Fixed {$fixed} ingredient(s) with corrected average_cost = last_purchase_price / net_content.");
            $this->newLine();
            $this->info('💡  Tip: Clear any cached KPI values and refresh the CEO Dashboard to verify the corrected inventory valuation.');
        }

        return self::SUCCESS;
    }
}
