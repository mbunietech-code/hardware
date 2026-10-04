<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CatalogService;
use App\Services\PartyService;
use Illuminate\Http\Request;

class CatalogController extends ApiController
{
    public function __construct(private CatalogService $catalog, private PartyService $parties) {}

    public function categories()
    {
        return response()->json(['data' => Category::orderBy('name')->get()]);
    }

    public function storeCategory(Request $request)
    {
        return response()->json(['data' => $this->catalog->saveCategory($request->all(), $request->user())], 201);
    }

    public function expenseCategories()
    {
        return response()->json(['data' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function products(Request $request)
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? $request->integer('shop_id') ?: null : $user->shop_id;

        return Product::with('category:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', $request->search.'%')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->when($shopId, fn ($q) => $q->withSum(['stockBalances as stock' => fn ($b) => $b->where('shop_id', $shopId)], 'quantity'))
            ->orderBy('name')->paginate($this->perPage($request));
    }

    public function showProduct(Product $product)
    {
        return response()->json(['data' => $product->load('category', 'stockBalances.shop:id,name')]);
    }

    public function storeProduct(Request $request)
    {
        return response()->json(['data' => $this->catalog->saveProduct($request->all(), $request->user())], 201);
    }

    public function updateProduct(Request $request, Product $product)
    {
        return response()->json(['data' => $this->catalog->saveProduct($request->all() + $product->only(['code', 'name', 'unit', 'cost_price', 'selling_price']), $request->user(), $product)]);
    }

    public function customers(Request $request)
    {
        return Customer::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->paginate($this->perPage($request));
    }

    public function storeCustomer(Request $request)
    {
        return response()->json(['data' => $this->parties->createCustomer($request->all(), $request->user())], 201);
    }

    public function suppliers(Request $request)
    {
        return Supplier::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->paginate($this->perPage($request));
    }

    public function storeSupplier(Request $request)
    {
        return response()->json(['data' => $this->parties->createSupplier($request->all(), $request->user())], 201);
    }
}
