@extends('layouts.app')

@section('title', 'New Check')

@section('content')

<div class="page-head">

    <div>
        <h1>Mulai Checking Invoice</h1>

        <p class="muted">
            Pilih company, picker, lalu masukkan nomor invoice.
        </p>
    </div>

</div>

<div class="card narrow">

    <form
        method="POST"
        action="{{ route('checks.load') }}"
        class="stack"
    >

        @csrf

        <label>
            Company / Database

            <select
                name="company"
                required
                autofocus
            >
                <option value="">
                    -- Pilih --
                </option>

                <option
                    value="INGCO"
                    @selected(old('company') === 'INGCO')
                >
                    INGCO · AS_INGCO
                </option>

                <option
                    value="SMI"
                    @selected(old('company') === 'SMI')
                >
                    SMI · AS_SMI
                </option>
            </select>
        </label>

        <label>
            Nama Picker

            <select
                name="picker_user_id"
                required
            >
                <option value="">
                    -- Pilih Picker --
                </option>

                @foreach($pickers as $picker)

                    <option
                        value="{{ $picker->id }}"
                        @selected(
                            (string) old('picker_user_id')
                            ===
                            (string) $picker->id
                        )
                    >
                        {{ $picker->name }}
                    </option>

                @endforeach
            </select>
        </label>

        <label>
            Nomor Invoice

            <input
                name="invoice_number"
                value="{{ old('invoice_number') }}"
                placeholder="Masukkan nomor invoice"
                required
            >
        </label>

        <button
            class="btn btn-primary btn-lg"
        >
            Load Invoice
        </button>

    </form>

</div>

@endsection
