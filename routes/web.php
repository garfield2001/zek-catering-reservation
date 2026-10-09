<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\DishController;
use App\Http\Controllers\Admin\Catalog\FamilyOrderDefaultController;
use App\Http\Controllers\Admin\Catalog\OfferingController;
use App\Http\Controllers\Admin\Catalog\OfferingOptionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/admin', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/admin/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/admin/staff/create', [StaffController::class, 'create']);
    Route::post('/admin/staff', [StaffController::class, 'store']);
    Route::get('/admin/staff/{user}/edit', [StaffController::class, 'edit']);
    Route::put('/admin/staff/{user}', [StaffController::class, 'update']);
    Route::get('/admin/staff/{user}/permissions', [PermissionController::class, 'edit']);
    Route::put('/admin/staff/{user}/permissions', [PermissionController::class, 'update']);
    Route::get('/admin/audit', AuditController::class)->name('audit.index');
    Route::prefix('admin/catalog')->name('catalog.')->scopeBindings()->group(function () {
        Route::resource('categories', CategoryController::class)->except('destroy');
        Route::resource('dishes', DishController::class)->except('destroy');
        Route::get('dishes/{dish}/offerings/create', [OfferingController::class, 'create'])->name('offerings.create');
        Route::post('dishes/{dish}/offerings', [OfferingController::class, 'store'])->name('offerings.store');
        Route::get('dishes/{dish}/offerings/{offering}', [OfferingController::class, 'show'])->name('offerings.show');
        Route::get('dishes/{dish}/offerings/{offering}/edit', [OfferingController::class, 'edit'])->name('offerings.edit');
        Route::put('dishes/{dish}/offerings/{offering}', [OfferingController::class, 'update'])->name('offerings.update');
        Route::get('dishes/{dish}/offerings/{offering}/options/create', [OfferingOptionController::class, 'create'])->name('options.create');
        Route::post('dishes/{dish}/offerings/{offering}/options', [OfferingOptionController::class, 'store'])->name('options.store');
        Route::get('dishes/{dish}/offerings/{offering}/options/{option}/edit', [OfferingOptionController::class, 'edit'])->name('options.edit');
        Route::put('dishes/{dish}/offerings/{offering}/options/{option}', [OfferingOptionController::class, 'update'])->name('options.update');
        Route::get('families', [FamilyOrderDefaultController::class, 'index'])->name('families.index');
        Route::put('families/{family}/defaults', [FamilyOrderDefaultController::class, 'update'])->name('families.defaults.update');
    });
});
