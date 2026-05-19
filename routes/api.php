<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WastageReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials.'], 422);
    }

    $user = $request->user();
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
    ]);
});

Route::name('api.')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', function (Request $request) {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    });

    Route::apiResource('branches', BranchController::class)->only(['index', 'store', 'update']);

    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('locations', LocationController::class)->only(['index', 'store']);
    Route::post('locations/{id}/categories', [CategoryController::class, 'store']);

    Route::apiResource('items', ItemController::class);

    Route::apiResource('deliveries', DeliveryController::class)->only(['index', 'store', 'show']);
    Route::post('deliveries/{delivery}/approve', [DeliveryController::class, 'approve']);

    Route::apiResource('productions', ProductionController::class)->only(['index', 'store', 'show']);
    Route::post('productions/{production}/finish', [ProductionController::class, 'finish']);
    Route::post('productions/{id}/wastage', [WastageReportController::class, 'store']);

    Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);

    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
});
