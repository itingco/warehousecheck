<?php

use App\Http\Controllers\AdminQtyOverrideController;
use App\Http\Controllers\AdminScanErrorController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoodTransferCheckController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Controllers\SupervisorOtpController;
use App\Http\Controllers\WarehouseCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/account/password', [ProfilePasswordController::class, 'edit'])
        ->name('account.password.edit');

    Route::put('/account/password', [ProfilePasswordController::class, 'update'])
        ->name('account.password.update');

    /*
    |--------------------------------------------------------------------------
    | Operational checking
    |--------------------------------------------------------------------------
    | Semua user aktif selain admin dapat menjadi checker. Picker dipilih per
    | dokumen dan tidak boleh sama dengan user yang sedang menjadi checker.
    | Controller + service tetap melakukan validasi server-side.
    */
    Route::get('/checks/new', [WarehouseCheckController::class, 'create'])
        ->name('checks.create');

    Route::post('/checks/load', [WarehouseCheckController::class, 'loadDocument'])
        ->name('checks.load');

    Route::post('/checks/{check}/scan', [WarehouseCheckController::class, 'scan'])
        ->whereNumber('check')
        ->name('checks.scan');

    Route::post('/checks/{check}/details/{detail}/override', [WarehouseCheckController::class, 'override'])
        ->whereNumber('check')
        ->whereNumber('detail')
        ->name('checks.override');

    Route::post('/checks/{check}/finalize', [WarehouseCheckController::class, 'finalize'])
        ->whereNumber('check')
        ->name('checks.finalize');

    Route::get('/checks/{check}', [WarehouseCheckController::class, 'show'])
        ->whereNumber('check')
        ->name('checks.show');

    /*
    |--------------------------------------------------------------------------
    | Good Transfer Check
    |--------------------------------------------------------------------------
    */
    Route::redirect('/good-transfers', '/?document_type=good_transfer')
        ->name('good-transfers.index');

    Route::redirect(
        '/good-transfers/new',
        '/checks/new?document_type=good_transfer'
    )->name('good-transfers.create');

    Route::post('/good-transfers/load', [GoodTransferCheckController::class, 'loadTransfer'])
        ->name('good-transfers.load');

    Route::post('/good-transfers/{transfer}/scan', [GoodTransferCheckController::class, 'scan'])
        ->whereNumber('transfer')
        ->name('good-transfers.scan');

    Route::post('/good-transfers/{transfer}/details/{detail}/override', [GoodTransferCheckController::class, 'override'])
        ->whereNumber('transfer')
        ->whereNumber('detail')
        ->name('good-transfers.override');

    Route::post('/good-transfers/{transfer}/finalize', [GoodTransferCheckController::class, 'finalize'])
        ->whereNumber('transfer')
        ->name('good-transfers.finalize');

    Route::get('/good-transfers/{transfer}', [GoodTransferCheckController::class, 'show'])
        ->whereNumber('transfer')
        ->name('good-transfers.show');

    /*
    |--------------------------------------------------------------------------
    | Supervisor
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:supervisor,admin')->group(function () {
        Route::get('/supervisor/otp', [SupervisorOtpController::class, 'index'])
            ->name('supervisor.otp');

        Route::post('/supervisor/otp/generate', [SupervisorOtpController::class, 'generate'])
            ->name('supervisor.otp.generate');

        Route::post('/supervisor/otp/revoke', [SupervisorOtpController::class, 'revoke'])
            ->name('supervisor.otp.revoke');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/scan-errors', [AdminScanErrorController::class, 'index'])
            ->name('admin.scan-errors');

        Route::get('/admin/qty-overrides', [AdminQtyOverrideController::class, 'index'])
            ->name('admin.qty-overrides');

        Route::post(
            '/admin/invoice-checks/{check}/details/{detail}/qty-override',
            [AdminQtyOverrideController::class, 'invoice']
        )
            ->whereNumber('check')
            ->whereNumber('detail')
            ->name('admin.invoice.qty-override');

        Route::post(
            '/admin/good-transfers/{transfer}/details/{detail}/qty-override',
            [AdminQtyOverrideController::class, 'goodTransfer']
        )
            ->whereNumber('transfer')
            ->whereNumber('detail')
            ->name('admin.good-transfer.qty-override');

        Route::get('/admin/users', [AdminUserController::class, 'index'])
            ->name('admin.users');

        Route::post('/admin/users', [AdminUserController::class, 'store'])
            ->name('admin.users.store');

        Route::post('/admin/users/{user}/toggle', [AdminUserController::class, 'toggle'])
            ->whereNumber('user')
            ->name('admin.users.toggle');
    });
});
