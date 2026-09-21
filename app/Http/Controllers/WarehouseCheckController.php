<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Services\GoodTransferCheckService;
use App\Services\WarehouseCheckService;
use Illuminate\Http\Request;

class WarehouseCheckController extends Controller
{
    public function __construct(
        private readonly WarehouseCheckService $service,
        private readonly GoodTransferCheckService $goodTransferService
    ) {
    }

    public function create(Request $request)
    {
        $this->assertOperationalUser($request->user());

        $pickers = User::query()
            ->where('is_active', 1)
            ->where('role', '<>', 'admin')
            ->where('id', '<>', $request->user()->id)
            ->orderBy('name')
            ->get();

        return view('checks.create', compact('pickers'));
    }

    public function loadDocument(Request $request)
    {
        $this->assertOperationalUser($request->user());

        $data = $request->validate([
            'document_type' => ['required', 'in:invoice,good_transfer'],
            'company' => ['required', 'in:INGCO,SMI'],
            'document_number' => ['required', 'string', 'max:100'],
            'picker_user_id' => ['required', 'integer'],
        ]);

        try {
            $picker = $this->findEligiblePickerOrFail(
                (int) $data['picker_user_id'],
                $request->user()
            );

            if ($data['document_type'] === 'good_transfer') {
                $header = $this->goodTransferService->start(
                    $data['company'],
                    $data['document_number'],
                    $picker,
                    $request->user()
                );

                return redirect()->route('good-transfers.show', $header);
            }

            $header = $this->service->start(
                $data['company'],
                $data['document_number'],
                $picker,
                $request->user()
            );

            return redirect()->route('checks.show', $header);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['document_number' => $e->getMessage()]);
        }
    }

    public function loadInvoice(Request $request)
    {
        $this->assertOperationalUser($request->user());

        $data = $request->validate([
            'company' => ['required', 'in:INGCO,SMI'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'picker_user_id' => ['required', 'integer'],
        ]);

        try {
            $picker = $this->findEligiblePickerOrFail(
                (int) $data['picker_user_id'],
                $request->user()
            );

            $header = $this->service->start(
                $data['company'],
                $data['invoice_number'],
                $picker,
                $request->user()
            );

            return redirect()->route('checks.show', $header);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['invoice_number' => $e->getMessage()]);
        }
    }

    public function show(Request $request, WarehouseCheckHeader $check)
    {
        $user = $request->user();

        $this->assertCanViewCheck($check, $user);

        $check->load([
            'details' => fn ($query) => $query->orderBy('source_detail_id'),
            'details.overrideRecord.admin',
            'picker',
            'checker',
        ]);

        $ready = $this->service->isReadyToFinalize($check);

        $canOperate =
            $check->status === 'DRAFT'
            && $user->is_active
            && $user->role !== 'admin'
            && (int) $check->checker_user_id === (int) $user->id;

        $canAdminOverrideQty =
            $check->status === 'DRAFT'
            && $user->role === 'admin';

        return view(
            'checks.show',
            compact('check', 'ready', 'canOperate', 'canAdminOverrideQty')
        );
    }

    public function scan(Request $request, WarehouseCheckHeader $check)
    {
        $this->assertOperationalUser($request->user());

        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
        ]);

        try {
            $result = $this->service->scan(
                $check,
                $data['barcode'],
                $request->user()
            );

            $line = $result['line'];

            return response()->json([
                'ok' => true,
                'accepted' => $result['accepted'],
                'requires_override' => $result['requires_override'],
                'message' => $result['message'],
                'line' => $line ? [
                    'id' => $line->id,
                    'scanned_base_qty' => (float) $line->scanned_base_qty,
                    'expected_base_qty' => $line->expected_base_qty !== null
                        ? (float) $line->expected_base_qty
                        : null,
                    'status' => $line->status,
                ] : null,
                'session_complete' => $result['complete'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function override(
        Request $request,
        WarehouseCheckHeader $check,
        WarehouseCheckDetail $detail
    ) {
        $this->assertOperationalUser($request->user());

        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'reason_code' => [
                'required',
                'in:NO_BARCODE,SPECIAL_ITEM,DAMAGED_BARCODE,NO_RATIO,OTHER',
            ],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->override(
                $check,
                $detail,
                $data['otp'],
                $data['reason_code'],
                $data['reason_note'] ?? null,
                $request->user()
            );

            return back()->with('success', 'Baris berhasil di-override.');
        } catch (\Throwable $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }
    }

    public function finalize(Request $request, WarehouseCheckHeader $check)
    {
        $this->assertOperationalUser($request->user());

        try {
            $this->service->finalize($check, $request->user());

            return redirect()
                ->route('dashboard')
                ->with(
                    'success',
                    'Checking invoice berhasil disimpan. CheckedBy dan CheckedDateTime pada AR_Invoices sudah di-update.'
                );
        } catch (\Throwable $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }
    }

    private function findEligiblePickerOrFail(int $pickerId, User $checker): User
    {
        $picker = User::query()
            ->whereKey($pickerId)
            ->where('is_active', 1)
            ->where('role', '<>', 'admin')
            ->where('id', '<>', $checker->id)
            ->first();

        if (!$picker) {
            throw new \RuntimeException(
                'Picker tidak valid. Picker harus user aktif non-admin dan tidak boleh sama dengan checker.'
            );
        }

        return $picker;
    }

    private function assertOperationalUser(User $user): void
    {
        if (!$user->is_active || $user->role === 'admin') {
            abort(403, 'Admin tidak dapat menjadi picker/checker.');
        }
    }

    private function assertCanViewCheck(
        WarehouseCheckHeader $check,
        User $user
    ): void {
        if (
            $user->role !== 'admin'
            && (int) $check->checker_user_id !== (int) $user->id
        ) {
            abort(
                403,
                'Anda hanya dapat melihat Sales Invoice checking yang Anda buat sendiri.'
            );
        }
    }
}
