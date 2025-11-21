@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">
        Route Declaration
        <small class="text-muted fs-6">
            for {{ $target->first_name }} {{ $target->family_name }} ({{ $target->employee_code }})
        </small>
    </h2>

    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
        Back
    </a>
</div>

{{-- ★ ここでフォームを開始：両方のカードを丸ごと含める --}}
<form id="routeForm"
    method="POST"
    action="{{ $isAdminMode
                ? route('routes.admin.store', $target)
                : route('routes.store') }}">
    @csrf

    {{-- ===== route_declarations カード ===== --}}
    <div class="card mb-3">
        <div class="card-header">
            Route Declaration (Basic Info)
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Closest Station <span class="text-danger">*</span></label>
                    <input type="text"
                        name="closest_station"
                        value="{{ old('closest_station') }}"
                        class="form-control"
                        required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Train Line</label>
                    <input type="text"
                        name="train_line"
                        value="{{ old('train_line') }}"
                        class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Effective Date <span class="text-danger">*</span></label>
                    <input type="date"
                        name="effective_date"
                        value="{{ old('effective_date') }}"
                        class="form-control"
                        required>
                </div>

                <div class="col-12">
                    <label class="form-label">Reason for Submitting Expenses</label>
                    <textarea name="reason"
                        rows="2"
                        class="form-control">{{ old('reason') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== route_details カード ===== --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Routes (Details)</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRouteRow">
                + ルート追加
            </button>
        </div>

        <div class="card-body">
            <div id="routeRows">
                @php
                $oldDetails = old('details', [
                ['dow' => 'Mon'],
                ]);
                @endphp

                @foreach($oldDetails as $i => $row)
                <div class="border rounded p-2 mb-2 route-row">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-0">DOW</label>
                            <select name="details[{{ $i }}][dow]"
                                class="form-select form-select-sm">
                                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow)
                                <option value="{{ $dow }}"
                                    @selected(($row['dow'] ?? '' )===$dow)>
                                    {{ $dow }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small mb-0">From</label>
                            <input type="text"
                                name="details[{{ $i }}][from_station]"
                                value="{{ $row['from_station'] ?? '' }}"
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small mb-0">To</label>
                            <input type="text"
                                name="details[{{ $i }}][to_station]"
                                value="{{ $row['to_station'] ?? '' }}"
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-0">Trip Type</label>
                            <select name="details[{{ $i }}][trip_type]"
                                class="form-select form-select-sm">
                                <option value="round_trip"
                                    @selected(($row['trip_type'] ?? '' )==='round_trip' )>
                                    Round Trip
                                </option>
                                <option value="one_way"
                                    @selected(($row['trip_type'] ?? '' )==='one_way' )>
                                    One Way
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-0">Amount (JPY)</label>
                            <input type="number"
                                name="details[{{ $i }}][amount]"
                                value="{{ $row['amount'] ?? '' }}"
                                class="form-control form-control-sm"
                                min="0" step="1">
                        </div>

                        <div class="col-md-6 mt-1">
                            <label class="form-label small mb-0">Route Text</label>
                            <input type="text"
                                name="details[{{ $i }}][route_text]"
                                value="{{ $row['route_text'] ?? '' }}"
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-md-5 mt-1">
                            <label class="form-label small mb-0">Note</label>
                            <input type="text"
                                name="details[{{ $i }}][note]"
                                value="{{ $row['note'] ?? '' }}"
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-md-1 mt-1 text-end">
                            <button type="button"
                                class="btn btn-outline-danger btn-sm remove-route">
                                &times;
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                Submit
            </button>
        </div>
    </div>

</form> {{-- ★ ここで form を閉じる --}}
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        let index = document.querySelectorAll('.route-row').length;

        const container = document.getElementById('routeRows');
        const addBtn = document.getElementById('addRouteRow');

        function buildRowHtml(i) {
            return `
            <div class="border rounded p-2 mb-2 route-row">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-0">DOW</label>
                        <select name="details[${i}][dow]" class="form-select form-select-sm">
                            <option value="Mon">Mon</option>
                            <option value="Tue">Tue</option>
                            <option value="Wed">Wed</option>
                            <option value="Thu">Thu</option>
                            <option value="Fri">Fri</option>
                            <option value="Sat">Sat</option>
                            <option value="Sun">Sun</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">From</label>
                        <input type="text"
                            name="details[${i}][from_station]"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">To</label>
                        <input type="text"
                            name="details[${i}][to_station]"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Trip Type</label>
                        <select name="details[${i}][trip_type]" class="form-select form-select-sm">
                            <option value="round_trip">Round Trip</option>
                            <option value="one_way">One Way</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Amount (JPY)</label>
                        <input type="number"
                            name="details[${i}][amount]"
                            class="form-control form-control-sm"
                            min="0" step="1">
                    </div>
                    <div class="col-md-6 mt-1">
                        <label class="form-label small mb-0">Route Text</label>
                        <input type="text"
                            name="details[${i}][route_text]"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-md-5 mt-1">
                        <label class="form-label small mb-0">Note</label>
                        <input type="text"
                            name="details[${i}][note]"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-md-1 mt-1 text-end">
                        <button type="button"
                                class="btn btn-outline-danger btn-sm remove-route">
                            &times;
                        </button>
                    </div>
                </div>
            </div>`;
        }

        addBtn.addEventListener('click', () => {
            container.insertAdjacentHTML('beforeend', buildRowHtml(index++));
        });

        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.remove-route');
            if (!btn) return;

            const rows = container.querySelectorAll('.route-row');
            if (rows.length <= 1) {
                // 最後の1行は消さないようにするならここで return
                btn.closest('.route-row').querySelectorAll('input,select').forEach(el => el.value = '');
                return;
            }
            btn.closest('.route-row').remove();
        });
    });
</script>
@endpush