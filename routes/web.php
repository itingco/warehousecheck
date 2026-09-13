<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupervisorOtpController;
use App\Http\Controllers\WarehouseCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get(
        '/login',
        [AuthController::class, 'showLogin']
    )->name('login');

    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->name('login.submit');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    )->name('logout');

    Route::get(
        '/',
        [DashboardController::class, 'index']
    )->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Checker / Admin
    |--------------------------------------------------------------------------
    |
    | /checks/new HARUS berada sebelum /checks/{check}
    |
    */

    Route::middleware('role:checker,admin')->group(function () {
        Route::get(
            '/checks/new',
            [WarehouseCheckController::class, 'create']
        )->name('checks.create');

        Route::post(
            '/checks/load',
            [WarehouseCheckController::class, 'loadInvoice']
        )->name('checks.load');

        Route::post(
            '/checks/{check}/scan',
            [WarehouseCheckController::class, 'scan']
        )
            ->whereNumber('check')
            ->name('checks.scan');

        Route::post(
            '/checks/{check}/details/{detail}/override',
            [WarehouseCheckController::class, 'override']
        )
            ->whereNumber('check')
            ->whereNumber('detail')
            ->name('checks.override');

        Route::post(
            '/checks/{check}/finalize',
            [WarehouseCheckController::class, 'finalize']
        )
            ->whereNumber('check')
            ->name('checks.finalize');
    });

    /*
    |--------------------------------------------------------------------------
    | Check Detail
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/checks/{check}',
        [WarehouseCheckController::class, 'show']
    )
        ->whereNumber('check')
        ->name('checks.show');

    /*
    |--------------------------------------------------------------------------
    | Supervisor
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:supervisor,admin')->group(function () {
        Route::get(
            '/supervisor/otp',
            [SupervisorOtpController::class, 'index']
        )->name('supervisor.otp');

        Route::post(
            '/supervisor/otp/generate',
            [SupervisorOtpController::class, 'generate']
        )->name('supervisor.otp.generate');

        Route::post(
            '/supervisor/otp/revoke',
            [SupervisorOtpController::class, 'revoke']
        )->name('supervisor.otp.revoke');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get(
            '/admin/users',
            [AdminUserController::class, 'index']
        )->name('admin.users');

        Route::post(
            '/admin/users',
            [AdminUserController::class, 'store']
        )->name('admin.users.store');

        Route::post(
            '/admin/users/{user}/toggle',
            [AdminUserController::class, 'toggle']
        )
            ->whereNumber('user')
            ->name('admin.users.toggle');
    });
});
