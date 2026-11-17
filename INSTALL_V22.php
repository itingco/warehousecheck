<?php
/** WarehouseCheck manual photo feature, scoped installer, run at Laravel root. */
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR, "artisan tidak ditemukan. Extract ke root Laravel.\n"); exit(1); }
$source = $root.'/patch';
$paths = [
 'routes/web.php', 'resources/views/checks/show.blade.php', 'resources/views/good_transfers/show.blade.php',
];
foreach ($paths as $p) if (!is_file($root.'/'.$p)) { fwrite(STDERR, "Tidak ada $p; pemasangan dibatalkan.\n"); exit(1); }
$routes=file_get_contents($root.'/routes/web.php');
if (!str_contains($routes, "->name('checks.scan')") || !str_contains($routes, "->name('good-transfers.scan')")) {
 fwrite(STDERR, "Struktur route utama tidak cocok; dibatalkan.\n"); exit(1);
}
$routeText = <<<'ROUTE'

// V22: Barang tanpa barcode, qty + foto bukti wajib; scan utama tidak diubah.
Route::middleware('auth')->group(function () {
    Route::get('/checks/{check}/manual-photo/lookup', [\App\Http\Controllers\ManualPhotoCheckController::class, 'invoiceLookup'])->whereNumber('check')->name('checks.manual-photo.lookup');
    Route::post('/checks/{check}/manual-photo', [\App\Http\Controllers\ManualPhotoCheckController::class, 'invoiceStore'])->whereNumber('check')->name('checks.manual-photo.store');
    Route::get('/good-transfers/{transfer}/manual-photo/lookup', [\App\Http\Controllers\ManualPhotoCheckController::class, 'transferLookup'])->whereNumber('transfer')->name('good-transfers.manual-photo.lookup');
    Route::post('/good-transfers/{transfer}/manual-photo', [\App\Http\Controllers\ManualPhotoCheckController::class, 'transferStore'])->whereNumber('transfer')->name('good-transfers.manual-photo.store');
    Route::get('/manual-photo/{type}/{header}/{evidence}/view', [\App\Http\Controllers\ManualPhotoCheckController::class, 'photo'])->whereNumber('header')->whereNumber('evidence')->name('checks.manual-photo.view');
});
ROUTE;
if (!str_contains($routes,"->name('checks.manual-photo.lookup')")) $routes .= $routeText;
$mods = ['routes/web.php'=>$routes];
foreach (['resources/views/checks/show.blade.php','resources/views/good_transfers/show.blade.php'] as $path) {
    $text=file_get_contents($root.'/'.$path);
    if (!str_contains($text, "@include('partials.check-scanner-script'")) {
        fwrite(STDERR, "$path tidak memiliki partial scanner bawaan; dibatalkan.\n");exit(1);
    }
    if (!str_contains($text, "@include('partials.manual-photo-check-v22')")) {
        $marker = '    <div class="scanner-card">';
        if (substr_count($text,$marker)!==1) { fwrite(STDERR,"Marker scanner tidak cocok: $path\n");exit(1); }
        $text=str_replace($marker, "    @include('partials.manual-photo-check-v22')\n".$marker,$text);
    }
    if (!str_contains($text,"@include('partials.manual-photo-evidence-v22')")) {
        $marker="@include('partials.admin-qty-override')";
        if (substr_count($text,$marker)!==1) {fwrite(STDERR,"Marker bukti tidak cocok: $path\n");exit(1);}
        $text=str_replace($marker,"@include('partials.manual-photo-evidence-v22')\n".$marker,$text);
    }
    $mods[$path]=$text;
}
$copy = [
 'app/Http/Controllers/ManualPhotoCheckController.php',
 'database/migrations/2026_11_17_230000_create_warehouse_check_manual_photos.php',
 'public/assets/js/warehouse-manual-photo-v22.js',
 'resources/views/partials/manual-photo-check-v22.blade.php',
 'resources/views/partials/manual-photo-evidence-v22.blade.php',
];
foreach($copy as $path){ if(!is_file($source.'/'.$path)) {fwrite(STDERR,"File patch tidak ditemukan: $path\n");exit(1);} $mods[$path]=file_get_contents($source.'/'.$path); }
foreach($mods as $path=>$value) {
    $target=$root.'/'.$path;
    if(!is_dir(dirname($target)) && !mkdir(dirname($target),0755,true)) {fwrite(STDERR,"Tidak bisa membuat folder $path\n");exit(1);}
    if(is_file($target) && file_get_contents($target)===$value) {echo "Tidak berubah: $path\n";continue;}
    if(is_file($target) && !is_file($target.'.before-v22')) copy($target,$target.'.before-v22');
    if(file_put_contents($target,$value)===false){fwrite(STDERR,"Gagal menulis: $path\n");exit(1);}
    echo "Diperbarui: $path\n";
}
echo "Selesai. Jalankan php artisan migrate lalu php artisan optimize:clear.\n";
