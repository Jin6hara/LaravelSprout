@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">Commuter Pass Advisor</h2>
    <span class="badge text-bg-success">Candidates by Period</span>
</div>

{{-- 検索フォーム --}}
<form method="GET" class="card mb-2 p-2">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm"
                value="{{ old('from', $from) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm"
                value="{{ old('to', $to) }}">
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-1">Min Count</label>
            <input type="number" name="min_count" min="1" class="form-control form-control-sm"
                value="{{ old('min_count', $min) }}">
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Search</button>
        </div>
    </div>
</form>

{{-- 結果 --}}
@if($grouped->isEmpty())
<div class="text-muted small">No candidates found for the specified period.</div>
@else
@foreach($grouped as $userId => $schools)
@php
$u = $userMap[$userId] ?? null;
@endphp
<div class="card mb-1">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            {{ $u->first_name ?? '' }} {{ $u->family_name ?? '' }}
            [{{ $u->employee_code ?? '' }}]
        </strong>
    </div>

    <div class="card-body py-1">
        @foreach($schools as $school => $info)
        <div class="mb-1">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-1">{{ $school }}</h6>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            {{-- Line ID削除 --}}
                            <th style="width: 70px;">DOW</th>
                            <th>Start–End</th>
                            <th>Effective</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($info['lines'] as $line)
                        <tr>
                            <td>{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$line->dow] ?? $line->dow }}</td>
                            <td>{{ substr($line->start_time,0,5) }}–{{ substr($line->end_time,0,5) }}</td>
                            <td>{{ $line->effective_start }} → {{ $line->effective_end }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endif
@endsection