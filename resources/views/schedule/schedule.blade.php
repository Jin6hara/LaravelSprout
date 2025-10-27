@extends('layouts.app')
@php
use App\Support\TimeString;
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">All Schedules</h2>
    <span class="badge text-bg-primary">Admin View (Editable)</span>
</div>

{{-- ✅ 検索フォーム --}}
<form method="GET" class="card mb-2 p-2">
    <div class="row g-1 align-items-end">
        {{-- active_on --}}
        <div class="col-md-2">
            <label class="form-label small mb-1">Active On</label>
            <input type="date" name="active_on" class="form-control form-control-sm"
                value="{{ request('active_on') }}">
        </div>

        {{-- Active Until --}}
        <div class="col-md-2">
            <label class="form-label small mb-1">Active Until</label>
            <input type="date" name="active_until" class="form-control form-control-sm"
                value="{{ request('active_until') }}">
        </div>

        {{-- ユーザー選択 --}}
        <div class="col-md-3">
            <label class="form-label small mb-1">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">— All Users —</option>
                @foreach($userOptions as $u)
                <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>
                    {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                </option>
                @endforeach
            </select>
        </div>

        {{-- active --}}
        <div class="col-md-1">
            <label class="form-label small mb-1">Active</label>
            <select name="active" class="form-select form-select-sm">
                <option value="">—</option>
                <option value="1" @selected(request('active')==='1' )>Active</option>
                <option value="0" @selected(request('active')==='0' )>Inactive</option>
            </select>
        </div>

        {{-- label --}}
        <div class="col-md-3">
            <label class="form-label small mb-1">Label</label>
            <input type="text" name="label" class="form-control form-control-sm"
                value="{{ request('label') }}" placeholder="Weekly / Summer etc">
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Search</button>
        </div>
    </div>
</form>

<div class="d-flex justify-content-end mb-2 y-2">
    {{-- 新規追加ボタン --}}
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
        + New Schedule
    </button>
</div>

<div class="row g-1">
    @forelse($schedules as $schedule)
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $schedule->user->first_name ?? '' }} {{ $schedule->user->family_name ?? '' }}</strong>
                    <span class="text-muted small ms-2">[ID: {{ $schedule->user->id }}]</span>
                </div>
                <span class="badge {{ $schedule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('schedules.update', $schedule) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-1 align-items-end">
                        {{-- User ID （原則変更不可）
                        <div class="col-md-3">
                            <label class="form-label small mb-1">User</label>
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">— Select User —</option>
                                @foreach($userOptions as $u)
                                <option value="{{ $u->id }}" @selected(old('user_id', $schedule->user_id ?? '') == $u->id)>
                        {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                        </option>
                        @endforeach
                        </select>
                    </div>
                    --}}

                    <div class="col-md-2">
                        <label class="form-label small mb-1">Label</label>
                        <input type="text" name="label" class="form-control form-control-sm"
                            value="{{ old('label', $schedule->label) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small mb-1">Start</label>
                        <input type="date" name="effective_start"
                            class="form-control form-control-sm"
                            value="{{ TimeString::normalizeToYmd($schedule->effective_start) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small mb-1">End</label>
                        <input type="date" name="effective_end"
                            class="form-control form-control-sm"
                            value="{{ TimeString::normalizeToYmd($schedule->effective_end) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small mb-1">Active</label>
                        <select name="is_active" class="form-select form-select-sm">
                            <option value="1" @selected($schedule->is_active)>Active</option>
                            <option value="0" @selected(!$schedule->is_active)>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small mb-1">Total Minutes</label>
                        <input type="number" class="form-control form-control-sm" value="{{ $schedule->total_minutes }}" readonly>
                    </div>

                    <div class="col-md-1 text-end">
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </div>
                    {{-- Ajax Delete ボタン --}}
                    <div class="col-md-1 text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger w-100 js-delete-schedule"
                            data-delete-url="{{ route('schedules.destroy', $schedule) }}"
                            data-active-on="{{ request('active_on') }}"
                            data-active-until="{{ request('active_until') }}"
                            data-active="{{ request('active') }}"
                            data-label="{{ request('label') }}">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@empty
<div class="col-12 text-muted small">No schedules found.</div>
@endforelse
</div>
{{-- 新規作成モーダル --}}
<div class="modal fade" id="createScheduleModal" tabindex="-1" aria-labelledby="createScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('schedules.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="createScheduleModalLabel">Create New Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            {{-- 重要：検索条件を引き継ぐ --}}
            <input type="hidden" name="active_on" value="{{ request('active_on') }}">
            <input type="hidden" name="active_until" value="{{ request('active_until') }}">
            <input type="hidden" name="active" value="{{ request('active') }}">
            <input type="hidden" name="label" value="{{ request('label') }}">
            {{-- 重要：検索条件を引き継ぐ --}}

            <div class="modal-body">
                {{-- ユーザー選択（作成対象ユーザー） --}}
                <div class="mb-3">
                    <label class="form-label small mb-1">User</label>
                    <select name="user_id" class="form-select form-select-sm" required>
                        <option value="">— Select User —</option>
                        @forelse($eligibleUsers as $u)
                        <option value="{{ $u->id }}">
                            {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                        </option>
                        @empty
                        <option value="" disabled>（該当ユーザーなし）</option>
                        @endforelse
                    </select>
                    <div class="form-text">
                        追加可能条件：在籍中、または入社予定が今後1ヶ月以内
                    </div>
                </div>

                {{-- 必要であれば初期ラベルの入力欄を出す場合（任意） --}}
                {{-- <div class="mb-2">
          <label class="form-label small mb-1">Label (optional)</label>
          <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. Weekly">
        </div> --}}
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" @disabled($eligibleUsers->isEmpty())>Create</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-delete-schedule');
        if (!btn) return;

        const msg = 'Are you sure you want to delete this schedule?\n(Deletion will fail if related schedule lines still exist.)';
        if (!window.confirm(msg)) return;

        const url = btn.dataset.deleteUrl;
        const payload = {
            active_on: btn.dataset.activeOn || '',
            active_until: btn.dataset.activeUntil || '',
            active: btn.dataset.active || '',
            label: btn.dataset.label || '',
        };

        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        const csrf = tokenEl ? tokenEl.getAttribute('content') : '';

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin', // ★ これを明示
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!res.ok) {
                const err = (data && data.message) || 'Delete failed.';
                alert(err);
                return;
            }

            // サーバが flash を積んでいるので、ただリダイレクトすればOK
            if (data && data.redirect) {
                window.location.href = data.redirect;
            }
        } catch (err) {
            alert('Network error. Please try again.');
        }
    });
</script>
@endpush