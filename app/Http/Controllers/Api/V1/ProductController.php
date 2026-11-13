<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private readonly RecipeService $recipeService) {}

    // GET /api/v1/products
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'variants', 'recipe.ingredients.ingredient.unit'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('sku', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->has('available_for_pos'), fn($q) => $q->where('available_for_pos', $request->boolean('available_for_pos')))
            ->when($request->has('is_bundling'), fn($q) => $q->where('is_bundling', $request->boolean('is_bundling')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return response()->json($products);
    }

    // POST /api/v1/products
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'         => ['required', 'exists:product_categories,id'],
            'name'                => ['required', 'string', 'max:200'],
            'selling_price'       => ['required', 'numeric', 'min:0'],
            'barcode'             => ['nullable', 'string', 'unique:products,barcode'],
            'description'         => ['nullable', 'string'],
            'available_for_pos'   => ['boolean'],
            'has_variant'         => ['boolean'],
            'is_bundling'          => ['boolean'],
            'bundle_data'         => ['nullable', 'array', 'required_if:is_bundling,true'],
            'bundle_data.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'bundle_data.*.qty'        => ['required', 'integer', 'min:1'],
            'recipe'              => ['nullable', 'array'],
            'recipe.items'        => ['nullable', 'array'],
        ]);

        $product = DB::transaction(function () use ($data) {
            if (!empty($data['is_bundling'])) {
                [$data['bundle_data'], $data['hpp']] = $this->normalizeBundle($data['bundle_data'] ?? []);
            }
            $data['sku']  = Product::generateNextSku();
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']) . '-' . time();
            $product = Product::create($data);

            if (!empty($data['is_bundling'])) {
                $bundleRecipe = $this->buildBundleRecipe($data['bundle_data']);
                if (!empty($bundleRecipe['items'])) {
                    $this->recipeService->saveProductRecipe($product, $bundleRecipe);
                }
                $this->syncBundleFinancials($product, (float) $data['hpp']);
            } elseif (!empty($data['recipe']['items'])) {
                $this->recipeService->saveProductRecipe($product, $data['recipe']);
            }
            return $product;
        });

        ActivityLog::record(
            action:      'create_product',
            module:      'products',
            entity:      'Product',
            entityId:    $product->id,
            description: "Created product {$product->name} (Price: Rp {$product->selling_price})",
        );

        return response()->json(['message' => 'Produk berhasil dibuat.', 'product' => $product->fresh()->load('category', 'recipe.ingredients.ingredient.unit')], 201);
    }

    // GET /api/v1/products/{product}
    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => $product->load('category', 'variants', 'recipe.ingredients.ingredient.unit')]);
    }

    // PATCH /api/v1/products/{product}
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id'       => ['sometimes', 'exists:product_categories,id'],
            'name'              => ['sometimes', 'string', 'max:200'],
            'selling_price'     => ['sometimes', 'numeric', 'min:0'],
            'description'       => ['nullable', 'string'],
            'available_for_pos' => ['boolean'],
            'status'            => ['sometimes', 'in:active,inactive,archived'],
            'is_bundling'        => ['boolean'],
            'bundle_data'       => ['nullable', 'array'],
            'bundle_data.*.product_id' => ['required_with:bundle_data', 'integer', 'distinct', 'exists:products,id'],
            'bundle_data.*.qty'        => ['required_with:bundle_data', 'integer', 'min:1'],
            'recipe'            => ['nullable', 'array'],
            'recipe.items'      => ['nullable', 'array'],
        ]);

        if (!empty($data['is_bundling']) || ($product->is_bundling && array_key_exists('bundle_data', $data))) {
            [$data['bundle_data'], $data['hpp']] = $this->normalizeBundle($data['bundle_data'] ?? $product->bundle_data ?? [], $product->id);
            $data['is_bundling'] = true;
        }
        $product->update($data);

        if (!empty($data['is_bundling']) && !empty($data['bundle_data'])) {
            $bundleRecipe = $this->buildBundleRecipe($data['bundle_data']);
            if (!empty($bundleRecipe['items'])) {
                $this->recipeService->saveProductRecipe($product, $bundleRecipe);
            }
            $this->syncBundleFinancials($product, (float) $data['hpp']);
        } elseif (isset($data['recipe']) && is_array($data['recipe']) && !empty($data['recipe']['items'])) {
            $this->recipeService->saveProductRecipe($product, $data['recipe']);
        } elseif ($product->recipe) {
            $this->recipeService->recalculateHpp($product->recipe);
        }

        return response()->json(['message' => 'Produk diperbarui.', 'product' => $product->fresh()->load('category', 'recipe.ingredients.ingredient.unit')]);
    }

    // DELETE /api/v1/products/{product}
    public function destroy(Product $product): JsonResponse
    {
        if ($product->recipe) {
            $product->recipe->ingredients()->delete();
            $product->recipe->delete();
        }
        $product->delete();
        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }

    /**
     * Build aggregated recipe for bundled products.
     */
    private function buildBundleRecipe(array $bundleData): array
    {
        $aggregated = [];
        foreach ($bundleData as $bItem) {
            $prodId = $bItem['product_id'] ?? null;
            $bundleQty = (float)($bItem['qty'] ?? 1);
            if (!$prodId) continue;

            $prod = Product::with('recipe.ingredients')->find($prodId);
            if ($prod && $prod->recipe) {
                foreach ($prod->recipe->ingredients as $rIng) {
                    $ingId = $rIng->ingredient_id;
                    $yield = max(1, (float) $prod->recipe->yield_qty);
                    $qty = ((float)$rIng->qty / $yield) * $bundleQty;

                    if (!isset($aggregated[$ingId])) {
                        $aggregated[$ingId] = [
                            'ingredient_id' => $ingId,
                            'qty'           => 0,
                            'type'          => $rIng->type ?? 'main',
                            'notes'         => 'Aggregated from bundle',
                        ];
                    }
                    $aggregated[$ingId]['qty'] += $qty;
                }
            }
        }

        return [
            'name'  => 'Resep Bundling',
            'items' => array_values($aggregated),
        ];
    }

    private function normalizeBundle(array $bundleData, ?int $bundleId = null): array
    {
        if (count($bundleData) === 0) {
            throw ValidationException::withMessages(['bundle_data' => 'Pilih minimal satu menu untuk paket bundling.']);
        }
        $ids = collect($bundleData)->pluck('product_id')->map(fn($id) => (int) $id);
        $products = Product::with('recipe.ingredients.ingredient.unit')->whereIn('id', $ids)->get()->keyBy('id');
        $normalized = [];
        $totalHpp = 0;
        foreach ($bundleData as $item) {
            $productId = (int) $item['product_id'];
            $qty = max(1, (int) $item['qty']);
            $child = $products->get($productId);
            if (!$child || $child->is_bundling || ($bundleId && $productId === $bundleId)) {
                throw ValidationException::withMessages(['bundle_data' => 'Paket bundling hanya dapat berisi menu biasa.']);
            }
            $hpp = (float) $child->cogs;
            $normalized[] = [
                'product_id' => $child->id,
                'product_name' => $child->name,
                'qty' => $qty,
                'price' => (float) $child->selling_price,
                'hpp' => $hpp,
            ];
            $totalHpp += $hpp * $qty;
        }
        return [$normalized, round($totalHpp, 4)];
    }

    private function syncBundleFinancials(Product $product, float $hpp): void
    {
        $price = (float) $product->selling_price;
        $product->update([
            'hpp' => $hpp,
            'gross_margin' => $price > 0 ? ($price - $hpp) / $price : 0,
            'markup_pct' => $hpp > 0 ? ($price - $hpp) / $hpp : 0,
        ]);
    }
}
