<?php
/** Minimal scoped patch: run from warehousecheck Laravel root. */
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan installer dari root Laravel (artisan tidak ditemukan).\n"); exit(1); }
$source = $root.'/patch';
$target = $root.'/resources/views/partials/check-scanner-script.blade.php';
if (!is_file($target)) { fwrite(STDERR, "File scanner bawaan tidak ditemukan. Tidak ada perubahan.\n"); exit(1); }
$old = file_get_contents($target);
if (str_contains($old, 'WarehouseFastCameraV21')) { echo "Scanner V21 sudah terpasang.\n"; exit(0); }
$library = <<<'HTML'
<script src="https://unpkg.com/@zxing/browser@0.1.5/umd/index.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/warehouse-fast-camera-v21.js') }}?v=21.1"></script>
HTML;
$replaced = preg_replace('~\A<script\b[\s\S]*?</script>~', $library, $old, 1, $numA);
if ($numA !== 1) { fwrite(STDERR, "Gagal menemukan include scanner lama. Batal tanpa perubahan.\n"); exit(1); }
$block = file_get_contents($source.'/SCANNER_CAMERA_V21_INLINE.txt');
$pattern = '~    async function startCamera\(\) \{[\s\S]*?(?=    openCameraBtn\.addEventListener\(\x27click\x27, openCameraModal\);)~';
$new = preg_replace_callback($pattern, fn () => $block."\n", $replaced, 1, $numB);
if ($numB !== 1 || !str_contains($new,'async function submitBarcode(') || !str_contains($new, 'openOverrideByLine')) {
    fwrite(STDERR, "Struktur scanner tidak cocok dengan main GitHub. Batal agar fitur lain aman.\n"); exit(1);
}
$routesPath = $root.'/routes/web.php';
$routes = file_get_contents($routesPath);
if (!str_contains($routes, "->name('checks.scan')") || !str_contains($routes, "->name('good-transfers.scan')")) {
    fwrite(STDERR, "Route cek dokumen tidak sesuai. Batal.\n"); exit(1);
}
$routeAddition = <<<'ROUTES'

// V21: read-only camera confirmation, existing scan endpoints unchanged.
Route::middleware('auth')->group(function () {
    Route::post('/checks/{check}/camera-preview', [\App\Http\Controllers\CameraScanPreviewController::class, 'invoice'])
        ->whereNumber('check')->name('checks.camera-preview');
    Route::post('/good-transfers/{transfer}/camera-preview', [\App\Http\Controllers\CameraScanPreviewController::class, 'transfer'])
        ->whereNumber('transfer')->name('good-transfers.camera-preview');
});
ROUTES;
$routesNew = str_contains($routes, "->name('checks.camera-preview')") ? $routes : $routes.$routeAddition;
$files = [
    $target => $new,
    $routesPath => $routesNew,
    $root.'/app/Http/Controllers/CameraScanPreviewController.php' => file_get_contents($source.'/app/Http/Controllers/CameraScanPreviewController.php'),
    $root.'/config/warehouse_scanner_ai.php' => file_get_contents($source.'/config/warehouse_scanner_ai.php'),
    $root.'/public/assets/js/warehouse-fast-camera-v21.js' => file_get_contents($source.'/public/assets/js/warehouse-fast-camera-v21.js'),
];
foreach ($files as $path => $contents) { if (!is_dir(dirname($path))) mkdir(dirname($path),0755,true); }
foreach ($files as $path => $contents) {
    if (is_file($path)) copy($path, $path.'.before-scanner-v21');
    if (file_put_contents($path, $contents) === false) { fwrite(STDERR, "Tidak bisa menulis $path\n"); exit(1); }
    echo 'Updated '.substr($path,strlen($root)+1)."\n";
}
echo "Selesai. Jalankan php artisan optimize:clear dan php artisan route:list --name=camera-preview\n";
