<?php

namespace App\Http\Controllers\Web;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\CatalogService;
use App\Services\PartyService;
use Illuminate\Http\Request;

class CatalogController extends WebController
{
    public function __construct(private CatalogService $catalog, private PartyService $parties) {}

    public function products(Request $request)
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? ($request->integer('shop_id') ?: null) : $user->shop_id;

        return view('catalog.products', [
            'products' => Product::with('category:id,name')
                ->withSum(['stockBalances as stock' => fn ($q) => $q->when($shopId, fn ($b) => $b->where('shop_id', $shopId))], 'quantity')
                ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%')))
                ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
                ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
                ->orderBy('name')->paginate(40)->withQueryString(),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'shops' => $this->shopOptions($request),
            'shopId' => $shopId,
        ]);
    }

    public function createProduct()
    {
        abort_unless(auth()->user()->hasPermission('manage_products'), 403);

        return view('catalog.product-form', ['product' => new Product(['unit' => 'pcs', 'is_active' => true]), 'categories' => Category::where('is_active', true)->orderBy('name')->pluck('name', 'id')]);
    }

    public function storeProduct(Request $request)
    {
        $product = $this->catalog->saveProduct($request->all() + ['is_active' => $request->boolean('is_active')], $request->user());

        return redirect()->route('products.show', $product)->with('success', __('Product created. Use a purchase or stock adjustment to add opening stock.'));
    }

    public function showProduct(Request $request, Product $product)
    {
        $shopIds = $request->user()->accessibleShopIds();

        return view('catalog.product', [
            'product' => $product->load('category'),
            'balances' => $product->stockBalances()->with('shop')->when($shopIds !== null, fn ($q) => $q->whereIn('shop_id', $shopIds))->get(),
            'movements' => StockMovement::with('shop:id,name', 'user:id,name')->where('product_id', $product->id)
                ->when($shopIds !== null, fn ($q) => $q->whereIn('shop_id', $shopIds))->latest('id')->limit(30)->get(),
        ]);
    }

    public function editProduct(Product $product)
    {
        abort_unless(auth()->user()->hasPermission('manage_products'), 403);

        return view('catalog.product-form', ['product' => $product, 'categories' => Category::orderBy('name')->pluck('name', 'id')]);
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->catalog->saveProduct($request->all() + ['is_active' => $request->boolean('is_active')], $request->user(), $product);

        return redirect()->route('products.show', $product)->with('success', __('Product updated.'));
    }

    public function categories()
    {
        abort_unless(auth()->user()->hasPermission('manage_products'), 403);

        return view('catalog.categories', ['categories' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function storeCategory(Request $request)
    {
        $this->catalog->saveCategory($request->all(), $request->user());

        return back()->with('success', __('Category saved.'));
    }

    public function updateCategory(Request $request, Category $category)
    {
        $this->catalog->saveCategory($request->all() + ['is_active' => $request->boolean('is_active')], $request->user(), $category);

        return back()->with('success', __('Category updated.'));
    }

    public function customers(Request $request)
    {
        return view('catalog.parties', [
            'kind' => 'customers', 'title' => 'Customers',
            'parties' => Customer::withSum(['debts as owing' => fn ($q) => $q->whereIn('status', ['open', 'partial'])], 'balance')
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%'))
                ->orderBy('name')->paginate(40)->withQueryString(),
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $this->parties->createCustomer($request->all(), $request->user());

        return back()->with('success', __('Customer added.'));
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $this->parties->update($customer, $request->all() + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Customer updated.'));
    }

    public function suppliers(Request $request)
    {
        return view('catalog.parties', [
            'kind' => 'suppliers', 'title' => 'Suppliers',
            'parties' => Supplier::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
                ->orderBy('name')->paginate(40)->withQueryString(),
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $this->parties->createSupplier($request->all(), $request->user());

        return back()->with('success', __('Supplier added.'));
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $this->parties->update($supplier, $request->all() + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Supplier updated.'));
    }
}
