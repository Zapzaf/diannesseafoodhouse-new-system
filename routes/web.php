<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BranchManagementController;
use App\Http\Controllers\CategoryManagementController;
use App\Http\Controllers\CostingReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryManagementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuOrderController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\ProductionManagementController;
use App\Http\Controllers\TableManagementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleManagementController;
use App\Http\Controllers\ScrapController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierManagementController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
	Route::get('/login', fn () => view('auth.login'))->name('login');
	Route::post('/login', function (Request $request) {
		$credentials = $request->validate([
			'email' => ['required', 'email'],
			'password' => ['required', 'string'],
		]);

		if (! Auth::attempt($credentials, true)) {
			return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
		}

		$request->session()->regenerate();

		return redirect()->route('dashboard');
	})->name('login.attempt');
});

Route::post('/logout', function (Request $request) {
	Auth::logout();
	$request->session()->invalidate();
	$request->session()->regenerateToken();

	return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
	Route::get('/', fn () => redirect()->route('dashboard'));
	Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

	Route::prefix('inventory')->name('inventory.')->group(function (): void {
		Route::get('/', [InventoryController::class, 'index'])->name('index');
		Route::get('/data', [InventoryController::class, 'data'])->name('data');
		Route::post('/export', [InventoryController::class, 'export'])->name('export');
		Route::get('/branch/{branch}/items', [InventoryController::class, 'itemsByBranch'])->name('branch-items');
		Route::get('/create', [InventoryController::class, 'create'])->name('create');
		Route::post('/', [InventoryController::class, 'store'])->name('store');
		Route::get('/transactions', [InventoryController::class, 'transactions'])->name('transactions');
		Route::get('/{inventory}/edit', [InventoryController::class, 'edit'])->name('edit');
		Route::put('/{inventory}', [InventoryController::class, 'update'])->name('update');
		Route::delete('/{inventory}', [InventoryController::class, 'destroy'])->name('destroy');
		Route::post('/{inventory}/stock-in', [InventoryController::class, 'stockIn']);
		Route::post('/{inventory}/deduct', [InventoryController::class, 'deduct']);
		Route::post('/{inventory}/transfer', [InventoryController::class, 'transfer'])->name('transfer');
	});

	// Items routes (from nav)
	Route::prefix('items')->name('items.')->group(function (): void {
		Route::get('/', [InventoryController::class, 'index'])->name('index');
		Route::get('/create', [InventoryController::class, 'create'])->name('create');
		Route::get('/low-stock', [InventoryController::class, 'lowStock'])->name('low-stock');
	});

	// Transactions routes (from nav)
	Route::prefix('transactions')->name('transactions.')->group(function (): void {
		Route::get('/', [InventoryController::class, 'transactions'])->name('index');
		Route::get('/create', [InventoryController::class, 'transactionCreate'])->name('create');
		Route::post('/store', [InventoryController::class, 'transactionStore'])->name('store');
		Route::get('/pending', [InventoryController::class, 'transactionsPending'])->name('pending');
	});

	Route::prefix('users')->name('users.')->group(function (): void {
		Route::get('/', [UserController::class, 'index'])->name('index');
		Route::get('/data', [UserController::class, 'data'])->name('data');
		Route::get('/create', [UserController::class, 'create'])->name('create');
		Route::post('/', [UserController::class, 'store'])->name('store');
		Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
		Route::put('/{user}', [UserController::class, 'update'])->name('update');
		Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
	});

	Route::get('/account', [AccountController::class, 'index'])->name('account.index');
	Route::put('/account', [AccountController::class, 'update'])->name('account.update');
	Route::get('/profile-photos/{user}', [AccountController::class, 'showProfilePhoto'])->name('profile-photos.show');

	Route::post('/branch/select', function (Request $request) {
		$request->session()->put('selected_branch_id', $request->input('branch_id') ?: null);

		return back();
	})->name('branch.select');

	Route::prefix('categories')->name('categories.')->group(function (): void {
		Route::get('/all', [CategoryManagementController::class, 'allLocationCategories'])->name('all');
		Route::get('/module', [CategoryManagementController::class, 'allLocationCategories'])->name('module');
		Route::get('/create-location', [CategoryManagementController::class, 'createLocation'])->name('locations.create');
		Route::get('/create-category', [CategoryManagementController::class, 'createCategory'])->name('items.create');
		Route::get('/view/{location}', [CategoryManagementController::class, 'view'])->name('view');
		Route::post('/locations', [CategoryManagementController::class, 'storeLocation'])->name('locations.store');
		Route::put('/locations/{location}', [CategoryManagementController::class, 'updateLocation'])->name('locations.update');
		Route::delete('/locations/{location}', [CategoryManagementController::class, 'destroyLocation'])->name('locations.destroy');
		Route::post('/items', [CategoryManagementController::class, 'storeCategory'])->name('items.store');
		Route::put('/items/{category}', [CategoryManagementController::class, 'updateCategory'])->name('items.update');
		Route::delete('/items/{category}', [CategoryManagementController::class, 'destroyCategory'])->name('items.destroy');
	});

	Route::get('/categories/{type}', [CategoryManagementController::class, 'index'])->name('categories.index');
	Route::post('/categories/{type}', [CategoryManagementController::class, 'store'])->name('categories.store');

	Route::prefix('suppliers')->name('suppliers.')->group(function (): void {
		Route::get('/', [SupplierManagementController::class, 'index'])->name('index');
		Route::get('/create', [SupplierManagementController::class, 'create'])->name('create');
		Route::post('/', [SupplierManagementController::class, 'store'])->name('store');
	});

	Route::prefix('deliveries')->name('deliveries.')->group(function (): void {
		Route::get('/', [DeliveryManagementController::class, 'index'])->name('index');
		Route::get('/create', [DeliveryManagementController::class, 'create'])->name('create');
		Route::post('/', [DeliveryManagementController::class, 'store'])->name('store');
		Route::get('/{delivery}', [DeliveryManagementController::class, 'show'])->name('show');
		Route::post('/{delivery}/approve', [DeliveryManagementController::class, 'approve'])->name('approve');
		Route::post('/{delivery}/prices', [DeliveryManagementController::class, 'updatePrices'])->name('prices.update');
	});

	// Delivery routes (from nav — singular)
	Route::prefix('delivery')->name('delivery.')->group(function (): void {
		Route::get('/', [DeliveryManagementController::class, 'index'])->name('index');
		Route::get('/create', [DeliveryManagementController::class, 'create'])->name('create');
		Route::get('/pending', [DeliveryManagementController::class, 'pending'])->name('pending');
	});

	Route::prefix('productions')->name('productions.')->group(function (): void {
		Route::get('/', [ProductionManagementController::class, 'index'])->name('index');
		Route::get('/create', [ProductionManagementController::class, 'create'])->name('create');
		Route::post('/', [ProductionManagementController::class, 'store'])->name('store');
		Route::get('/{production}', [ProductionManagementController::class, 'show'])->name('show');
		Route::post('/{production}/finish', [ProductionManagementController::class, 'finish'])->name('finish');
		Route::post('/{production}/wastage', [ProductionManagementController::class, 'storeWastage'])->name('wastage.store');
	});

	// Production routes (from nav — singular)
	Route::prefix('production')->name('production.')->group(function (): void {
		Route::get('/', [ProductionManagementController::class, 'index'])->name('index');
		Route::get('/processing', [ProductionManagementController::class, 'processing'])->name('processing');
	});

	// Scrap Materials routes (from nav)
	Route::prefix('scrap')->name('scrap.')->group(function (): void {
		Route::get('/', [ScrapController::class, 'index'])->name('index');
	});

    Route::prefix('sales')->name('sales.')->group(function (): void {
        Route::get('/', [SaleManagementController::class, 'index'])->name('index');
        Route::get('/create', [SaleManagementController::class, 'create'])->name('create');
        Route::post('/', [SaleManagementController::class, 'store'])->name('store');
    });

    Route::prefix('menus')->name('menus.')->group(function (): void {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::get('/data', [MenuController::class, 'data'])->name('data');
        Route::get('/create', [MenuController::class, 'create'])->name('create');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::get('/{menu}', [MenuController::class, 'show'])->name('show');
        Route::get('/{menu}/edit', [MenuController::class, 'edit'])->name('edit');
        Route::put('/{menu}', [MenuController::class, 'update'])->name('update');
        Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('menu-categories')->name('menu-categories.')->group(function (): void {
        Route::get('/', [MenuCategoryController::class, 'index'])->name('index');
        Route::get('/data', [MenuCategoryController::class, 'data'])->name('data');
        Route::get('/create', [MenuCategoryController::class, 'create'])->name('create');
        Route::post('/', [MenuCategoryController::class, 'store'])->name('store');
        Route::get('/{menuCategory}/edit', [MenuCategoryController::class, 'edit'])->name('edit');
        Route::put('/{menuCategory}', [MenuCategoryController::class, 'update'])->name('update');
        Route::delete('/{menuCategory}', [MenuCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('menu-orders')->name('menu-orders.')->group(function (): void {
        Route::get('/', [MenuOrderController::class, 'index'])->name('index');
        Route::get('/data', [MenuOrderController::class, 'data'])->name('data');
        Route::get('/create', [MenuOrderController::class, 'create'])->name('create');
        Route::post('/', [MenuOrderController::class, 'store'])->name('store');
        Route::post('/{menuOrder}/items', [MenuOrderController::class, 'storeItem'])->name('items.store');
        Route::delete('/{menuOrder}/items/{item}', [MenuOrderController::class, 'destroyItem'])->name('items.destroy');
        Route::get('/{menuOrder}/billing', [MenuOrderController::class, 'billingReceipt'])->name('billing');
        Route::get('/{menuOrder}', [MenuOrderController::class, 'show'])->name('show');
        Route::get('/{menuOrder}/edit', [MenuOrderController::class, 'edit'])->name('edit');
        Route::put('/{menuOrder}', [MenuOrderController::class, 'update'])->name('update');
        Route::delete('/{menuOrder}', [MenuOrderController::class, 'destroy'])->name('destroy');
        Route::post('/{menuOrder}/payments', [MenuOrderController::class, 'storePayment'])->name('payments.store');
        Route::get('/payments/{payment}/receipt', [MenuOrderController::class, 'paymentReceipt'])->name('payments.receipt');
        Route::post('/{menuOrder}/cancel', [MenuOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{menuOrder}/void', [MenuOrderController::class, 'void'])->name('void');
    });

    Route::prefix('tables')->name('tables.')->group(function (): void {
        Route::get('/', [TableManagementController::class, 'index'])->name('index');
        Route::get('/data', [TableManagementController::class, 'data'])->name('data');
        Route::get('/create', [TableManagementController::class, 'create'])->name('create');
        Route::post('/', [TableManagementController::class, 'store'])->name('store');
        Route::get('/{table}/edit', [TableManagementController::class, 'edit'])->name('edit');
        Route::put('/{table}', [TableManagementController::class, 'update'])->name('update');
        Route::delete('/{table}', [TableManagementController::class, 'destroy'])->name('destroy');
        Route::post('/{table}/assign', [TableManagementController::class, 'assign'])->name('assign');
        Route::post('/{table}/release', [TableManagementController::class, 'release'])->name('release');
    });

    Route::prefix('payments')->name('payments.')->group(function (): void {
        Route::get('/', [PaymentsController::class, 'index'])->name('index');
        Route::get('/data', [PaymentsController::class, 'data'])->name('data');
        Route::get('/create', [PaymentsController::class, 'create'])->name('create');
        Route::post('/', [PaymentsController::class, 'store'])->name('store');
        Route::get('/{payment}', [PaymentsController::class, 'show'])->name('show');
    });

	Route::prefix('branches')->name('branches.')->group(function (): void {
		Route::get('/', [BranchManagementController::class, 'index'])->name('index');
		Route::get('/create', [BranchManagementController::class, 'create'])->name('create');
		Route::post('/', [BranchManagementController::class, 'store'])->name('store');
		Route::get('/{branch}', [BranchManagementController::class, 'show'])->name('show');
		Route::get('/{branch}/edit', [BranchManagementController::class, 'edit'])->name('edit');
		Route::put('/{branch}', [BranchManagementController::class, 'update'])->name('update');
		Route::delete('/{branch}', [BranchManagementController::class, 'destroy'])->name('destroy');
	});

	// Reports routes (from nav)
	Route::prefix('reports')->name('reports.')->group(function (): void {
		Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory.index');
		Route::get('/transactions', [ReportController::class, 'transaction'])->name('transaction.index');
		Route::get('/deliveries', [ReportController::class, 'delivery'])->name('delivery.index');
		Route::get('/costing', [CostingReportController::class, 'index'])->name('costing.index');
		Route::get('/costing/create', [CostingReportController::class, 'create'])->name('costing.create');
		Route::post('/costing', [CostingReportController::class, 'store'])->name('costing.store');
		Route::get('/costing/{costingReport}', [CostingReportController::class, 'show'])->name('costing.show');
		Route::post('/costing/{costingReport}/approve', [CostingReportController::class, 'approve'])->name('costing.approve');
		Route::post('/costing/{costingReport}/reject', [CostingReportController::class, 'reject'])->name('costing.reject');
	});

	// Settings route (from nav)
	Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
	Route::put('/settings/branch', [SettingsController::class, 'updateBranch'])->name('settings.branch.update');

    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ExpenseController::class, 'index'])->name('index');
        Route::post('/import', [\App\Http\Controllers\ExpenseController::class, 'import'])->name('import');
        Route::get('/export/{monthYear}', [\App\Http\Controllers\ExpenseController::class, 'export'])->name('export');

        // Show must be last (wildcard)
        Route::get('/{monthYear}', [\App\Http\Controllers\ExpenseController::class, 'show'])->name('show');
    });
});
