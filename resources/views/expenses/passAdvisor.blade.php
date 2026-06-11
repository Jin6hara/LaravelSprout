{{-- 期間を指定して定期券の候補区間・費用をアドバイスするビュー --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Commuter Pass Advisor</h2>
    <span class="badge text-bg-success">Candidates by Period</span>
</div>

{{-- 検索フォーム --}}
{{-- 検索フォーム --}}
<form method="GET" class="card mb-3 p-3">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3 col-lg-2">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm"
                value="{{ old('from', $from) }}">
        </div>

        <div class="col-12 col-md-3 col-lg-2">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm"
                value="{{ old('to', $to) }}">
        </div>

        <div class="col-12 col-md-3 col-lg-2">
            <label class="form-label small mb-1">Min Count</label>
            <input type="number" name="min_count" min="1" class="form-control form-control-sm"
                value="{{ old('min_count', $min) }}">
        </div>

        <div class="col-12 col-md-3 col-lg-2">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                Search
            </button>
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
$userPasses = $passesMap[$userId] ?? collect();
@endphp

<div class="card mb-4 p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold">
            {{ $u->first_name ?? '' }} {{ $u->family_name ?? '' }}
            [{{ $u->employee_code ?? '' }}]
        </h5>
        @if(\App\Support\CommuterPassNeedChecker::needsPassBySchools(
        $schools->toArray(),
        ($passesMap[$userId] ?? collect()),
        $from // ← 検索日（例: '2025-11-04'）
        ))
        <span class="badge text-bg-danger">定期券必要</span>
        @endif
    </div>

    <div class="row g-3">
        {{-- ▼ 左：School情報 --}}
        <div class="col-md-6">
            <div class="border rounded p-3 bg-light h-100">
                <h6 class="fw-bold mb-2 text-primary">School Schedule</h6>
                @foreach($schools as $schoolKey => $info)
                <div class="mb-2">
                    <div class="fw-bold text-dark">{{ $info['school_name'] }}</div>
                    @forelse($info['lines'] as $line)
                    <div class="small text-secondary">
                        {{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$line->dow] ?? $line->dow }}
                        ：{{ $line->effective_start }} → {{ $line->effective_end }}
                    </div>
                    @empty
                    <div class="text-muted small">No lines</div>
                    @endforelse
                </div>
                @endforeach
            </div>
        </div>

        {{-- ▼ 右：定期券情報 --}}
        <div class="col-md-6">
            <div class="border rounded p-3 bg-light h-100">
                <h6 class="fw-bold mb-2 text-success">Commuter Pass</h6>
                @if($userPasses->isNotEmpty())
                @foreach($userPasses as $p)
                <div class="small text-secondary mb-1">
                    {{ $p->station_from }} → {{ $p->station_to }}:
                    {{ $p->date_from }}〜{{ $p->date_to }}
                </div>
                @endforeach
                @else
                <div class="text-muted small">No commuter pass registered.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endforeach
@endif
@endsection