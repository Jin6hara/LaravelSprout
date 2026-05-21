{{-- resources/views/schedule/lineEdit.blade.php --}}
@extends('layouts.app')

@push('styles')
<style>
.schedule-line-block {
    display: grid;
    grid-template-columns: minmax(540px, 75%) 1fr;
    gap: 0;
    border: 1px solid #999;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.35rem;
    background: #fff;
}

.schedule-line-left,
.schedule-line-right {
    padding: 0.35rem 0.5rem;
}

.schedule-line-left {
    border-right: 2px solid #333;
    background: #fdf7f8;
}

.schedule-line-right {
    background: #fff;
    overflow-y: auto;
    min-width: 0;
    height: 155px;
    min-height: 155px;
    max-height: 420px;
    resize: vertical;
}

.schedule-line-grid {
    display: grid;
    grid-template-columns: 2fr 0.6fr 1fr 80px 80px;
    gap: 0.25rem;
    align-items: end;
    margin-bottom: 0.25rem;
}

.schedule-line-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 0.25rem;
    align-items: end;
    margin-bottom: 0.25rem;
}

.schedule-line-actions {
    display: flex;
    gap: 0.25rem;
    flex-wrap: nowrap;
    justify-content: flex-end;
}

.schedule-line-block .form-control-sm,
.schedule-line-block .form-select-sm {
    min-height: 26px;
    padding-top: 0.1rem;
    padding-bottom: 0.1rem;
    font-size: 0.78rem;
}

.schedule-line-block label {
    font-size: 0.7rem;
    margin-bottom: 0.05rem;
    color: #666;
    display: block;
}

.schedule-line-block .btn-sm {
    padding: 0.15rem 0.4rem;
    font-size: 0.75rem;
}

.schedule-details-compact {
    font-size: 0.78rem;
}

.schedule-detail-period-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #444;
    background: #e9ecef;
    padding: 0.1rem 0.4rem;
    margin-top: 0.3rem;
    border-top: 1px solid #adb5bd;
    border-radius: 3px 3px 0 0;
    white-space: nowrap;
}

.schedule-details-compact > .schedule-detail-period-header:first-child {
    margin-top: 0;
    border-top: none;
}

.schedule-detail-period-header .sdr-count {
    font-weight: 400;
    color: #888;
}

.schedule-detail-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 100px);
    gap: 0.5rem;
    line-height: 1.65;
    white-space: nowrap;
    border-bottom: 1px solid #f0f0f0;
    padding: 0 0.25rem;
}

.schedule-detail-row:last-child {
    border-bottom: none;
}

.schedule-detail-row > span {
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

@media (max-width: 992px) {
    .schedule-line-block {
        grid-template-columns: 1fr;
    }

    .schedule-line-left {
        border-right: none;
        border-bottom: 1px solid #999;
    }
}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Schedule Lines</h2>
    </div>
    <div>
        <a href="{{ route('schedules.school_timetable') }}" class="btn btn-sm btn-outline-secondary">
            School Timetable
        </a>
    </div>
</div>

{{-- 検索フォーム --}}
<form method="GET" action="{{ route('schedules.edit') }}" class="card mb-2" style="background:#fdf7f8;">
    <div class="card-body py-1 px-2">
        <div class="row g-1 align-items-end">
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                <label class="form-label small mb-1">Active On</label>
                <input type="date" name="active_on" class="form-control form-control-sm"
                    value="{{ $activeOn }}">
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                <label class="form-label small mb-1">Active Until</label>
                <input type="date" name="active_until" class="form-control form-control-sm"
                    value="{{ request('active_until') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">(All)</option>
                    <option value="null" @selected($userId==='null')>Not Assigned</option>
                    @foreach($userOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected($userId==$opt['id'])>
                        {{ $opt['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-1">
                <label class="form-label small mb-1">DOW</label>
                <select name="dow" class="form-select form-select-sm">
                    <option value="">(All)</option>
                    @foreach($dowOptions as $val => $label)
                    <option value="{{ $val }}" @selected((string)request('dow','')===(string)$val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                <label class="form-label small mb-1">School</label>
                <input type="text" name="school_name" class="form-control form-control-sm"
                    value="{{ request('school_name','') }}" placeholder="部分一致">
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-1">
                <label class="form-label small mb-1">Related to</label>
                <input type="number" name="schedule_line_id" class="form-control form-control-sm"
                    value="{{ request('schedule_line_id','') }}" placeholder="例) 123">
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-1">
                <button class="btn btn-sm btn-primary w-100">Search</button>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-1">
                <a href="{{ route('schedules.edit') }}" class="btn btn-sm btn-outline-secondary w-100">
                    Clear
                </a>
            </div>
        </div>
    </div>
</form>

<div class="text-start mb-2">
    <button type="button"
        id="add-line-btn"
        class="btn btn-sm btn-outline-primary">
        ＋ Add Blank Line
    </button>

    <button type="button" id="bulk-save-btn" class="btn btn-sm btn-primary">
        Bulk Save
    </button>
</div>

@if($lines->isEmpty())
<div class="alert alert-secondary">該当する Schedule Line はありません。</div>
@else

{{-- 各行の form を block 外に定義（HTML仕様上 block 内に form を入れ子にすると崩れるため form 属性方式を使用） --}}
@foreach($lines as $line)
<form id="line-form-{{ $line->id }}"
      method="POST"
      action="{{ route('schedule_lines.update', $line) }}"
      class="d-none">
    @csrf
    @method('PUT')
    <input type="hidden" name="__line_id" value="{{ $line->id }}">
</form>
@endforeach

@foreach($lines as $line)
@php $isMyOld = old('__line_id') == $line->id; @endphp

<div class="schedule-line-block" data-line-id="{{ $line->id }}">

    {{-- 左側：Schedule Line 編集エリア --}}
    <div class="schedule-line-left">

        {{-- ヘッダー行：ID / 更新日時 / DOW・学校ハイライト --}}
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="fw-bold text-muted" style="font-size:0.78rem">#{{ $line->id }}</span>
            <span class="text-muted" style="font-size:0.7rem">更新: {{ $line->updated_at?->format('m-d H:i') }}</span>
            <div class="ms-auto d-flex gap-1">
                <span class="badge bg-secondary-subtle text-body-secondary" style="font-size:0.72rem">{{ $dowOptions[$line->dow] ?? $line->dow }}</span>
                <span class="badge bg-info-subtle text-body" style="font-size:0.72rem; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $line->school_name }}</span>
            </div>
        </div>

        {{-- 上段: User / DOW / School / Start / End --}}
        <div class="schedule-line-grid">
            <div>
                <label>User (Owner)</label>
                <select name="user_id" class="form-select form-select-sm js-user-select"
                    form="line-form-{{ $line->id }}"
                    data-selected="{{ $line->user_id ?? '' }}">
                </select>
            </div>
            <div>
                <label>DOW</label>
                <select name="dow" class="form-select form-select-sm" form="line-form-{{ $line->id }}">
                    @foreach($dowOptions as $val => $label)
                    <option value="{{ $val }}" @selected(($isMyOld ? (int)old('dow') : $line->dow) === $val)>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>School</label>
                <input type="text" name="school_name" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    value="{{ $isMyOld ? old('school_name') : $line->school_name }}">
            </div>
            <div>
                <label>Start</label>
                <input type="time" name="start_time" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    value="{{ $isMyOld ? old('start_time') : \Illuminate\Support\Str::of($line->start_time)->substr(0,5) }}">
            </div>
            <div>
                <label>End</label>
                <input type="time" name="end_time" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    value="{{ $isMyOld ? old('end_time') : \Illuminate\Support\Str::of($line->end_time)->substr(0,5) }}">
            </div>
        </div>

        {{-- 下段: Effective Start / Effective End / Memo --}}
        <div class="schedule-line-grid2">
            <div>
                <label>Eff. Start</label>
                <input type="date" name="effective_start" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    value="{{ $isMyOld ? old('effective_start') : optional($line->effective_start)->toDateString() }}">
            </div>
            <div>
                <label>Eff. End</label>
                <input type="date" name="effective_end" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    value="{{ $isMyOld ? old('effective_end') : optional($line->effective_end)->toDateString() }}">
            </div>
            <div>
                <label>Memo</label>
                <textarea name="handover_memo" class="form-control form-control-sm" form="line-form-{{ $line->id }}"
                    rows="1" placeholder="Memo">{{ $isMyOld ? old('handover_memo') : $line->handover_memo }}</textarea>
            </div>
        </div>

        {{-- アクションボタン --}}
        <div class="schedule-line-actions">
            <button type="submit" class="btn btn-sm btn-success" form="line-form-{{ $line->id }}">保存</button>

            <button
                type="button"
                class="btn btn-sm btn-outline-danger js-delete-line"
                data-delete-url="{{ route('schedule_lines.destroy', $line) }}"
                data-line-id="{{ $line->id }}">
                削除
            </button>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary js-copy-line"
                data-copy-url="{{ route('schedule_lines.copy', $line) }}"
                data-line-id="{{ $line->id }}"
                data-current-user="{{ $line->user_id ?? '' }}"
                data-effective-end="{{ optional($line->effective_end)?->toDateString() ?? '' }}"
                data-handover-memo="{{ $line->handover_memo }}">
                複写
            </button>

            <a href="{{ route('schedule_details.edit', $line) }}"
                class="btn btn-sm btn-outline-info"
                target="_blank"
                rel="noopener noreferrer">
                詳細
            </a>
        </div>
    </div>

    {{-- 右側：閲覧専用 Schedule Details --}}
    <div class="schedule-line-right">
        @include('schedule.detailsView', ['line' => $line, 'seriesByLine' => $seriesByLine])
    </div>

</div>
@endforeach

<div class="d-flex justify-content-center mt-3">
    {{ $lines->appends(request()->query())->links() }}
</div>

@endif

{{-- 複写入力モーダル --}}
<div class="modal fade" id="copyLineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Schedule Line を複写</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small">コピー先 User</label>
                    <select id="copy-user-id" class="form-select form-select-sm">
                        <option value="">（None / NULL）</option>
                        @foreach($userOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">別のユーザーへ複写する場合はここで選択。</div>
                </div>

                <div class="mb-2">
                    <label class="form-label small">Effective Start</label>
                    <input type="date" class="form-control form-control-sm" id="copy-start">
                </div>
                <div>
                    <label class="form-label small">Effective End</label>
                    <input type="date" class="form-control form-control-sm" id="copy-end">
                </div>
                <div class="form-text mt-2">
                    複写後、元行は <strong>開始日の前日</strong> で自動クローズされます。
                </div>
                <div class="mt-2">
                    <label class="form-label small">メモ（任意・理由など）</label>
                    <textarea id="copy-memo" class="form-control form-control-sm" rows="2"
                        placeholder="例）元担当"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-sm btn-primary" id="copy-submit">複写する</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="application/json" id="user-options-data">{!! json_encode($userOptions) !!}</script>
<script>
    (function() {
        const opts = JSON.parse(document.getElementById('user-options-data').textContent);
        document.querySelectorAll('select.js-user-select').forEach(function(sel) {
            const selectedId = String(sel.dataset.selected ?? '');
            sel.appendChild(new Option('— None —', ''));
            opts.forEach(function(opt) {
                const o = new Option(opt.label, opt.id);
                if (String(opt.id) === selectedId) o.selected = true;
                sel.appendChild(o);
            });
        });
    })();
</script>
<script>
    (function() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            document.querySelector('input[name="_token"]')?.value;

        function showFlash(message, type = 'success') {
            const area = document.getElementById('flash-area');
            if (!area) return;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
            area.prepend(wrapper.firstElementChild);
        }

        async function handleDelete(btn) {
            const url = btn.getAttribute('data-delete-url');
            const lineId = btn.getAttribute('data-line-id');
            if (!url) return;
            const ok = window.confirm(`Line #${lineId} を削除します。よろしいですか？`);
            if (!ok) return;
            btn.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Unexpected response' }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '削除に失敗しました。');
                // block 対応: 該当 .schedule-line-block と hidden form を削除する
                btn.closest('.schedule-line-block')?.remove();
                document.getElementById(`line-form-${lineId}`)?.remove();
                showFlash(data.message || `Line #${lineId} を削除しました。`, 'success');
            } catch (err) {
                console.error(err);
                showFlash(err.message || '削除に失敗しました。', 'danger');
                btn.disabled = false;
            }
        }

        document.addEventListener('click', function(e) {
            const t = e.target;
            if (t && t.classList.contains('js-delete-line')) handleDelete(t);
        }, false);
    })();
</script>

<script>
    (function() {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        function showFlash(message, type = 'success') {
            const area = document.getElementById('flash-area');
            if (!area || !message) return;
            const wrap = document.createElement('div');
            wrap.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
            area.prepend(wrap.firstElementChild);
        }

        function persistFlash(message, type = 'success') {
            try { sessionStorage.setItem('flash', JSON.stringify({ message, type, t: Date.now() })); } catch (e) {}
        }

        function restoreFlashOnce() {
            try {
                const raw = sessionStorage.getItem('flash');
                if (!raw) return;
                const { message, type } = JSON.parse(raw);
                if (message) showFlash(message, type || 'success');
                sessionStorage.removeItem('flash');
            } catch (e) {}
        }

        restoreFlashOnce();

        let copyCtx = { url: null, lineId: null, currentUser: '' };

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-copy-line');
            if (!btn) return;

            copyCtx.url = btn.getAttribute('data-copy-url');
            copyCtx.lineId = btn.getAttribute('data-line-id');
            copyCtx.currentUser = btn.getAttribute('data-current-user') || '';

            const srcEnd = btn.getAttribute('data-effective-end') || '';
            const startEl = document.getElementById('copy-start');
            const endEl = document.getElementById('copy-end');
            if (startEl) startEl.value = '';
            if (endEl) endEl.value = srcEnd;

            const memoEl = document.getElementById('copy-memo');
            if (memoEl) memoEl.value = btn.getAttribute('data-handover-memo') || '';

            const sel = document.getElementById('copy-user-id');
            if (sel) sel.value = copyCtx.currentUser;

            const modalEl = document.getElementById('copyLineModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        document.getElementById('copy-submit')?.addEventListener('click', async () => {
            const start = document.getElementById('copy-start')?.value;
            const end = document.getElementById('copy-end')?.value;
            const userId = document.getElementById('copy-user-id')?.value || null;
            const memoInput = document.getElementById('copy-memo');
            const memo = memoInput?.value?.trim() || null;

            if (!start || !end) { showFlash('開始日と終了日を入力してください。', 'danger'); return; }
            if (start > end) { showFlash('終了日は開始日以降にしてください。', 'danger'); return; }
            if (!copyCtx.url) return;

            try {
                const res = await fetch(copyCtx.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        effective_start: start,
                        effective_end: end,
                        user_id: userId,
                        handover_memo: memo,
                    }),
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Unexpected response' }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '複写に失敗しました。');
                persistFlash(data.message || '複写が完了しました。', 'success');
                window.location.href = new URL(window.location.href).toString();
            } catch (err) {
                console.error(err);
                showFlash(err.message || '複写に失敗しました。', 'danger');
            } finally {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('copyLineModal')).hide();
            }
        });
    })();
</script>
<script>
    document.getElementById('add-line-btn')?.addEventListener('click', async () => {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        const sel = document.querySelector('select[name="user_id"]');
        const userId = sel?.value || null;

        try {
            const res = await fetch('/schedule_lines', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ user_id: userId }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data?.message || '追加に失敗しました。');
            sessionStorage.setItem('flash', JSON.stringify({ message: data.message || '追加が完了しました。', type: 'success' }));
            window.location.href = new URL(window.location.href).toString();
        } catch (err) {
            console.error(err);
            const area = document.getElementById('flash-area');
            if (area) {
                const wrap = document.createElement('div');
                wrap.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          ${err.message || '追加に失敗しました。'}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
                area.prepend(wrap.firstElementChild);
            }
        }
    });
</script>
<script>
    (function() {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        function showFlash(message, type = 'success') {
            const area = document.getElementById('flash-area');
            if (!area || !message) return;
            const wrap = document.createElement('div');
            wrap.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
            area.prepend(wrap.firstElementChild);
        }

        function persistFlash(message, type = 'success') {
            try { sessionStorage.setItem('flash', JSON.stringify({ message, type, t: Date.now() })); } catch (e) {}
        }

        document.getElementById('bulk-save-btn')?.addEventListener('click', async () => {
            const forms = document.querySelectorAll(
                'form[action*="schedule_lines"][method="post"], form[action*="schedule_lines"][method="POST"], form[action*="schedule_lines"][method="put"], form[action*="schedule_lines"][method="PUT"]'
            );

            const items = [];
            forms.forEach((f) => {
                try {
                    const id = f.querySelector('input[name="__line_id"]')?.value || null;
                    if (!id) return;
                    // f.elements は form 属性で関連付けられたテーブル内の入力要素も含む
                    const els = f.elements;
                    const userId = els.namedItem('user_id')?.value || null;
                    const dow = els.namedItem('dow')?.value ?? '';
                    const school = els.namedItem('school_name')?.value ?? '';
                    const st = els.namedItem('start_time')?.value ?? '';
                    const et = els.namedItem('end_time')?.value ?? '';
                    const es = els.namedItem('effective_start')?.value ?? '';
                    const ee = els.namedItem('effective_end')?.value ?? '';
                    const memo = els.namedItem('handover_memo')?.value ?? '';
                    items.push({
                        id: Number(id),
                        user_id: userId === '' ? null : Number(userId),
                        dow: dow === '' ? null : Number(dow),
                        school_name: school,
                        start_time: st,
                        end_time: et,
                        effective_start: es,
                        effective_end: ee,
                        handover_memo: memo,
                    });
                } catch (e) { console.warn('collect error:', e); }
            });

            if (!items.length) { showFlash('保存対象がありません。', 'danger'); return; }

            try {
                const res = await fetch(`{{ route('schedule_lines.bulk_update') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ items }),
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Unexpected response' }));
                if (res.ok && (data.ok || data.updated > 0)) {
                    if (data.errors && data.errors.length) {
                        const list = data.errors.map(e => `#${e.id ?? '-'}: ${e.messages.join(' / ')}`).join('<br>');
                        showFlash(`${data.message}<br>${list}`, 'warning');
                    } else {
                        persistFlash(data.message || '一括保存が完了しました。', 'success');
                        window.location.href = new URL(window.location.href).toString();
                        return;
                    }
                } else {
                    throw new Error(data?.message || '一括保存に失敗しました。');
                }
            } catch (err) {
                console.error(err);
                showFlash(err.message || '一括保存に失敗しました。', 'danger');
            }
        });
    })();
</script>

@endpush
@endsection
