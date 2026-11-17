<?php

namespace App\Http\Controllers;

use App\Models\GoodTransferCheckDetail;
use App\Models\GoodTransferCheckHeader;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualPhotoCheckController extends Controller
{
    public function invoiceLookup(Request $request, WarehouseCheckHeader $check): JsonResponse
    {
        return $this->lookup($request, $check, 'invoice');
    }

    public function transferLookup(Request $request, GoodTransferCheckHeader $transfer): JsonResponse
    {
        return $this->lookup($request, $transfer, 'transfer');
    }

    public function invoiceStore(Request $request, WarehouseCheckHeader $check): JsonResponse
    {
        return $this->store($request, $check, 'invoice');
    }

    public function transferStore(Request $request, GoodTransferCheckHeader $transfer): JsonResponse
    {
        return $this->store($request, $transfer, 'transfer');
    }

    private function assertChecker(Request $request, $header): void
    {
        abort_unless($request->user() && (int) $header->checker_user_id === (int) $request->user()->id
            && (bool) $request->user()->is_active && $request->user()->role !== 'admin'
            && $header->status === 'DRAFT', 403, 'Hanya checker pemilik dokumen DRAFT yang dapat mencatat barang.');
    }

    private function lookup(Request $request, $header, string $type): JsonResponse
    {
        $this->assertChecker($request, $header);
        $data = $request->validate(['item_code' => ['required', 'string', 'max:100']]);
        $code = strtoupper(trim($data['item_code']));
        $details = $header->details()->whereRaw('UPPER(LTRIM(RTRIM(item_code))) = ?', [$code])
            ->orderBy('source_detail_id')->get();
        if ($details->isEmpty()) {
            throw ValidationException::withMessages(['item_code' => 'Kode barang tidak ada di dokumen yang sedang diperiksa.']);
        }
        $available = $details->filter(fn ($line) => $line->status === 'PENDING' && $line->expected_base_qty !== null)
            ->sum(fn ($line) => max(0, (float) $line->expected_base_qty - (float) $line->scanned_base_qty));
        return response()->json([
            'ok' => true, 'item_code' => $details->first()->item_code,
            'item_name' => $details->first()->item_name,
            'uom_code' => $details->first()->uom_code,
            'remaining_qty' => round($available, 4),
            'message' => $available > 0 ? null : 'Tidak ada qty tersisa; mungkin memerlukan Supervisor Override.',
        ]);
    }

    private function store(Request $request, $header, string $type): JsonResponse
    {
        $this->assertChecker($request, $header);
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
            'photo' => ['required', 'string', 'max:8500000'],
        ]);
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $data['photo'], $match)) {
            throw ValidationException::withMessages(['photo' => 'Foto wajib diambil langsung dari kamera.']);
        }
        $bytes = base64_decode($match[2], true);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024 || strlen($bytes) < 100 || !@getimagesizefromstring($bytes)) {
            throw ValidationException::withMessages(['photo' => 'Foto rusak atau melebihi 5 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photo' => 'Tipe foto tidak diizinkan.']);
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $code = strtoupper(trim($data['item_code']));
        $qty = (float) $data['qty']; // Qty dalam base/smallest UOM, bukan jumlah kemasan.
        $path = 'warehousecheck/manual-evidence/'.($type === 'invoice' ? 'invoice' : 'transfer').'/'.$header->id.'/'.(string) \Illuminate\Support\Str::uuid().'.'.$ext;
        if (!Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan.']);
        }
        try {
            $response = DB::connection('apphub')->transaction(function () use ($request, $header, $type, $code, $qty, $path, $mime) {
                $headerClass = $type === 'invoice' ? WarehouseCheckHeader::class : GoodTransferCheckHeader::class;
                $lineClass = $type === 'invoice' ? WarehouseCheckDetail::class : GoodTransferCheckDetail::class;
                $lockedHeader = $headerClass::query()->whereKey($header->id)->lockForUpdate()->firstOrFail();
                $this->assertChecker($request, $lockedHeader);
                $lines = $lineClass::query()->where('check_header_id', $lockedHeader->id)
                    ->whereRaw('UPPER(LTRIM(RTRIM(item_code))) = ?', [$code])
                    ->orderBy('source_detail_id')->lockForUpdate()->get();
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['item_code' => 'Barang tidak termasuk pada dokumen.']);
                }
                $remaining = $qty;
                $changes = [];
                foreach ($lines as $line) {
                    if ($line->status !== 'PENDING' || $line->expected_base_qty === null) continue;
                    $space = (float) $line->expected_base_qty - (float) $line->scanned_base_qty;
                    if ($space <= 0.00001) continue;
                    $part = min($space, $remaining);
                    if ($part <= 0) continue;
                    $changes[] = [$line, $part];
                    $remaining = round($remaining - $part, 4);
                    if ($remaining <= 0.00001) break;
                }
                if ($remaining > 0.00001) {
                    throw ValidationException::withMessages(['qty' => 'Qty melebihi sisa dokumen atau ratio tidak tersedia. Gunakan Supervisor Override bila diperlukan.']);
                }
                $last = null;
                foreach ($changes as [$line, $part]) {
                    $newQty = (float) $line->scanned_base_qty + $part;
                    $complete = abs($newQty - (float) $line->expected_base_qty) <= 0.00001;
                    $line->update(['scanned_base_qty' => $newQty, 'status' => $complete ? 'OK' : 'PENDING', 'completed_at' => $complete ? now() : null]);
                    // Dedicated evidence rows keep original barcode scan logs untouched.
                    DB::connection('apphub')->table('warehouse_check_manual_photos')->insert([
                        'document_type' => $type, 'check_header_id' => $lockedHeader->id,
                        'check_detail_id' => $line->id, 'item_code' => $line->item_code,
                        'qty_base' => $part, 'checker_user_id' => $request->user()->id,
                        'file_path' => $path, 'mime_type' => $mime, 'created_at' => now(),
                    ]);
                    $last = $line;
                }
                $ready = !$lineClass::query()->where('check_header_id', $lockedHeader->id)
                    ->whereNotIn('status', ['OK', 'OVERRIDE'])->exists();
                return ['ok' => true, 'accepted' => true, 'requires_override' => false,
                    'message' => 'Qty + foto bukti berhasil disimpan.', 'line' => $last ? [
                        'id' => $last->id, 'scanned_base_qty' => (float) $last->scanned_base_qty,
                        'expected_base_qty' => $last->expected_base_qty === null ? null : (float) $last->expected_base_qty,
                        'status' => $last->status,
                    ] : null, 'session_complete' => $ready];
            });
            return response()->json($response);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function photo(Request $request, string $type, int $header, int $evidence): BinaryFileResponse
    {
        abort_unless(in_array($type, ['invoice', 'transfer'], true), 404);
        $row = DB::connection('apphub')->table('warehouse_check_manual_photos')
            ->where('id', $evidence)->where('document_type', $type)->where('check_header_id', $header)->first();
        abort_unless($row !== null, 404);
        $user = $request->user();
        abort_unless($user && ($user->role === 'admin' || (int) $row->checker_user_id === (int) $user->id), 403);
        abort_unless(Storage::disk('local')->exists($row->file_path), 404);
        return response()->file(Storage::disk('local')->path($row->file_path), [
            'Content-Type' => $row->mime_type,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
