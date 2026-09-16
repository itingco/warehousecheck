WAREHOUSE CHECKER V5 - SCAN HOTFIX

Copy isi folder ini ke root project Laravel Anda dan replace file yang sama.

File yang berubah:
- resources/views/checks/show.blade.php
- resources/views/good_transfers/show.blade.php
- resources/views/partials/check-scanner-script.blade.php

Perubahan:
1. Endpoint scan memakai relative/same-origin URL untuk mencegah mixed content HTTPS -> HTTP.
2. Fetch mengirim session cookie same-origin secara explicit.
3. Response non-JSON/HTTP 419/500 tidak lagi berubah menjadi pesan generic.
4. Popup kamera menampilkan error request/server yang sebenarnya.

Setelah copy:
php artisan optimize:clear

Tidak ada SQL/migration baru.
