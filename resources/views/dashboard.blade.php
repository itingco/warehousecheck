@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="page-head">

    <div>
        <h1>Dashboard Checking</h1>

        <p class="muted">
            Riwayat invoice, picker, checker,
            waktu checking dan jumlah salah scan.
        </p>
    </div>

    @if(auth()->user()->hasAnyRole(['checker','admin']))
        <a
            class="btn btn-primary"
            href="{{ route('checks.create') }}"
        >
            + New Check
        </a>
    @endif

</div>

<div class="card">

    <form
        method="GET"
        class="filter-grid"
    >

        <label>
            Invoice

            <input
                name="invoice"
                value="{{ request('invoice') }}"
                placeholder="Nomor invoice"
            >
        </label>

        <label>
            Company

            <select name="company">
                <option value="">
                    Semua
                </option>

                <option
                    value="INGCO"
                    @selected(request('company') === 'INGCO')
                >
                    INGCO
                </option>

                <option
                    value="SMI"
                    @selected(request('company') === 'SMI')
                >
                    SMI
                </option>
            </select>
        </label>

        <label>
            Status

            <select name="status">
                <option value="">
                    Semua
                </option>

                @foreach(['DRAFT','FINALIZING','COMPLETED'] as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>
        </label>

        <button
            class="btn btn-secondary align-end"
        >
            Filter
        </button>

    </form>

</div>

<div class="card table-card">

    <div class="table-scroll">

        <table>

            <thead>
            <tr>
                <th>Invoice</th>
                <th>Company</th>
                <th>Customer</th>
                <th>Picker</th>
                <th>Checker</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Salah Scan</th>
                <th>Status</th>
            </tr>
            </thead>

            <tbody>

            @forelse($rows as $row)

                <tr>

                    <td>
                        <a
                            href="{{ route('checks.show', $row) }}"
                        >
                            <strong>
                                {{ $row->invoice_number }}
                            </strong>
                        </a>
                    </td>

                    <td>{{ $row->company }}</td>

                    <td>
                        {{ $row->customer_name }}
                    </td>

                    <td>
                        {{ optional($row->picker)->name }}
                    </td>

                    <td>
                        {{ optional($row->checker)->name }}
                    </td>

                    <td>
                        {{ optional($row->started_at)->format('d-m-Y H:i:s') }}
                    </td>

                    <td>
                        {{ optional($row->completed_at)->format('d-m-Y H:i:s') ?? '-' }}
                    </td>

                    <td>
                        <span class="badge badge-danger">
                            {{ $row->scan_errors_count }}
                        </span>
                    </td>

                    <td>
                        <span
                            class="badge badge-{{ strtolower($row->status) }}"
                        >
                            {{ $row->status }}
                        </span>
                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="9"
                        class="empty"
                    >
                        Belum ada data.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($rows->hasPages())
        <div class="pagination-simple">

            @if($rows->onFirstPage())
                <span class="btn btn-light btn-sm disabled">
                    ← Previous
                </span>
            @else
                <a
                    class="btn btn-light btn-sm"
                    href="{{ $rows->previousPageUrl() }}"
                >
                    ← Previous
                </a>
            @endif

            <span class="page-info">
                Page {{ $rows->currentPage() }}
                / {{ $rows->lastPage() }}
            </span>

            @if($rows->hasMorePages())
                <a
                    class="btn btn-light btn-sm"
                    href="{{ $rows->nextPageUrl() }}"
                >
                    Next →
                </a>
            @else
                <span class="btn btn-light btn-sm disabled">
                    Next →
                </span>
            @endif

        </div>
    @endif

</div>

@endsection
