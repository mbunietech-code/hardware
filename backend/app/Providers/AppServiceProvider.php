<?php

namespace App\Providers;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\SyncReceipt;
use App\Models\User;
use App\Support\ActionContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActionContext::class, fn () => new ActionContext);
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        Relation::enforceMorphMap([
            'sale' => Sale::class,
            'purchase' => Purchase::class,
            'stock_adjustment' => StockAdjustment::class,
            'product' => Product::class,
            'debt' => Debt::class,
            'daily_session' => DailySession::class,
            'sync_receipt' => SyncReceipt::class,
            'user' => User::class,
        ]);

        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('permission', fn (User $user, string $permission) => $user->hasPermission($permission));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('login')));
    }
}
