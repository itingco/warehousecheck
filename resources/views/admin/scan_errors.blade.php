@extends('layouts.app')

@section('title', 'Scan Errors')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">ADMIN ONLY</div>
        <h1>Scan Errors</h1>
        <p class="muted">
            Riwayat barcode yang ditolak saat checking Invoice dan Good Transfer.
        </p>
    </div>

    <a class="btn btn-light" href="{{ route('admin.scan-errors') }}">
        Reset Filter
    </a>
</div>

<div class="card">
    <form method="GET" class="filter-grid">
        <label>
            Dari Tanggal
            <input
                type="date"
                name="date_from"
                value="{{ request('date_from') }}"
            >
        </label>

        <label>
            Sampai Tanggal
            <input
                type="date"
                name="date_to"
                value="{{ request('date_to') }}"
            >
        </label>

        <label>
            Jenis Dokumen
            <select name="document_type">
                <option value="">Semua</option>
                <option value="invoice" @selected(request('document_type') === 'invoice')>
                    Invoice
                </option>
                <option value="good_transfer" @selected(request('document_type') === 'good_transfer')>
                    Good Transfer
                </option>
            </select>
        </label>

        <label>
            Company
            <select name="company">
                <option value="">Semua</option>
                <option value="INGCO" @selected(request('company') === 'INGCO')>INGCO</option>
                <option value="SMI" @selected(request('company') === 'SMI')>SMI</option>
            </select>
        </label>

        <label>
            Picker
            <select name="picker_id">
                <option value="">Semua</option>
                @foreach($pickers as $picker)
                    <option
                        value="{{ $picker->id }}"
                        @selected((string) request('picker_id') === (string) $picker->id)
                    >
                        {{ $picker->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Checker
            <select name="checker_id">
                <option value="">Semua</option>
                @foreach($checkers as $checker)
                    <option
                        value="{{ $checker->id }}"
                        @selected((string) request('checker_id') === (string) $checker->id)
                    >
                        {{ $checker->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Jenis Error
            <select name="error_type">
                <option value="">Semua</option>
                @foreach($errorTypes as $errorType)
                    <option
                        value="{{ $errorType }}"
                        @selected(request('error_type') === $errorType)
                    >
                        {{ $errorType }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Barcode
            <input
                name="barcode"
                value="{{ request('barcode') }}"
                placeholder="Cari barcode"
            >
        </label>

        @if(request()->filled('header_id'))
            <input type="hidden" name="header_id" value="{{ request('header_id') }}">
        @endif

        <button class="btn btn-secondary align-end">Filter</button>
    </form>
</div>

<div class="card" style="margin-bottom: 16px;">
    <strong>{{ number_format($scanErrors->total()) }}</strong>
    <span class="muted">data salah scan ditemukan.</span>
</div>

<div class="card table-card">
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Waktu</th>
                <th>Jenis Dokumen</th>
                <th>Nomor Dokumen</th>
                <th>Company</th>
                <th>Barcode</th>
                <th>Item</th>
                <th>UOM</th>
                <th>Jenis Error</th>
                <th>Pesan</th>
                <th>Picker</th>
                <th>Checker</th>
            </tr>
            </thead>
            <tbody>
            @forelse($scanErrors as $row)
                <tr>
                    <td>
                        {{ optional($row->scanned_at)->format('d-m-Y H:i:s') ?: '-' }}
                    </td>
                    <td>
                        @if($row->document_type === 'invoice')
                            <span class="badge badge-ok">Invoice</span>
                        @else
                            <span class="badge badge-warning">Good Transfer</span>
                        @endif
                    </td>
                    <td>
                        @if($row->document_type === 'invoice')
                            <a href="{{ route('checks.show', $row->header_id) }}">
                                <strong>{{ $row->document_number }}</strong>
                            </a>
                        @else
                            <a href="{{ route('good-transfers.show', $row->header_id) }}">
                                <strong>{{ $row->document_number }}</strong>
                            </a>
                        @endif
                    </td>
                    <td>{{ $row->company }}</td>
                    <td><code>{{ $row->barcode ?: '-' }}</code></td>
                    <td>{{ $row->resolved_item_code ?: '-' }}</td>
                    <td>{{ $row->resolved_uom_code ?: '-' }}</td>
                    <td>
                        <span class="badge badge-danger">
                            {{ $row->error_type }}
                        </span>
                    </td>
                    <td>{{ $row->error_message ?: '-' }}</td>
                    <td>{{ $row->picker_name ?: '-' }}</td>
                    <td>{{ $row->checker_name ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="empty">
                        Tidak ada data salah scan untuk filter ini.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($scanErrors->hasPages())
        <div class="pagination-simple">
            @if($scanErrors->onFirstPage())
                <span class="btn btn-light btn-sm disabled">← Previous</span>
            @else
                <a class="btn btn-light btn-sm" href="{{ $scanErrors->previousPageUrl() }}">← Previous</a>
            @endif

            <span class="page-info">
                Page {{ $scanErrors->currentPage() }} / {{ $scanErrors->lastPage() }}
            </span>

            @if($scanErrors->hasMorePages())
                <a class="btn btn-light btn-sm" href="{{ $scanErrors->nextPageUrl() }}">Next →</a>
            @else
                <span class="btn btn-light btn-sm disabled">Next →</span>
            @endif
        </div>
    @endif
</div>
@endsection
