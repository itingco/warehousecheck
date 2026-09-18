@extends('layouts.app')

@section('title', 'New Good Transfer Check')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">GOOD TRANSFER</div>
        <h1>Mulai Checking Good Transfer</h1>
        <p class="muted">
            Pilih company, picker, lalu masukkan Mutation Number / nomor Good Transfer.
        </p>
    </div>
</div>

<div class="card narrow">
    <div class="alert alert-success" style="margin-bottom:16px;">
        Checker aktif: <strong>{{ auth()->user()->name }}</strong>.
        Nama Anda sendiri dan user admin tidak ditampilkan pada pilihan Picker.
    </div>
    <form method="POST" action="{{ route('good-transfers.load') }}" class="stack">
        @csrf

        <label>
            Company / Database
            <select name="company" required autofocus>
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

        <label>
            Mutation Number / Nomor Good Transfer
            <input
                name="mutation_number"
                value="{{ old('mutation_number') }}"
                placeholder="Masukkan Mutation Number"
                required
            >
        </label>

        <button class="btn btn-primary btn-lg">Load Good Transfer</button>
    </form>
</div>
@endsection
