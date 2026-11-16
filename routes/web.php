<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LaundryController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/lacak', [LaundryController::class, 'track'])->middleware('throttle:30,1')->name('track');
Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json(['status' => 'ok', 'database' => 'connected']);
    } catch (Throwable $e) {
        report($e);

        return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
    }
})->withoutMiddleware('web')->name('health');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/admin', [LaundryController::class, 'dashboard'])->name('dashboard');
    Route::get('/admin/orders/create', [LaundryController::class, 'form'])->name('orders.create');
    Route::post('/admin/orders', [LaundryController::class, 'store'])->name('orders.store');
    Route::get('/admin/orders/{order}', [LaundryController::class, 'show'])->name('orders.show');
    Route::get('/admin/orders/{order}/edit', [LaundryController::class, 'form'])->name('orders.edit');
    Route::put('/admin/orders/{order}', [LaundryController::class, 'update'])->name('orders.update');
    Route::patch('/admin/orders/{order}/status', [LaundryController::class, 'status'])->name('orders.status');
    Route::post('/admin/orders/{order}/payment', [LaundryController::class, 'pay'])->name('orders.pay');
    Route::get('/admin/services', [LaundryController::class, 'services'])->name('services');
    Route::post('/admin/services', [LaundryController::class, 'saveService'])->name('services.store');
    Route::put('/admin/services/{service}', [LaundryController::class, 'saveService'])->name('services.update');
    Route::delete('/admin/services/{service}', [LaundryController::class, 'deleteService'])->name('services.delete');
    Route::get('/admin/report', [LaundryController::class, 'report'])->name('report');
});
