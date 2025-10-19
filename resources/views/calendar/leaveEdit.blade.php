@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Leave Manager</h2>
    <span class="badge text-bg-primary">Admin View</span>
</div>

{{-- ▼ 検索フォーム（月・ユーザー） --}}
<form method="GET" action="{{ route('leaves.edit') }}" class="mb-3">
    <div class="card">
        <div class="card-body p-2">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Month</label>
                    <input type="month" name="month" class="form-control form-control-sm"
                        value="{{ $month }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>
                            {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-sm btn-primary w-100">Search</button>
                </div>
            </div>
        </div>
    </div>
</form>

@if($leaves->count())
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
    @foreach($leaves as $leave)
    @php
    $now = now();
    $diffUpdated = $leave->updated_at?->diffInMinutes($now);
    $isNew = $leave->created_at && $leave->created_at->equalTo($leave->updated_at);
    $cls =
    $isNew ? 'text-success'
    : (($diffUpdated ?? 10_000) <= 10 ? 'text-danger'
        : (($diffUpdated ?? 10_000) <=30 ? 'text-warning'
        : (($diffUpdated ?? 10_000) <=60 ? 'text-dark' : 'text-muted' )));
        $action=$isNew ? 'Created' : 'Updated' ;
        $time=optional($leave->updated_at)->format('Y-m-d H:i');
        @endphp

        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm">
                {{-- まだ保存先ルート未定のため actionは空。後で PUT/PATCH に差し替え可能 --}}
                <form class="h-100 d-flex flex-column js-leave-form"
                    data-leave-id="{{ $leave->id }}">
                    @csrf
                    {{-- @method('PUT') ← 更新ルート作成後に有効化 --}}

                    <div class="card-body p-2 light-blue">
                        <div class="mb-0 d-grid gap-0">
                            {{-- 0: User --}}
                            <div class="mb-0">
                                <label class="form-label small mb-0">User</label>
                                <select name="user_id" class="form-select form-select-sm">
                                    @foreach($userOptions as $u)
                                    <option value="{{ $u->id }}" @selected($leave->user_id===$u->id)>
                                        {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- 1: Kind / Status --}}
                            <div class="row g-1 mb-0">
                                <div class="col-6">
                                    <label class="form-label small mb-0">Kind</label>
                                    <select name="kind" class="form-select form-select-sm">
                                        @foreach($kindOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($leave->kind===$v)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach($statusOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($leave->status===$v)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- 2: Excused / Special Type --}}
                            <div class="row g-1 mb-0">
                                <div class="col-6">
                                    <label class="form-label small mb-0">Excused</label>
                                    <select name="excused" class="form-select form-select-sm">
                                        @foreach($excusedOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($leave->excused===$v)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Special Type</label>
                                    <input type="text" name="special_type" class="form-control form-control-sm"
                                        value="{{ old('special_type', $leave->special_type) }}"
                                        placeholder="Details for special leave">
                                </div>
                            </div>

                            {{-- 3: Dates --}}
                            <div class="row g-1 mb-0">
                                <div class="col-6">
                                    <label class="form-label small mb-0">Start Date</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm"
                                        value="{{ old('start_date', $fmtDate($leave->start_date)) }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">End Date (Optional)</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm"
                                        value="{{ old('end_date', $fmtDate($leave->end_date)) }}">
                                </div>
                            </div>

                            {{-- 4: Times --}}
                            <div class="row g-1 mb-0">
                                <div class="col-6">
                                    <label class="form-label small mb-0">Time Start (Optional)</label>
                                    <input type="time" name="time_start" class="form-control form-control-sm"
                                        value="{{ old('time_start', $fmtTime($leave->time_start)) }}"
                                        inputmode="numeric" step="60">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Time End (Optional)</label>
                                    <input type="time" name="time_end" class="form-control form-control-sm"
                                        value="{{ old('time_end', $fmtTime($leave->time_end)) }}"
                                        inputmode="numeric" step="60">
                                </div>
                            </div>

                            {{-- 5: Reason --}}
                            <div class="mb-0">
                                <label class="form-label small mb-0">Reason</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2"
                                    placeholder="理由">{{ old('reason', $leave->reason) }}</textarea>
                            </div>

                            {{-- 6: Handle Type --}}
                            <div class="mb-0">
                                <label class="form-label small mb-0">Handle Type</label>
                                <input type="text" name="handle_type" class="form-control form-control-sm"
                                    value="{{ old('handle_type', $leave->handle_type) }}"
                                    placeholder="後日調整、給与控除等の備考">
                            </div>
                        </div>

                        {{-- フッタ操作（後でルート紐付け可） --}}
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-2 gap-1 mt-2">
                            {{-- 削除ボタン --}}
                            <button type="button"
                                class="btn btn-sm btn-outline-danger js-delete-leave"
                                data-url="{{ route('leaves.destroy', $leave) }}">
                                削除
                            </button>

                            {{-- 保存ボタン --}}
                            <button type="button"
                                class="btn btn-sm btn-primary js-save-leave"
                                data-url="{{ route('leaves.update', $leave) }}">
                                保存
                            </button>
                        </div>

                        <small class="{{ $cls }} text-left d-block mt-1">
                            {{ $action }}: {{ $time }}
                        </small>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
</div>
@else
<div class="alert alert-light border">
    対象月（{{ $periodStart->format('Y-m-d') }}〜{{ $periodEnd->format('Y-m-d') }}）に該当する休暇はありません。
</div>
@endif

{{-- Ajax保存/削除 --}}
@push('scripts')
<script>
document.addEventListener('click', async (e) => {
  const saveBtn = e.target.closest('.js-save-leave');
  const deleteBtn = e.target.closest('.js-delete-leave');

  // 保存処理
  if (saveBtn) {
    const url = saveBtn.dataset.url;
    const card = saveBtn.closest('.card');
    const form = card.querySelector('form.js-leave-form');
    const data = Object.fromEntries(new FormData(form));

    if (!url) return alert('URLが設定されていません。');
    if (!confirm('この内容で保存しますか？')) return;

    try {
      const res = await fetch(url, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      });
      const json = await res.json();

      if (res.ok && json.ok) {
        alert('保存しました。');
        location.reload();
      } else {
        alert('保存に失敗しました。\n' + (json.message || 'Validation error'));
        console.error(json);
      }
    } catch (err) {
      console.error(err);
      alert('通信エラーが発生しました。');
    }
  }

  // 削除処理
  if (deleteBtn) {
    const url = deleteBtn.dataset.url;
    if (!url) return alert('URLが設定されていません。');
    if (!confirm('この休暇データを削除しますか？')) return;

    try {
      const res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      });
      const json = await res.json();

      if (res.ok && json.ok) {
        alert('削除しました。');
        location.reload();
      } else {
        alert('削除に失敗しました。\n' + (json.message || 'Error'));
        console.error(json);
      }
    } catch (err) {
      console.error(err);
      alert('通信エラーが発生しました。');
    }
  }
});
</script>
@endpush

@endsection