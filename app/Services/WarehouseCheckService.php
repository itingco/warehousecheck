<?php

namespace App\Services;

use App\Models\User;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Models\WarehouseCheckOverride;
use App\Models\WarehouseCheckScanError;
use App\Models\WarehouseCheckScanLog;
use Illuminate\Support\Facades\DB;

class WarehouseCheckService
{
    public function __construct(
        private readonly InvoiceSourceService $invoiceSource,
        private readonly BarcodeService $barcodeService,
        private readonly SupervisorOtpService $otpService,
    ) {
    }

    public function start(
        string $company,
        string $invoiceNumber,
        User $picker,
        User $checker
    ): WarehouseCheckHeader {
        $company = strtoupper(trim($company));
        $invoiceNumber = trim($invoiceNumber);

        if ($picker->role !== 'picker' || !$picker->is_active) {
            throw new \RuntimeException(
                'Picker tidak valid atau tidak aktif.'
            );
        }

        $existing = WarehouseCheckHeader::query()
            ->where('company', $company)
            ->where('invoice_number', $invoiceNumber)
            ->whereIn('status', ['DRAFT', 'FINALIZING'])
            ->latest('id')
            ->first();

        if ($existing) {
            if (
                (int) $existing->checker_user_id !== (int) $checker->id
                && $checker->role !== 'admin'
            ) {
                throw new \RuntimeException(
                    'Invoice ini sedang diperiksa checker lain.'
                );
            }

            if ((int) $existing->picker_user_id !== (int) $picker->id) {
                throw new \RuntimeException(
                    'Invoice ini sudah memiliki draft dengan picker berbeda.'
                );
            }

            return $existing->load([
                'details',
                'picker',
                'checker',
            ]);
        }

        $completed = WarehouseCheckHeader::query()
            ->where('company', $company)
            ->where('invoice_number', $invoiceNumber)
            ->where('status', 'COMPLETED')
            ->exists();

        if ($completed) {
            throw new \RuntimeException(
                'Invoice ini sudah pernah selesai di-check.'
            );
        }

        $rows = $this->invoiceSource
            ->getInvoice($company, $invoiceNumber);

        if ($rows->isEmpty() || !$rows->first()->InvoiceID) {
            throw new \RuntimeException('Invoice tidak ditemukan.');
        }

        return DB::connection('apphub')->transaction(
            function () use ($rows, $company, $picker, $checker) {
                $first = $rows->first();

                $header = WarehouseCheckHeader::create([
                    'company' => $company,
                    'source_database' =>
                        $this->invoiceSource->databaseForCompany($company),
                    'invoice_id' => (int) $first->InvoiceID,
                    'invoice_number' => trim((string) $first->InvoiceNumber),
                    'invoice_date' => $first->InvoiceDate,
                    'customer_name' => $first->CustomerName,
                    'shipping_address' => trim(
                        ($first->ShippingAddressLine1 ?? '')
                        . ' '
                        . ($first->ShippingAddressLine2 ?? '')
                    ),
                    'picker_user_id' => $picker->id,
                    'checker_user_id' => $checker->id,
                    'status' => 'DRAFT',
                    'started_at' => now(),
                ]);

                foreach ($rows as $row) {
                    if (!$row->InvoiceDetailID || !$row->ItemCode) {
                        continue;
                    }

                    $itemCode = trim((string) $row->ItemCode);
                    $uomCode = $row->UOMCode
                        ? trim((string) $row->UOMCode)
                        : null;

                    $invoiceRatio =
                        $this->barcodeService->ratioForInvoiceUom(
                            $itemCode,
                            $uomCode
                        );

                    $expectedQty = (float) $row->Quantity;

                    $expectedBaseQty = $invoiceRatio !== null
                        ? $expectedQty * $invoiceRatio
                        : null;

                    WarehouseCheckDetail::create([
                        'check_header_id' => $header->id,
                        'source_detail_id' => (int) $row->InvoiceDetailID,
                        'item_code' => $itemCode,
                        'item_name' => $row->ItemName,
                        'uom_code' => $uomCode,
                        'expected_qty' => $expectedQty,
                        'invoice_uom_ratio' => $invoiceRatio,
                        'expected_base_qty' => $expectedBaseQty,
                        'scanned_base_qty' => 0,
                        'status' => 'PENDING',
                    ]);
                }

                if ($header->details()->count() === 0) {
                    throw new \RuntimeException(
                        'Invoice tidak mempunyai detail item.'
                    );
                }

                return $header->load([
                    'details',
                    'picker',
                    'checker',
                ]);
            }
        );
    }

    public function scan(
        WarehouseCheckHeader $header,
        string $barcode,
        User $checker
    ): array {
        $this->assertCheckerOwnsDraft($header, $checker);

        $barcode = trim($barcode);

        if ($barcode === '') {
            throw new \RuntimeException('Barcode kosong.');
        }

        $resolved = $this->barcodeService->lookup($barcode);

        if (!$resolved) {
            $this->logError(
                $header,
                $barcode,
                null,
                null,
                'UNKNOWN_BARCODE',
                'Barcode tidak ditemukan di ItemBarcode.',
                $checker
            );

            throw new \RuntimeException(
                'Barcode tidak ditemukan. Salah scan sudah dicatat.'
            );
        }

        $itemCode = trim((string) $resolved->item_code);
        $uomCode = $resolved->uom_code
            ? trim((string) $resolved->uom_code)
            : null;

        $allItemLines = $header->details()
            ->where('item_code', $itemCode)
            ->orderBy('source_detail_id')
            ->get();

        if ($allItemLines->isEmpty()) {
            $this->logError(
                $header,
                $barcode,
                $itemCode,
                $uomCode,
                'ITEM_NOT_IN_INVOICE',
                'Item dari barcode tidak ada di invoice.',
                $checker
            );

            throw new \RuntimeException(
                'Item tidak ada di invoice. Salah scan sudah dicatat.'
            );
        }

        // Sesuai keputusan:
        // tidak ada ratio = skip perhitungan dan bukan kesalahan picker.
        if ($resolved->ratio_qty === null || (int) $resolved->ratio_qty <= 0) {
            return [
                'accepted' => false,
                'requires_override' => true,
                'message' =>
                    'Barcode ditemukan, tetapi ratio tidak tersedia. '
                    . 'Gunakan Supervisor Override pada item terkait.',
                'line' => $allItemLines
                    ->firstWhere('status', 'PENDING'),
                'complete' => false,
            ];
        }

        $ratio = (int) $resolved->ratio_qty;

        // Hanya line yang punya expected_base_qty yang boleh dihitung normal.
        $candidate = $allItemLines
            ->where('status', 'PENDING')
            ->filter(fn (WarehouseCheckDetail $line) =>
                $line->expected_base_qty !== null
            )
            ->first(function (WarehouseCheckDetail $line) use ($ratio) {
                $remaining =
                    (float) $line->expected_base_qty
                    - (float) $line->scanned_base_qty;

                return $remaining + 0.00001 >= $ratio;
            });

        if (!$candidate) {
            // Jika masih ada pending item tetapi invoice ratio-nya tidak tersedia,
            // jangan dianggap OVER_QTY.
            $pendingWithoutRatio = $allItemLines
                ->where('status', 'PENDING')
                ->first(fn (WarehouseCheckDetail $line) =>
                    $line->expected_base_qty === null
                );

            if ($pendingWithoutRatio) {
                return [
                    'accepted' => false,
                    'requires_override' => true,
                    'message' =>
                        'Item ada di invoice tetapi ratio invoice tidak tersedia. '
                        . 'Gunakan Supervisor Override.',
                    'line' => $pendingWithoutRatio,
                    'complete' => false,
                ];
            }

            $this->logError(
                $header,
                $barcode,
                $itemCode,
                $uomCode,
                'OVER_QTY',
                'Quantity item sudah terpenuhi atau scan melebihi kebutuhan.',
                $checker
            );

            throw new \RuntimeException(
                'Quantity item sudah penuh / melebihi kebutuhan. '
                . 'Salah scan sudah dicatat.'
            );
        }

        DB::connection('apphub')->transaction(
            function () use (
                $header,
                $candidate,
                $resolved,
                $ratio,
                $checker,
                $barcode,
                $itemCode,
                $uomCode
            ) {
                $line = WarehouseCheckDetail::query()
                    ->whereKey($candidate->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($line->status !== 'PENDING') {
                    throw new \RuntimeException(
                        'Baris sudah selesai diproses.'
                    );
                }

                if ($line->expected_base_qty === null) {
                    throw new \RuntimeException(
                        'Baris tidak memiliki ratio dan harus di-override.'
                    );
                }

                $newQty =
                    (float) $line->scanned_base_qty
                    + $ratio;

                $expected =
                    (float) $line->expected_base_qty;

                if ($newQty > $expected + 0.00001) {
                    throw new \RuntimeException(
                        'Quantity scan melebihi kebutuhan.'
                    );
                }

                $isComplete =
                    abs($newQty - $expected) < 0.00001;

                $line->update([
                    'scanned_base_qty' => $newQty,
                    'status' => $isComplete ? 'OK' : 'PENDING',
                    'completed_at' => $isComplete ? now() : null,
                ]);

                WarehouseCheckScanLog::create([
                    'check_header_id' => $header->id,
                    'check_detail_id' => $line->id,
                    'barcode' => $barcode,
                    'item_code' => $itemCode,
                    'uom_code' => $uomCode,
                    'ratio_qty' => $ratio,
                    'checker_user_id' => $checker->id,
                    'scanned_at' => now(),
                ]);
            }
        );

        $candidate->refresh();

        return [
            'accepted' => true,
            'requires_override' => false,
            'message' => $candidate->status === 'OK'
                ? 'Item selesai.'
                : 'Scan diterima.',
            'line' => $candidate,
            'complete' => $this->isReadyToFinalize($header),
        ];
    }

    public function override(
        WarehouseCheckHeader $header,
        WarehouseCheckDetail $line,
        string $otpCode,
        string $reasonCode,
        ?string $reasonNote,
        User $checker
    ): void {
        $this->assertCheckerOwnsDraft($header, $checker);

        if ((int) $line->check_header_id !== (int) $header->id) {
            throw new \RuntimeException(
                'Detail bukan milik invoice ini.'
            );
        }

        if ($line->status !== 'PENDING') {
            throw new \RuntimeException(
                'Hanya baris PENDING yang dapat di-override.'
            );
        }

        $otp = $this->otpService
            ->validateCode(trim($otpCode));

        DB::connection('apphub')->transaction(
            function () use (
                $header,
                $line,
                $otp,
                $reasonCode,
                $reasonNote,
                $checker
            ) {
                $locked = WarehouseCheckDetail::query()
                    ->whereKey($line->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== 'PENDING') {
                    throw new \RuntimeException(
                        'Baris sudah selesai.'
                    );
                }

                $locked->update([
                    'status' => 'OVERRIDE',
                    'completed_at' => now(),
                ]);

                WarehouseCheckOverride::create([
                    'check_header_id' => $header->id,
                    'check_detail_id' => $locked->id,
                    'supervisor_user_id' =>
                        $otp->supervisor_user_id,
                    'checker_user_id' => $checker->id,
                    'otp_id' => $otp->id,
                    'reason_code' => $reasonCode,
                    'reason_note' => $reasonNote,
                    'overridden_at' => now(),
                ]);
            }
        );
    }

    public function finalize(
        WarehouseCheckHeader $header,
        User $checker
    ): WarehouseCheckHeader {
        $this->assertCheckerOwnsDraft($header, $checker);

        if (!$this->isReadyToFinalize($header)) {
            throw new \RuntimeException(
                'Belum semua baris berstatus OK/OVERRIDE.'
            );
        }

        $header->update(['status' => 'FINALIZING']);

        try {
            $this->invoiceSource->markChecked(
                $header->company,
                (int) $header->invoice_id,
                $checker->name
            );

            $header->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'source_updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $header->update(['status' => 'DRAFT']);
            throw $e;
        }

        return $header->fresh([
            'details',
            'picker',
            'checker',
        ]);
    }

    public function isReadyToFinalize(
        WarehouseCheckHeader $header
    ): bool {
        return !$header->details()
            ->whereNotIn('status', ['OK', 'OVERRIDE'])
            ->exists();
    }

    private function assertCheckerOwnsDraft(
        WarehouseCheckHeader $header,
        User $checker
    ): void {
        if ($header->status !== 'DRAFT') {
            throw new \RuntimeException(
                'Session ini bukan DRAFT.'
            );
        }

        if (
            (int) $header->checker_user_id !== (int) $checker->id
            && $checker->role !== 'admin'
        ) {
            throw new \RuntimeException(
                'Session ini milik checker lain.'
            );
        }
    }

    private function logError(
        WarehouseCheckHeader $header,
        ?string $barcode,
        ?string $itemCode,
        ?string $uomCode,
        string $type,
        string $message,
        User $checker
    ): void {
        WarehouseCheckScanError::create([
            'check_header_id' => $header->id,
            'barcode' => $barcode,
            'resolved_item_code' => $itemCode,
            'resolved_uom_code' => $uomCode,
            'error_type' => $type,
            'error_message' => $message,
            'picker_user_id' => $header->picker_user_id,
            'checker_user_id' => $checker->id,
            'scanned_at' => now(),
        ]);
    }
}
