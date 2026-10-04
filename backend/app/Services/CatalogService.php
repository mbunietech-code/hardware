<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogService
{
    use Concerns;

    public function saveProduct(array $data, User $user, ?Product $product = null): Product
    {
        $this->requirePermission($user, 'manage_products', __('You are not allowed to manage products.'));
        $data = Validator::make($data, [
            'category_id' => 'nullable|integer|exists:categories,id',
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product?->id)],
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:30',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'local_uuid' => 'nullable|uuid',
        ], ['code.unique' => 'This product code is already used by another product.'])->validate();

        if (! $product && ($existing = $this->findByLocalUuid(Product::class, $data))) {
            return $existing;
        }
        $data['reorder_level'] ??= 0;

        if ($product) {
            $before = $product->toArray();
            $product->update($data + ['updated_by' => $user->id]);
            AuditLogger::log('product.updated', $product, $before, $product->toArray());

            return $product;
        }

        $product = Product::create($data + ['created_by' => $user->id, 'updated_by' => $user->id]);
        AuditLogger::log('product.created', $product);

        return $product;
    }

    public function saveCategory(array $data, User $user, ?Category $category = null): Category
    {
        $this->requirePermission($user, 'manage_products', __('You are not allowed to manage categories.'));
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category?->id)],
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        if ($category) {
            $before = $category->toArray();
            $category->update($data);
            AuditLogger::log('category.updated', $category, $before, $category->toArray());

            return $category;
        }
        $category = Category::create($data);
        AuditLogger::log('category.created', $category);

        return $category;
    }
}
