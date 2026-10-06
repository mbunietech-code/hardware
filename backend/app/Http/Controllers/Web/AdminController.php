<?php

namespace App\Http\Controllers\Web;

use App\Models\Business;
use App\Models\Device;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends WebController
{
    public function shops()
    {
        return view('admin.shops', ['shops' => Shop::withCount('users')->orderBy('name')->get()]);
    }

    public function storeShop(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|alpha_dash|unique:shops,code',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);
        $shop = Shop::create($data + ['business_id' => Business::value('id'), 'is_active' => true]);
        // Every product gets a zero balance row so the new shop appears in stock reports.
        Product::pluck('id')->each(fn ($pid) => StockBalance::firstOrCreate(['shop_id' => $shop->id, 'product_id' => $pid]));
        AuditLogger::log('shop.created', $shop);

        return back()->with('success', __('Shop created.'));
    }

    public function updateShop(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]) + ['is_active' => $request->boolean('is_active')];
        $before = $shop->toArray();
        $shop->update($data);
        AuditLogger::log($shop->is_active ? 'shop.updated' : 'shop.deactivated', $shop, $before, $shop->toArray());

        return back()->with('success', __('Shop updated.'));
    }

    public function users(Request $request)
    {
        return view('admin.users', ['users' => User::with('shop:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->paginate(40)->withQueryString()]);
    }

    public function createUser()
    {
        return view('admin.user-form', ['user' => new User(['role' => User::ROLE_SHOP_ADMIN, 'is_active' => true]), 'shops' => Shop::orderBy('name')->pluck('name', 'id')]);
    }

    public function editUser(User $user)
    {
        return view('admin.user-form', ['user' => $user, 'shops' => Shop::orderBy('name')->pluck('name', 'id')]);
    }

    public function storeUser(Request $request)
    {
        $user = User::create($this->userData($request) + ['business_id' => Business::value('id')]);
        AuditLogger::log('user.created', $user);

        return redirect()->route('users.index')->with('success', __('User created.'));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $this->userData($request, $user);
        if ($user->id === $request->user()->id && (! $data['is_active'] || $data['role'] !== User::ROLE_SUPER_ADMIN)) {
            return back()->withErrors(['role' => 'You cannot deactivate or demote your own account.']);
        }
        $before = $user->toArray();
        $user->update($data);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }
        AuditLogger::log('user.updated', $user, $before, $user->toArray());

        return redirect()->route('users.index')->with('success', __('User updated.'));
    }

    private function userData(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'required_without:phone', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'shop_id' => 'nullable|required_if:role,shop_admin|exists:shops,id',
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['permissions'] = collect(User::SHOP_PERMISSIONS)->keys()->mapWithKeys(fn ($p) => [$p => $request->boolean("permissions.$p")])->all();
        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] === User::ROLE_SUPER_ADMIN) {
            $data['shop_id'] = $data['shop_id'] ?? null;
        }

        return $data;
    }

    public function expenseCategories()
    {
        return view('admin.expense-categories', ['categories' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function storeExpenseCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:expense_categories,name']);
        $category = ExpenseCategory::create($data + ['reduces_profit' => $request->boolean('reduces_profit'), 'is_active' => true]);
        AuditLogger::log('expense_category.created', $category);

        return back()->with('success', __('Expense category added.'));
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('expense_categories')->ignore($expenseCategory->id)]]);
        $before = $expenseCategory->toArray();
        $expenseCategory->update($data + ['reduces_profit' => $request->boolean('reduces_profit'), 'is_active' => $request->boolean('is_active')]);
        AuditLogger::log('expense_category.updated', $expenseCategory, $before, $expenseCategory->toArray());

        return back()->with('success', __('Expense category updated.'));
    }

    public function settings()
    {
        return view('admin.settings', ['values' => Settings::all(), 'groups' => collect(Settings::DEFINITIONS)->groupBy('group', true)]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'allocation_primary_percent' => 'required|integer|min:0|max:100',
            'closing_reminder_hour' => 'required|integer|min:0|max:23',
            'debt_reminder_days' => 'required|integer|min:0|max:60',
            'business_name' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
        ]);
        $before = Settings::all();
        foreach (Settings::DEFINITIONS as $key => $def) {
            Settings::set($key, $def['type'] === 'bool' ? $request->boolean($key) : $request->input($key, $def['default']));
        }
        Business::query()->update(['name' => Settings::get('business_name'), 'currency' => Settings::get('currency')]);
        AuditLogger::log('settings.updated', null, $before, Settings::all());

        return back()->with('success', __('Settings saved.'));
    }

    public function devices()
    {
        return view('admin.devices', ['devices' => Device::with('user:id,name')->latest('last_seen_at')->paginate(40)]);
    }

    public function toggleDevice(Device $device)
    {
        $device->update(['is_revoked' => ! $device->is_revoked]);
        if ($device->is_revoked) {
            $device->user->tokens()->where('name', $device->device_id)->delete();
        }
        AuditLogger::log($device->is_revoked ? 'device.revoked' : 'device.restored', $device->user, null, ['device_id' => $device->device_id]);

        return back()->with('success', $device->is_revoked ? 'Device revoked.' : 'Device restored.');
    }

    public function backups(BackupService $backups)
    {
        return view('admin.backups', ['backups' => $backups->list()]);
    }

    public function runBackup(BackupService $backups)
    {
        try {
            $file = $backups->run();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('success', __('Backup created: :file', ['file' => basename($file)]));
    }

    public function downloadBackup(string $name, BackupService $backups)
    {
        $path = $backups->directory().'/'.basename($name);
        abort_unless(is_file($path), 404);
        AuditLogger::log('backup.downloaded', null, null, ['file' => basename($path)]);

        return response()->download($path);
    }
}
