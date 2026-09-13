<?php

namespace App\Http\Controllers;

use App\Models\WarehouseCheckHeader;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = WarehouseCheckHeader::query()
            ->with(['picker', 'checker'])
            ->withCount('scanErrors')
            ->latest('id');

        if ($request->filled('invoice')) {
            $query->where(
                'invoice_number',
                'like',
                '%' . trim($request->invoice) . '%'
            );
        }

        if ($request->filled('company')) {
            $query->where(
                'company',
                strtoupper($request->company)
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                strtoupper($request->status)
            );
        }

        $rows = $query
            ->paginate(25)
            ->withQueryString();

        return view('dashboard', compact('rows'));
    }
}
