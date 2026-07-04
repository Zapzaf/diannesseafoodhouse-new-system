<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CheckRegister;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Observers\CheckRegisterObserver;
use App\Policies\CategoryPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\ItemPolicy;
use App\Policies\LocationPolicy;
use App\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);

        CheckRegister::observe(CheckRegisterObserver::class);
    }
}
