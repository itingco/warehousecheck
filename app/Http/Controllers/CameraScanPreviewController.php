<?php

namespace App\Http\Controllers;

use App\Models\GoodTransferCheckHeader;
use App\Models\WarehouseCheckHeader;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Read-only lookup for camera confirmation. Never writes scan quantities. */
class CameraScanPreviewController extends Controller
{
    public function invoice(Request $request, WarehouseCheckHeader $check, BarcodeService $barcodes): JsonResponse
    {
        return $this->handle($request, $check, $barcodes);
    }

    public function transfer(Request $request, GoodTransferCheckHeader $transfer, BarcodeService $barcodes): JsonResponse
    {
        return $this->handle($request, $transfer, $barcodes);
    }

    private function handle(Request $request, WarehouseCheckHeader|GoodTransferCheckHeader $document, BarcodeService $barcodes): JsonResponse
    {
        abort_unless($request->user()?->is_active && $request->user()?->role !== 'admin'
            && (int) $document->checker_user_id === (int) $request->user()->id
            && $document->status === 'DRAFT', 403);

        $validated = $request->validate([
            'barcode' => ['nullable', 'string', 'max:150', 'required_without:image'],
            'image' => ['nullable', 'string', 'max:7500000', 'required_without:barcode'],
        ]);
        $value = trim((string) ($validated['barcode'] ?? ''));
        if ($value === '' && isset($validated['image'])) {
            $value = $this->readImage($validated['image']);
        }
        if ($value === '' || preg_match('#^https?://#i', $value)) {
            throw ValidationException::withMessages(['barcode' => 'QR URL website diabaikan atau barcode tidak terbaca.']);
        }
        if (strlen($value) > 50) {
            throw ValidationException::withMessages(['barcode' => 'Barcode terlalu panjang untuk endpoint pengecekan (maksimum 50 karakter).']);
        }

        try {
            $result = $barcodes->lookup($value);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }
        if (! $result) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tidak ditemukan di master ItemBarcode. Belum ada transaksi yang disimpan.']);
        }
        $itemCode = trim((string) $result->item_code);
        $line = $document->details()->where('item_code', $itemCode)->first();
        if (! $line) {
            throw ValidationException::withMessages(['barcode' => 'Barang '.$itemCode.' tidak terdapat dalam dokumen yang sedang dicek.']);
        }
        return response()->json([
            'ok' => true,
            'barcode' => $value,
            'item_code' => $itemCode,
            'item_name' => (string) $line->item_name,
            'uom_code' => (string) ($result->uom_code ?: ($line->uom_code ?: '-')),
            'ratio_qty' => $result->ratio_qty !== null ? (float) $result->ratio_qty : null,
            'scan_source' => (string) ($result->scan_source ?? 'MASTER_BARCODE'),
            'needs_ratio_override' => $result->ratio_qty === null || (float) $result->ratio_qty <= 0,
            'requires_confirmation' => true,
        ]);
    }

    private function readImage(string $data): string
    {
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#D', $data, $match)) {
            throw ValidationException::withMessages(['image' => 'Foto kamera harus JPEG.']);
        }
        $bytes = base64_decode($match[1], true);
        if ($bytes === false || strlen($bytes) > 4 * 1024 * 1024 || ! @getimagesizefromstring($bytes)) {
            throw ValidationException::withMessages(['image' => 'Foto kamera tidak valid atau terlalu besar.']);
        }
        $key = (string) config('warehouse_scanner_ai.api_key', '');
        if ($key === '') {
            throw ValidationException::withMessages(['image' => 'Vision AI belum diaktifkan. Atur OPENAI_API_KEY atau gunakan scanner otomatis.']);
        }
        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(5)->timeout(25)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('warehouse_scanner_ai.model', 'gpt-4.1-mini'),
                    'store' => false,
                    'max_output_tokens' => 100,
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => 'Read the ONE most prominent product barcode or QR value from the image. Preserve leading zeros and any ~QTY suffix. Never invent digits. If unclear, answer UNCLEAR. Reply only with the barcode text.'],
                            ['type' => 'input_image', 'image_url' => $data, 'detail' => 'high'],
                        ],
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Warehouse scanner AI request failed', ['error' => $e->getMessage()]);
            throw ValidationException::withMessages(['image' => 'Layanan Vision AI tidak dapat dihubungi.']);
        }
        if (! $response->successful()) {
            Log::warning('Warehouse scanner AI HTTP failure', ['status' => $response->status()]);
            throw ValidationException::withMessages(['image' => 'Vision AI gagal membaca foto. Silakan ulangi.']);
        }
        $value = collect($response->json('output', []))
            ->flatMap(fn ($output) => $output['content'] ?? [])
            ->filter(fn ($piece) => ($piece['type'] ?? null) === 'output_text')
            ->pluck('text')->implode(' ');
        $value = trim($value, " \t\n\r\0\x0B\"'`");
        if ($value === '' || strtoupper($value) === 'UNCLEAR' || str_contains($value, "\n")
            || ! preg_match('/^[A-Za-z0-9._~\/-]{1,50}$/D', $value)) {
            throw ValidationException::withMessages(['image' => 'Barcode tidak dapat dibaca secara pasti. Foto ulang atau gunakan scanner biasa.']);
        }
        return $value;
    }
}
