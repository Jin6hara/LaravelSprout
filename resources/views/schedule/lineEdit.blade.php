{{-- resources/views/schedule/lineEdit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Schedule Lines</h2>
        <div class="text-muted small">一行＝一カード（編集可 / 横並び）</div>
    </div>
</div>

{{-- 検索フォーム（基準日/スケジュール） --}}
<form method="GET" action="{{ route('schedules.edit') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label small mb-1">有効日（Active On）</label>
                <input type="date" name="active_on" class="form-control form-control-sm" value="{{ $activeOn }}">
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label small mb-1">Schedule</label>
                <select name="schedule_id" class="form-select form-select-sm">
                    <option value="">（すべて）</option>
                    @foreach($scheduleOptions as $opt)
                    <option value="{{ $opt->id }}" @selected($scheduleId==$opt->
                        id)>{{ $opt->label ?? 'Schedule #'.$opt->id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <button class="btn btn-sm btn-primary w-100">検索</button>
            </div>
        </div>
    </div>
</form>

@if (session('status'))
<div class="alert alert-success py-2">{{ session('status') }}</div>
@endif

@if($lines->isEmpty())
<div class="alert alert-secondary">該当する Schedule Line はありません。</div>
@else
{{-- ★ 1カード/行に固定 --}}
<div class="row g-3">
    @foreach($lines as $line)
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>#{{ $line->id }}</strong>
                    <span class="badge text-bg-light">
                        {{ $line->schedule->label ?? ('Schedule '.$line->schedule_id) }}
                    </span>
                </div>

                {{-- 担当ユーザー（active_on が無ければ today 基準） --}}
                @php
                $chips = collect($usersBySchedule[$line->schedule_id] ?? []);
                $baseLabel = $activeOn ?: 'today';
                @endphp
                <div class="mt-2">
                    <div class="small text-muted mb-1">User（{{ $baseLabel }} 時点）</div>
                    @if($chips->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($chips as $u)
                        <span class="badge rounded-pill text-bg-secondary">
                            {{ $u->family_name }} {{ $u->first_name }}
                            @if(!empty($u->employee_code)) [{{ $u->employee_code }}] @endif
                        </span>
                        @endforeach
                    </div>
                    @else
                    <div class="text-muted small">—</div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('schedule_lines.update', $line) }}">
                @csrf
                @method('PUT')

                {{-- ★ 変更項目を横並び（レスポンシブで折り返し） --}}
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">DOW</label>
                            <select name="dow" class="form-select form-select-sm">
                                @foreach($dowOptions as $val => $label)
                                <option value="{{ $val }}" @selected($line->dow === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-3 col-lg-3">
                            <label class="form-label small mb-1">School</label>
                            <input type="text" name="school_name" class="form-control form-control-sm"
                                value="{{ old('school_name', $line->school_name) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">Start</label>
                            <input type="time" name="start_time" class="form-control form-control-sm"
                                value="{{ old('start_time', \Illuminate\Support\Str::of($line->start_time)->substr(0,5)) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">End</label>
                            <input type="time" name="end_time" class="form-control form-control-sm"
                                value="{{ old('end_time', \Illuminate\Support\Str::of($line->end_time)->substr(0,5)) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-2">
                            <label class="form-label small mb-1">Effective Start</label>
                            <input type="date" name="effective_start" class="form-control form-control-sm"
                                value="{{ old('effective_start', optional($line->effective_start)->toDateString()) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-2">
                            <label class="form-label small mb-1">Effective End</label>
                            <input type="date" name="effective_end" class="form-control form-control-sm"
                                value="{{ old('effective_end', optional($line->effective_end)->toDateString()) }}">
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted">更新: {{ $line->updated_at?->format('Y-m-d H:i') }}</small>
                    <button class="btn btn-sm btn-success">保存</button>
                </div>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection