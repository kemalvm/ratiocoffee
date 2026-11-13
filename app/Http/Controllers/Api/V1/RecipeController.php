<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $recipeService) {}

    // POST /api/v1/recipes/product/{product}
    public function storeProductRecipe(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['nullable', 'string'],
            'yield_qty'             => ['nullable', 'numeric', 'min:0.001'],
            'packaging_cost'        => ['nullable', 'numeric', 'min:0'],
            'operational_cost'      => ['nullable', 'numeric', 'min:0'],
            'description'           => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.qty'           => ['required', 'numeric', 'min:0.0001'],
            'items.*.type'          => ['nullable', 'in:main,packaging,garnish'],
            'items.*.notes'         => ['nullable', 'string'],
        ]);

        $recipe = $this->recipeService->saveProductRecipe($product, $data);

        return response()->json([
            'message' => 'Resep & HPP produk berhasil diperbarui.',
            'recipe'  => $recipe,
            'product' => $product->fresh(),
        ]);
    }

    // POST /api/v1/recipes/{recipe}/recalculate
    public function recalculate(Recipe $recipe): JsonResponse
    {
        $updated = $this->recipeService->recalculateHpp($recipe);
        return response()->json(['message' => 'HPP Berhasil dikalkulasi ulang.', 'recipe' => $updated]);
    }
}
