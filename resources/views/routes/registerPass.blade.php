@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">
        Register Commuter Pass
        <small class="text-muted fs-6">
            for {{ $target->first_name }} {{ $target->family_name }} ({{ $target->employee_code }})
        </small>
    </h2>

    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
        Back
    </a>
</div>

@if(session('toast'))
<div class="alert alert-success">
    {{ session('toast') }}
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $msg)
        <li>{{ $msg }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST"
    action="{{ $isAdminMode
                ? route('commuter_passes.admin.store', $target)
                : route('commuter_passes.store') }}">
    @csrf

    <div class="card">
        <div class="card-header">
            Commuter Pass Info
        </div>

        <div class="card-body">
            <div class="row g-3">

                {{-- 期間 --}}
                <div class="col-md-3">
                    <label class="form-label">Valid From <span class="text-danger">*</span></label>
                    <input type="date"
                        name="date_from"
                        value="{{ old('date_from') }}"
                        class="form-control"
                        required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Valid To <span class="text-danger">*</span></label>
                    <input type="date"
                        name="date_to"
                        value="{{ old('date_to') }}"
                        class="form-control"
                        required>
                </div>

                {{-- 区間 --}}
                <div class="col-md-3">
                    <label class="form-label">From Station <span class="text-danger">*</span></label>
                    <input type="text"
                        name="station_from"
                        value="{{ old('station_from') }}"
                        class="form-control"
                        required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Station <span class="text-danger">*</span></label>
                    <input type="text"
                        name="station_to"
                        value="{{ old('station_to') }}"
                        class="form-control"
                        required>
                </div>

                {{-- 定期代（任意） --}}
                <div class="col-md-3">
                    <label class="form-label">Cost (JPY)</label>
                    <input type="number"
                        name="cost"
                        value="{{ old('cost') }}"
                        class="form-control"
                        min="0" step="1">
                </div>

                {{-- 備考 --}}
                <div class="col-12">
                    <label class="form-label">Note</label>
                    <textarea name="note"
                        rows="2"
                        class="form-control">{{ old('note') }}</textarea>
                </div>

            </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                Register
            </button>
        </div>
    </div>
</form>
@endsection