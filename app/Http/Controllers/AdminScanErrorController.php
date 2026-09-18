<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminScanErrorController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'document_type' => ['nullable', 'in:invoice,good_transfer'],
            'company' => ['nullable', 'in:INGCO,SMI'],
            'picker_id' => ['nullable', 'integer'],
            'checker_id' => ['nullable', 'integer'],
            'error_type' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:200'],
            'header_id' => ['nullable', 'integer'],
        ]);

        $rows = collect();
        $documentType = $filters['document_type'] ?? null;

        if ($documentType !== 'good_transfer') {
            $invoiceRows = $this->invoiceErrors($filters)
                ->get()
                ->map(function ($row) {
                    $row->document_type = 'invoice';
                    $row->document_type_label = 'Invoice';
                    $row->scanned_at = $row->scanned_at
                        ? Carbon::parse($row->scanned_at)
                        : null;

                    return $row;
                });

            $rows = $rows->concat($invoiceRows);
        }

        if ($documentType !== 'invoice') {
            $transferRows = $this->goodTransferErrors($filters)
                ->get()
                ->map(function ($row) {
                    $row->document_type = 'good_transfer';
                    $row->document_type_label = 'Good Transfer';
                    $row->scanned_at = $row->scanned_at
                        ? Carbon::parse($row->scanned_at)
                        : null;

                    return $row;
                });

            $rows = $rows->concat($transferRows);
        }

        $rows = $rows
            ->sortByDesc(fn ($row) => $row->scanned_at?->getTimestamp() ?? 0)
            ->values();

        $perPage = 50;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $scanErrors = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $pickers = User::query()
            ->where('role', '<>', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        // Picker/checker sekarang fleksibel. Jangan batasi filter berdasarkan role lama.
        $checkers = User::query()
            ->where('role', '<>', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $errorTypes = collect(
            DB::connection('apphub')
                ->table('warehouse_check_scan_errors')
                ->whereNotNull('error_type')
                ->distinct()
                ->pluck('error_type')
        )
            ->merge(
                DB::connection('apphub')
                    ->table('good_transfer_check_scan_errors')
                    ->whereNotNull('error_type')
                    ->distinct()
                    ->pluck('error_type')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('admin.scan_errors', compact(
            'scanErrors',
            'pickers',
            'checkers',
            'errorTypes'
        ));
    }

    private function invoiceErrors(array $filters): Builder
    {
        $query = DB::connection('apphub')
            ->table('warehouse_check_scan_errors as e')
            ->join('warehouse_check_headers as h', 'h.id', '=', 'e.check_header_id')
            ->leftJoin('warehouse_users as picker', 'picker.id', '=', 'e.picker_user_id')
            ->leftJoin('warehouse_users as checker', 'checker.id', '=', 'e.checker_user_id')
            ->select([
                'e.id',
                'e.check_header_id as header_id',
                'h.invoice_number as document_number',
                'h.company',
                'e.barcode',
                'e.resolved_item_code',
                'e.resolved_uom_code',
                'e.error_type',
                'e.error_message',
                'picker.name as picker_name',
                'checker.name as checker_name',
                'e.scanned_at',
            ]);

        return $this->applyFilters($query, $filters);
    }

    private function goodTransferErrors(array $filters): Builder
    {
        $query = DB::connection('apphub')
            ->table('good_transfer_check_scan_errors as e')
            ->join('good_transfer_check_headers as h', 'h.id', '=', 'e.check_header_id')
            ->leftJoin('warehouse_users as picker', 'picker.id', '=', 'e.picker_user_id')
            ->leftJoin('warehouse_users as checker', 'checker.id', '=', 'e.checker_user_id')
            ->select([
                'e.id',
                'e.check_header_id as header_id',
                'h.mutation_number as document_number',
                'h.company',
                'e.barcode',
                'e.resolved_item_code',
                'e.resolved_uom_code',
                'e.error_type',
                'e.error_message',
                'picker.name as picker_name',
                'checker.name as checker_name',
                'e.scanned_at',
            ]);

        return $this->applyFilters($query, $filters);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['date_from'])) {
            $query->where(
                'e.scanned_at',
                '>=',
                Carbon::parse($filters['date_from'])->startOfDay()
            );
        }

        if (!empty($filters['date_to'])) {
            $query->where(
                'e.scanned_at',
                '<',
                Carbon::parse($filters['date_to'])->addDay()->startOfDay()
            );
        }

        if (!empty($filters['company'])) {
            $query->where('h.company', strtoupper($filters['company']));
        }

        if (!empty($filters['picker_id'])) {
            $query->where('e.picker_user_id', (int) $filters['picker_id']);
        }

        if (!empty($filters['checker_id'])) {
            $query->where('e.checker_user_id', (int) $filters['checker_id']);
        }

        if (!empty($filters['error_type'])) {
            $query->where('e.error_type', $filters['error_type']);
        }

        if (!empty($filters['barcode'])) {
            $query->where('e.barcode', 'like', '%' . trim($filters['barcode']) . '%');
        }

        if (!empty($filters['header_id'])) {
            $query->where('e.check_header_id', (int) $filters['header_id']);
        }

        return $query;
    }
}
