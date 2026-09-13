<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Services\WarehouseCheckService;
use Illuminate\Http\Request;

class WarehouseCheckController extends Controller
{
    public function __construct(
        private readonly WarehouseCheckService $service
    ) {
    }

    public function create()
    {
        $pickers = User::query()
            ->where('role', 'picker')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view(
            'checks.create',
            compact('pickers')
        );
    }

    public function loadInvoice(Request $request)
    {
        $data = $request->validate([
            'company' => ['required', 'in:INGCO,SMI'],
            'invoice_number' => [
                'required',
                'string',
                'max:100',
            ],
            'picker_user_id' => [
                'required',
                'integer',
            ],
        ]);

        $picker = User::query()
            ->findOrFail($data['picker_user_id']);

        try {
            $header = $this->service->start(
                $data['company'],
                $data['invoice_number'],
                $picker,
                $request->user()
            );

            return redirect()->route(
                'checks.show',
                $header
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'invoice_number' => $e->getMessage(),
                ]);
        }
    }

    public function show(
        Request $request,
        WarehouseCheckHeader $check
    ) {
        $check->load([
            'details.overrideRecord',
            'picker',
            'checker',
        ]);

        $ready = $this->service
            ->isReadyToFinalize($check);

        $user = $request->user();

        $canOperate =
            $check->status === 'DRAFT'
            && $user->hasAnyRole(['checker', 'admin'])
            && (
                (int) $check->checker_user_id === (int) $user->id
                || $user->role === 'admin'
            );

        return view(
            'checks.show',
            compact('check', 'ready', 'canOperate')
        );
    }

    public function scan(
        Request $request,
        WarehouseCheckHeader $check
    ) {
        $data = $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:50',
            ],
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
                'requires_override' =>
                    $result['requires_override'],
                'message' => $result['message'],
                'line' => $line ? [
                    'id' => $line->id,
                    'scanned_base_qty' =>
                        (float) $line->scanned_base_qty,
                    'expected_base_qty' =>
                        $line->expected_base_qty !== null
                            ? (float) $line->expected_base_qty
                            : null,
                    'status' => $line->status,
                ] : null,
                'session_complete' =>
                    $result['complete'],
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
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'reason_code' => [
                'required',
                'in:NO_BARCODE,SPECIAL_ITEM,DAMAGED_BARCODE,NO_RATIO,OTHER',
            ],
            'reason_note' => [
                'nullable',
                'string',
                'max:500',
            ],
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

            return back()->with(
                'success',
                'Baris berhasil di-override.'
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'override' => $e->getMessage(),
            ]);
        }
    }

    public function finalize(
        Request $request,
        WarehouseCheckHeader $check
    ) {
        try {
            $this->service->finalize(
                $check,
                $request->user()
            );

            return redirect()
                ->route('dashboard')
                ->with(
                    'success',
                    'Checking invoice berhasil disimpan.'
                );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'finalize' => $e->getMessage(),
            ]);
        }
    }
}
