@extends('layouts.app')

@section('content')
<div class="container py-3">

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Leave Manager</h5>
    <small class="text-muted">{{ $leaves->total() }} leave(s)</small>
  </div>

  {{-- ▼ 検索フォーム --}}
  <form method="GET" action="{{ route('leaves.edit') }}" class="search mb-2" id="leave-search-form">
    <div class="card">
      <div class="card-body py-1 px-2">
        <div class="row g-1 align-items-end">

          {{-- User --}}
          <div class="col-12 col-md-3">
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

          {{-- Kind / Excused / Status --}}
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Kind</label>
            <select name="kind" class="form-select form-select-sm">
              <option value="">—</option>
              @foreach($kindOptions as $v => $label)
                <option value="{{ $v }}" @selected(request('kind')===$v)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Excused</label>
            <select name="excused" class="form-select form-select-sm">
              <option value="">—</option>
              @foreach($excusedOptions as $v => $label)
                <option value="{{ $v }}" @selected(request('excused')===$v)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">—</option>
              @foreach($statusOptions as $v => $label)
                <option value="{{ $v }}" @selected(request('status')===$v)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          {{-- Special / Handle / Reason --}}
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Special Type</label>
            <input type="text" name="special_type" value="{{ request('special_type') }}" class="form-control form-control-sm" placeholder="">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Handle Type</label>
            <input type="text" name="handle_type" value="{{ request('handle_type') }}" class="form-control form-control-sm" placeholder="">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Reason</label>
            <input type="text" name="reason" value="{{ request('reason') }}" class="form-control form-control-sm" placeholder="">
          </div>

          {{-- Start/End --}}
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Start Date</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm" id="filter-start-date">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">End Date</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm" id="filter-end-date">
          </div>

          {{-- Buttons --}}
          <div class="col-12 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill">Search</button>
            <button type="button" id="js-clear-filters" class="btn btn-sm btn-outline-secondary flex-fill">Clear</button>
          </div>

        </div>
      </div>
    </div>

  {{-- 日付バリデーションエラー表示。検索カードのrowを崩さないためcard外に配置 --}}
  <div class="invalid-feedback d-block mt-1" id="date-error" style="display:none;"></div>
  </form>

  <div class="d-flex align-items-center gap-1 mb-2">
    {{-- 空白Leave追加 --}}
    <form method="POST" action="{{ route('leaves.manage.store') }}" class="m-0 p-0">
      @csrf
      {{-- 現在のフィルタを保持（なければ今日/ログインユーザーにフォールバック） --}}
      <input type="hidden" name="start_date" value="{{ request('start_date', request('month', now()->toDateString())) }}">
      <input type="hidden" name="user_id" value="{{ request('user_id', auth()->id()) }}">
      <button type="submit" class="btn btn-sm btn-success">
        ＋ Add Blank
      </button>
    </form>

    {{-- 一括保存 --}}
    <button type="button"
      class="btn btn-sm btn-primary"
      id="js-bulk-save"
      data-url="{{ route('leaves.bulk_update') }}">
      Bulk Save
    </button>
  </div>

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
        {{-- Leave Manager --}}
        <form method="POST"
              action="{{ route('leaves.update', $leave) }}"
              class="h-100 d-flex flex-column js-leave-form"
              data-user-id="{{ $leave->user_id }}">
          @csrf
          @method('PUT')

          {{-- Add Bulk 用ID隠しフィールド (JS用？) --}}
          <input type="hidden" name="id" value="{{ $leave->id }}">

          <div class="card-body py-1 px-2">
            <div class="mb-0 d-grid gap-0">
              {{-- 0: User --}}
              <div class="mb-0">
                <label class="form-label small text-muted mb-0">User</label>
                <select name="user_id" class="form-select form-select-sm" required>
                  {{-- 空白オプション --}}
                  <option value="">—</option>
                  @foreach($userOptions as $u)
                    <option value="{{ $u->id }}" @selected($leave->user_id === $u->id)>
                      {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                    </option>
                  @endforeach
                </select>
              </div>

              {{-- 1: Kind / Special Type --}}
              <div class="row g-1 mb-0">
                <div class="col-6">
                  <label class="form-label small text-muted mb-0">Kind</label>
                  <select name="kind" class="form-select form-select-sm">
                    @foreach($kindOptions as $v => $label)
                    <option value="{{ $v }}" @selected($leave->kind===$v)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label small text-muted mb-0">Special Type</label>
                  <input type="text" name="special_type" class="form-control form-control-sm"
                    value="{{ old('special_type', $leave->special_type) }}"
                    placeholder="">
                </div>
              </div>

              {{-- 2: Excused / Status --}}
              <div class="row g-1 mb-0">
                <div class="col-6">
                  <label class="form-label small text-muted mb-0">Excused</label>
                  <select name="excused" class="form-select form-select-sm">
                    @foreach($excusedOptions as $v => $label)
                    <option value="{{ $v }}" @selected($leave->excused===$v)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label small text-muted mb-0">Status</label>
                  <input type="hidden" name="status" value="approved">
                  <select name="status" class="form-select form-select-sm">
                    @foreach($statusOptions as $v => $label)
                    <option value="{{ $v }}" @selected($leave->status===$v)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- 3: Start --}}
              <div class="row g-1 mb-0">
                <div class="col-7">
                  <label class="form-label small text-muted mb-0">Start Date</label>
                  <input type="date" name="start_date" class="form-control form-control-sm"
                    value="{{ old('start_date', $fmtDate($leave->start_date)) }}">
                </div>
                {{-- <div class="col-5">
                    <label class="form-label small text-muted mb-0">Time Start</label>
                    <input type="time" name="time_start" class="form-control form-control-sm"
                        value="{{ old('time_start', $fmtTime($leave->time_start)) }}"
                        inputmode="numeric" step="60">
                </div> --}}
              </div>

              {{-- 4: End --}}
              <div class="row g-1 mb-0">
                <div class="col-7">
                  <label class="form-label small text-muted mb-0">End Date</label>
                  <input type="date" name="end_date" class="form-control form-control-sm"
                    value="{{ old('end_date', $fmtDate($leave->end_date)) }}">
                </div>
                {{-- <div class="col-5">
                    <label class="form-label small text-muted mb-0">Time End</label>
                    <input type="time" name="time_end" class="form-control form-control-sm"
                        value="{{ old('time_end', $fmtTime($leave->time_end)) }}"
                        inputmode="numeric" step="60">
                </div> --}}
              </div>

              {{-- 5: Reason --}}
              <div class="mb-0">
                <label class="form-label small text-muted mb-0">Reason</label>
                <textarea name="reason" class="form-control form-control-sm" rows="2"
                  placeholder="">{{ old('reason', $leave->reason) }}</textarea>
              </div>

              {{-- 6: Handle Type --}}
              <div class="mb-0">
                <label class="form-label small text-muted mb-0">Handle Type</label>
                <input type="text" name="handle_type" class="form-control form-control-sm"
                  value="{{ old('handle_type', $leave->handle_type) }}"
                  placeholder="Leave this blank">
              </div>
            </div>

            {{-- フッタ操作 --}}
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-2 gap-1">
              @php
                $deleteShifts = $leave->generatedEvents->map(function ($event) use ($fmtDate, $fmtTime) {
                    $original = trim(($event->originalUser?->first_name ?? '') . ' ' . ($event->originalUser?->family_name ?? ''));
                    $assigned = trim(($event->assignedUser?->first_name ?? '') . ' ' . ($event->assignedUser?->family_name ?? ''));

                    return [
                        'id' => $event->id,
                        'date' => $fmtDate($event->event_date),
                        'original' => $original !== '' ? $original : '-',
                        'assigned' => $assigned !== '' ? $assigned : '-',
                        'school' => $event->school_name ?: '-',
                        'time' => trim($fmtTime($event->start_time) . ' - ' . $fmtTime($event->end_time), ' -') ?: '-',
                        'lesson' => $event->Lesson ?: '-',
                        'status' => $event->status ?: '-',
                        'notes' => $event->notes ?: '-',
                    ];
                })->values();
                $deleteShiftPayloadId = 'leave-delete-shifts-' . $leave->id;
              @endphp
              <script type="application/json" id="{{ $deleteShiftPayloadId }}">@json($deleteShifts)</script>

              {{-- 削除ボタン：クラスとdata属性をJSに合わせる --}}
              <button type="button"
                      class="btn btn-sm btn-outline-danger js-delete"
                      data-url="{{ route('leaves.destroy', $leave) }}"
                      data-date="{{ $leave->start_date?->format('Y-m-d') ?? 'this absence' }}"
                      data-shifts-source="{{ $deleteShiftPayloadId }}">
                Delete
              </button>

              {{-- 保存は通常送信 --}}
              <button type="submit" class="btn btn-sm btn-primary"> Save </button>
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

  <form id="js-delete-form" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="keep_generated_shifts" id="js-keep-generated-shifts" value="0">
  </form>

  <div class="mt-3">
    {{ $leaves->withQueryString()->links() }}
  </div>
  @else
  <div class="alert alert-light border">
    No Leave matches.
  </div>
  @endif

</div>

<!-- ✅ Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-sm">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title" id="deleteConfirmLabel">Delete Confirmation</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="deleteConfirmText">Are you sure you want to delete this absence?</p>
        <div class="small text-muted mb-2" id="deleteGeneratedShiftSummary"></div>
        <div class="generated-shift-list" id="deleteGeneratedShiftWrap" style="display:none;"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="keepGeneratedShiftsBtn">Keep Shift</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete Absence & Shift</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('styles')
<style>
/* Leaveカード・検索フォームカード共通デザイン */
.card {
  background-color: #ecececc4 !important;
  border: 1px solid #c1c5caff;
  border-radius: 6px;
}

.generated-shift-list {
  max-height: min(52vh, 31rem);
  overflow-y: auto;
  padding-right: .25rem;
}

.generated-shift-card {
  background: #f8fbff;
  border: 1px solid #c8d8ec;
  border-radius: 6px;
  padding: .75rem;
}

.generated-shift-card + .generated-shift-card {
  margin-top: .5rem;
}

.generated-shift-meta {
  display: grid;
  gap: .5rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.generated-shift-field {
  min-width: 0;
}

.generated-shift-label {
  color: #6c757d;
  display: block;
  font-size: .75rem;
  line-height: 1.1;
}

.generated-shift-value {
  overflow-wrap: anywhere;
}

.generated-shift-wide {
  background: #fff;
  border: 1px solid #dce6f2;
  border-radius: 4px;
  margin-top: .5rem;
  padding: .5rem;
  white-space: pre-wrap;
}

@media (max-width: 576px) {
  .generated-shift-meta {
    grid-template-columns: 1fr;
  }
}
</style>
@endpush

@push('scripts')
<script>
  // 削除確認ダイアログ（日付表示） → Bootstrap Modal に置き換え（英語UI）
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-delete');
    if (!btn) return;

    // 対象休暇情報をモーダルに反映
    const date = btn.dataset.date || 'this leave';
    const modalText = document.getElementById('deleteConfirmText');
    modalText.textContent = `Are you sure you want to delete the absence for ${date}?`;

    const shiftsSource = document.getElementById(btn.dataset.shiftsSource);
    const shifts = shiftsSource ? JSON.parse(shiftsSource.textContent || '[]') : [];
    const summary = document.getElementById('deleteGeneratedShiftSummary');
    const wrap = document.getElementById('deleteGeneratedShiftWrap');
    wrap.innerHTML = '';

    if (shifts.length > 0) {
      summary.textContent = `${shifts.length} generated shift(s) are linked to this absence. They will also be deleted unless you choose Keep Shift.`;
      wrap.style.display = '';

      shifts.forEach((shift) => {
        const card = document.createElement('div');
        card.className = 'generated-shift-card';

        const title = document.createElement('div');
        title.className = 'fw-semibold mb-2';
        title.textContent = `Generated Shift #${shift.id || '-'}`;
        card.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'generated-shift-meta';
        [
          ['Date', 'date'],
          ['Original', 'original'],
          ['Assigned', 'assigned'],
          ['School', 'school'],
          ['Time', 'time'],
          ['Status', 'status'],
        ].forEach(([label, key]) => {
          const field = document.createElement('div');
          field.className = 'generated-shift-field';

          const labelEl = document.createElement('span');
          labelEl.className = 'generated-shift-label';
          labelEl.textContent = label;

          const valueEl = document.createElement('div');
          valueEl.className = 'generated-shift-value';
          valueEl.textContent = shift[key] || '-';

          field.append(labelEl, valueEl);
          meta.appendChild(field);
        });
        card.appendChild(meta);

        ['lesson', 'notes'].forEach((key) => {
          const wide = document.createElement('div');
          wide.className = 'generated-shift-wide';

          const labelEl = document.createElement('span');
          labelEl.className = 'generated-shift-label';
          labelEl.textContent = key === 'lesson' ? 'Lesson' : 'Notes';

          const valueEl = document.createElement('div');
          valueEl.className = 'generated-shift-value';
          valueEl.textContent = shift[key] || '-';

          wide.append(labelEl, valueEl);
          card.appendChild(wide);
        });

        wrap.appendChild(card);
      });
    } else {
      summary.textContent = 'No generated shifts are linked to this absence.';
      wrap.style.display = 'none';
    }

    const form = document.getElementById('js-delete-form');
    form.action = btn.dataset.url;
    const keepInput = document.getElementById('js-keep-generated-shifts');
    keepInput.value = '0';

    // モーダル表示
    const modalEl = document.getElementById('deleteConfirmModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Shiftごと削除する通常ルート
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.onclick = () => {
      keepInput.value = '0';
      modal.hide();
      form.submit();
    };

    // Shiftを手動シフトとして残す拡張ルート
    const keepBtn = document.getElementById('keepGeneratedShiftsBtn');
    keepBtn.onclick = () => {
      keepInput.value = '1';
      modal.hide();
      form.submit();
    };
  });
</script>

<script>
document.getElementById('js-bulk-save')?.addEventListener('click', async (e) => {
  const url = e.currentTarget.dataset.url;
  const forms = Array.from(document.querySelectorAll('form.js-leave-form'));
  if (!url || forms.length === 0) return;

  const items = forms.map(f => {
    const fd = new FormData(f);
    // FormData → 普通のオブジェクトへ
    const obj = Object.fromEntries(fd.entries());
    // time/date の空文字は null に正規化（サーババリデーション対策）
    ['end_date','time_start','time_end','special_type','handle_type','reason'].forEach(k => {
      if (obj[k] === '') obj[k] = null;
    });

    // ✅ user_id が欠落していたらフォーム属性やDOMから補完
    if (!obj.user_id) {
      // data-user-id を最優先で使う
      const fromDataset = f.dataset?.userId || null;
      // disabled ではない select から拾う（通常はこれで入る）
      const fromSelect = f.querySelector('select[name="user_id"]:not([disabled])')?.value || null;
      obj.user_id = fromDataset || fromSelect || '';
    }

    return obj;
  }).filter((obj, idx) => {
    // ✅ まだ user_id が空なら、このカードをハイライトして送信対象から外す
    if (!obj.user_id) {
      const card = forms[idx].closest('.card');
      card?.classList.add('border','border-warning');
      // 可能ならトーストや小さな注釈で知らせる（任意）
      return false;
    }
    return true;
  });

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ items })
    });
    const json = await res.json();
    if (res.ok && json.ok) {
      // PRG + flash を使うためにリロード
      location.reload();
    } else {
      console.error(json);
      alert(json.message || '一括保存に失敗しました。');
    }
  } catch (err) {
    console.error(err);
    alert('通信エラーが発生しました。');
  }
});
</script>

<script>
(() => {
  const form = document.getElementById('leave-search-form');
  const startEl = document.getElementById('filter-start-date');
  const endEl = document.getElementById('filter-end-date');
  const errEl = document.getElementById('date-error');
  const clearBtn = document.getElementById('js-clear-filters');

  function showError(msg){
    errEl.textContent = msg;
    errEl.style.display = 'block';
  }
  function clearError(){
    errEl.textContent = '';
    errEl.style.display = 'none';
  }

  // ✅ 検索フォーム送信前の軽いチェック
  form?.addEventListener('submit', (e) => {
    clearError();
    const s = startEl?.value?.trim() || '';
    const d = endEl?.value?.trim() || '';

    if (!s && d) {
      e.preventDefault();
      showError('Start Date を指定してください。');
      return;
    }
    if (s && d && d < s) {
      e.preventDefault();
      showError('End Date は Start Date 以降で指定してください。');
    }
  });

  // ✅ 条件クリア（URLリロード）
  clearBtn?.addEventListener('click', () => {
    const baseUrl = "{{ route('leaves.edit') }}";
    window.location.href = baseUrl; // クエリ無しで再読み込み
  });
})();
</script>
@endpush
