<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    /**
     * Recalculate HPP (COGS) for a specific Recipe based on current Ingredient Moving-Average costs.
     */
    public function recalculateHpp(Recipe $recipe): Recipe
    {
        return DB::transaction(function () use ($recipe) {
            $totalIngredientCost = 0;

            foreach ($recipe->ingredients as $item) {
                $ing = Ingredient::find($item->ingredient_id);
                $unitCost = (float)($ing?->recipe_unit_cost ?? 0);

                $contribution = (float)$item->qty * $unitCost;
                $totalIngredientCost += $contribution;

                $item->update([
                    'unit_cost'         => $unitCost,
                    'cost_contribution' => $contribution,
                ]);
            }

            $yield = max(1, (float)$recipe->yield_qty);
            $totalHpp = ($totalIngredientCost + (float)$recipe->packaging_cost + (float)$recipe->operational_cost) / $yield;

            $recipe->update([
                'ingredient_cost'    => $totalIngredientCost,
                'total_hpp'          => $totalHpp,
                'last_calculated_at' => now(),
            ]);

            // Sync HPP to parent Product or ProductVariant
            if ($recipe->variant_id) {
                $variant = ProductVariant::find($recipe->variant_id);
                if ($variant) {
                    $variant->update(['hpp' => $totalHpp]);
                }
            } elseif ($recipe->product_id) {
                $product = Product::find($recipe->product_id);
                if ($product) {
                    $price = (float)$product->selling_price;
                    $margin = $price > 0 ? (($price - $totalHpp) / $price) : 0;
                    $markup = $totalHpp > 0 ? (($price - $totalHpp) / $totalHpp) : 0;

                    $product->update([
                        'hpp'          => $totalHpp,
                        'gross_margin' => $margin,
                        'markup_pct'   => $markup,
                    ]);
                }
            }

            ActivityLog::record(
                action:      'recalculate_hpp',
                module:      'recipes',
                entity:      'Recipe',
                entityId:    $recipe->id,
                description: "Recalculated HPP for recipe '{$recipe->name}': Rp " . number_format($totalHpp, 2),
            );

            return $recipe->fresh()->load('ingredients.ingredient.unit', 'product', 'variant');
        });
    }

    /**
     * Create or update recipe for a Product
     */
    public function saveProductRecipe(Product $product, array $data): Recipe
    {
        return DB::transaction(function () use ($product, $data) {
            $recipe = Recipe::updateOrCreate(
                ['product_id' => $product->id, 'variant_id' => null],
                [
                    'name'             => $data['name'] ?? "Resep {$product->name}",
                    'yield_qty'        => $data['yield_qty'] ?? 1,
                    'packaging_cost'   => $data['packaging_cost'] ?? 0,
                    'operational_cost' => $data['operational_cost'] ?? 0,
                    'description'      => $data['description'] ?? null,
                ]
            );

            // Sync ingredients
            $recipe->ingredients()->delete();
            foreach ($data['items'] as $item) {
                $recipe->ingredients()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'qty'           => $item['qty'],
                    'type'          => $item['type'] ?? 'main',
                    'notes'         => $item['notes'] ?? null,
                ]);
            }

            return $this->recalculateHpp($recipe);
        });
    }
}
