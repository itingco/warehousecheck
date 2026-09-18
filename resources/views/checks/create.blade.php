@extends('layouts.app')

@section('title', 'New Check')

@section('content')
@php
    $selectedType = old('document_type', request('document_type', 'invoice'));
@endphp

<div class="page-head">
    <div>
        <div class="eyebrow">WAREHOUSE CHECKING</div>
        <h1>New Check</h1>
        <p class="muted">
            Satu halaman untuk memulai checking Invoice atau Good Transfer.
        </p>
    </div>
</div>

<div class="card narrow">
    <div class="alert alert-success" style="margin-bottom:16px;">
        Checker aktif: <strong>{{ auth()->user()->name }}</strong>.
        Nama Anda sendiri dan user admin tidak ditampilkan pada pilihan Picker.
    </div>
    <form method="POST" action="{{ route('checks.load') }}" class="stack" id="newCheckForm">
        @csrf

        <label>
            Jenis Dokumen
            <select name="document_type" id="documentType" required autofocus>
                <option value="invoice" @selected($selectedType === 'invoice')>
                    Invoice
                </option>
                <option value="good_transfer" @selected($selectedType === 'good_transfer')>
                    Good Transfer
                </option>
            </select>
        </label>

        <label>
            Company / Database
            <select name="company" id="company" required>
                <option value="">-- Pilih --</option>
                <option value="INGCO" @selected(old('company') === 'INGCO')>
                    INGCO · AS_INGCO
                </option>
                <option value="SMI" @selected(old('company') === 'SMI')>
                    SMI · AS_SMI
                </option>
            </select>
        </label>

        <label>
            Nama Picker
            <select name="picker_user_id" required>
                <option value="">-- Pilih Picker --</option>
                @foreach($pickers as $picker)
                    <option
                        value="{{ $picker->id }}"
                        @selected((string) old('picker_user_id') === (string) $picker->id)
                    >
                        {{ $picker->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label style="position:relative;">
            <span id="documentNumberLabel">Nomor Invoice</span>
            <input
                name="document_number"
                id="documentNumber"
                value="{{ old('document_number') }}"
                placeholder="Masukkan nomor invoice"
                autocomplete="off"
                required
            >
            <div
                id="documentSuggestions"
                style="
                    display:none;
                    position:absolute;
                    left:0;
                    right:0;
                    top:100%;
                    z-index:30;
                    margin-top:6px;
                    max-height:280px;
                    overflow:auto;
                    border:1px solid #d9e1ec;
                    border-radius:12px;
                    background:#fff;
                    box-shadow:0 12px 30px rgba(15,23,42,.14);
                "
            ></div>
        </label>

        <div
            id="documentHint"
            class="muted small"
            style="margin-top:-4px;"
        >
            Data akan diambil dari AR_Invoices. Ketik contoh <strong>260915/</strong> untuk menampilkan nomor tanggal 15-09-2026.
        </div>

        <button class="btn btn-primary btn-lg" id="loadDocumentBtn">
            Load Invoice
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const type = document.getElementById('documentType');
    const company = document.getElementById('company');
    const label = document.getElementById('documentNumberLabel');
    const input = document.getElementById('documentNumber');
    const hint = document.getElementById('documentHint');
    const button = document.getElementById('loadDocumentBtn');
    const suggestions = document.getElementById('documentSuggestions');
    const suggestionUrl = @json(route('checks.document-suggestions', [], false));

    let debounceTimer = null;
    let activeRequest = null;

    function clearSuggestions() {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }

    function hasDateSearchToken(value) {
        return /(\d{6})\/(\d*)$/.test(value.trim());
    }

    function refreshDocumentFields() {
        const isTransfer = type.value === 'good_transfer';

        label.textContent = isTransfer
            ? 'Mutation Number / Nomor Good Transfer'
            : 'Nomor Invoice';

        input.placeholder = isTransfer
            ? 'Contoh: 260915/ lalu pilih Mutation Number'
            : 'Contoh: 260915/ lalu pilih nomor invoice';

        hint.innerHTML = isTransfer
            ? 'Data diambil dari IC_Mutations. Ketik contoh <strong>260915/</strong> untuk menampilkan dokumen tanggal 15-09-2026.'
            : 'Data diambil dari AR_Invoices. Ketik contoh <strong>260915/</strong> untuk menampilkan dokumen tanggal 15-09-2026.';

        button.textContent = isTransfer
            ? 'Load Good Transfer'
            : 'Load Invoice';

        clearSuggestions();
        scheduleSuggestionSearch();
    }

    function renderSuggestions(items) {
        suggestions.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('div');
            empty.textContent = 'Nomor dokumen tidak ditemukan untuk tanggal tersebut.';
            empty.style.padding = '12px 14px';
            empty.style.color = '#64748b';
            empty.style.fontSize = '13px';
            suggestions.appendChild(empty);
            suggestions.style.display = 'block';
            return;
        }

        items.forEach((item) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.style.cssText = [
                'display:block',
                'width:100%',
                'padding:11px 14px',
                'border:0',
                'border-bottom:1px solid #edf2f7',
                'background:#fff',
                'text-align:left',
                'cursor:pointer'
            ].join(';');

            const number = document.createElement('div');
            number.textContent = item.number;
            number.style.fontWeight = '700';

            const date = document.createElement('div');
            date.textContent = item.date ? `Tanggal ${item.date}` : '';
            date.style.cssText = 'font-size:12px;color:#64748b;margin-top:2px;';

            option.appendChild(number);
            if (item.date) {
                option.appendChild(date);
            }

            option.addEventListener('click', () => {
                input.value = item.number;
                clearSuggestions();
                input.focus();
            });

            suggestions.appendChild(option);
        });

        suggestions.style.display = 'block';
    }

    async function fetchSuggestions() {
        const value = input.value.trim();

        if (!company.value || !hasDateSearchToken(value)) {
            clearSuggestions();
            return;
        }

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();

        const params = new URLSearchParams({
            document_type: type.value,
            company: company.value,
            q: value,
        });

        try {
            const response = await fetch(`${suggestionUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: activeRequest.signal,
            });

            if (!response.ok) {
                clearSuggestions();
                return;
            }

            const payload = await response.json();
            renderSuggestions(Array.isArray(payload.suggestions) ? payload.suggestions : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                clearSuggestions();
            }
        }
    }

    function scheduleSuggestionSearch() {
        window.clearTimeout(debounceTimer);

        if (!company.value || !hasDateSearchToken(input.value)) {
            if (activeRequest) {
                activeRequest.abort();
            }
            clearSuggestions();
            return;
        }

        debounceTimer = window.setTimeout(fetchSuggestions, 350);
    }

    type.addEventListener('change', refreshDocumentFields);
    company.addEventListener('change', scheduleSuggestionSearch);
    input.addEventListener('input', scheduleSuggestionSearch);

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== input) {
            clearSuggestions();
        }
    });

    refreshDocumentFields();
})();
</script>
@endpush
