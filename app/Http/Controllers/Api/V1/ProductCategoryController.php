<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ProductCategory::withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        return response()->json([
            'data'       => $categories,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150', 'unique:product_categories,name'],
            'color'       => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $category = ProductCategory::create($data);

        return response()->json(['message' => 'Kategori produk berhasil dibuat.', 'category' => $category], 201);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150', 'unique:product_categories,name,' . $productCategory->id],
            'color'       => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
        ]);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $productCategory->update($data);

        return response()->json(['message' => 'Kategori produk berhasil diperbarui.', 'category' => $productCategory]);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        if ($productCategory->products()->count() > 0) {
            return response()->json([
                'message' => 'Kategori tidak dapat dihapus karena masih digunakan oleh ' . $productCategory->products()->count() . ' produk.'
            ], 422);
        }

        $productCategory->delete();

        return response()->json(['message' => 'Kategori produk berhasil dihapus.']);
    }
}
