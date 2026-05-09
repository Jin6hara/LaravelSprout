{{-- resources/views/schedule/lineEdit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Schedule Lines</h2>
    </div>
</div>

{{-- 検索フォーム --}}
<form method="GET" action="{{ route('schedules.edit') }}" class="card mb-2">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
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
<div class="row g-2">
    @foreach($lines as $line)
    <div class="col-12">
        <div class="card h-100">

            {{-- ヘッダー --}}
            <div class="card-header py-1">
                @php
                $user = $line->user;
                $lineStart = optional($line->effective_start)->toDateString();
                $lineEnd = optional($line->effective_end)->toDateString();
                @endphp

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold">#{{ $line->id }}</span>

                    {{-- 担当ユーザー --}}
                    <div class="mt-1">
                        @if($user)
                        <span class="badge rounded-pill text-bg-secondary">
                            {{ $user->family_name }}@if(!empty($user->first_name)) {{ ' ' . $user->first_name }} @endif
                            @if(!empty($user->employee_code)) [{{ $user->employee_code }}] @endif
                        </span>
                        @else
                        <div class="text-muted small">—</div>
                        @endif
                    </div>

                    <span class="badge bg-secondary-subtle text-body-secondary">
                        {{ $dowOptions[$line->dow] ?? $line->dow }}
                    </span>

                    <span class="badge bg-info-subtle text-body">
                        {{ $line->school_name }}
                    </span>

                    <span class="badge bg-light text-body-secondary">
                        {{ \Illuminate\Support\Str::of($line->start_time)->substr(0,5) }}
                        –
                        {{ \Illuminate\Support\Str::of($line->end_time)->substr(0,5) }}
                    </span>

                    <span class="ms-auto small text-muted">
                        期間: {{ $lineStart }} 〜 {{ $lineEnd ?: '—' }}
                    </span>
                </div>
            </div>
            {{-- ヘッダー --}}

            <form method="POST" action="{{ route('schedule_lines.update', $line) }}">
                @csrf
                @method('PUT')

                <input type="hidden" name="__line_id" value="{{ $line->id }}">

                @php $isMyOld = old('__line_id') == $line->id; @endphp

                <div class="card-body py-1 border-bottom">
                    <div class="row g-1 align-items-end">

                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label small mb-1">User (Owner)</label>
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">— None —</option>
                                @foreach($userOptions as $opt)
                                <option value="{{ $opt['id'] }}" @selected($line->user_id == $opt['id'])>
                                    {{ $opt['label'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4 col-lg-1">
                            <label class="form-label small mb-1">DOW</label>
                            <select name="dow" class="form-select form-select-sm">
                                @foreach($dowOptions as $val => $label)
                                <option value="{{ $val }}" @selected(($isMyOld ? (int)old('dow') : $line->dow) === $val)>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4 col-lg-2">
                            <label class="form-label small mb-1">School</label>
                            <input type="text" name="school_name" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('school_name') : $line->school_name }}">
                        </div>

                        <div class="col-6 col-md-3 col-lg-1">
                            <label class="form-label small mb-1">Start</label>
                            <input type="time" name="start_time" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('start_time') : \Illuminate\Support\Str::of($line->start_time)->substr(0,5) }}">
                        </div>

                        <div class="col-6 col-md-3 col-lg-1">
                            <label class="form-label small mb-1">End</label>
                            <input type="time" name="end_time" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('end_time') : \Illuminate\Support\Str::of($line->end_time)->substr(0,5) }}">
                        </div>

                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label small mb-1">Effective Start</label>
                            <input type="date" name="effective_start" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('effective_start') : optional($line->effective_start)->toDateString() }}">
                        </div>

                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label small mb-1">Effective End</label>
                            <input type="date" name="effective_end" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('effective_end') : optional($line->effective_end)->toDateString() }}">
                        </div>

                        <div class="col-12 mt-9 col-lg-10">
                            <label class="form-label small mb-1">Memo</label>
                            <textarea name="handover_memo"
                                class="form-control form-control-sm"
                                rows="1"
                                placeholder="Memo">{{ old('handover_memo', $line->handover_memo) }}</textarea>
                        </div>

                        <div class="col-12 col-lg-2 d-flex t gap-1">
                            <button type="submit" class="btn btn-sm btn-success mt-3 mt-lg-0">保存</button>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger mt-3 mt-lg-0 js-delete-line"
                                data-delete-url="{{ route('schedule_lines.destroy', $line) }}"
                                data-line-id="{{ $line->id }}">
                                削除
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary mt-3 mt-lg-0 js-copy-line"
                                data-copy-url="{{ route('schedule_lines.copy', $line) }}"
                                data-line-id="{{ $line->id }}"
                                data-current-user="{{ $line->user_id ?? '' }}"
                                data-effective-end="{{ optional($line->effective_end)?->toDateString() ?? '' }}"
                                data-handover-memo="{{ $line->handover_memo }}">
                                複写
                            </button>

                            <a href="{{ route('schedule_details.edit', $line) }}"
                                class="btn btn-sm btn-outline-info mt-3 mt-lg-0"
                                target="_blank"
                                rel="noopener noreferrer">
                                詳細
                            </a>

                        </div>
                    </div>
                </div>
            </form>

            {{-- 閲覧専用：Schedule Details --}}
            @include('schedule.detailsView', ['line' => $line, 'seriesByLine' => $seriesByLine])

            <div class="card-footer d-flex justify-content-between align-items-center py-2">
                <small class="text-muted">更新: {{ $line->updated_at?->format('Y-m-d H:i') }}</small>
            </div>
        </div>
    </div>
    @endforeach
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
                let card = btn.closest('.card');
                let col = card?.closest('.col-12') || card?.parentElement;
                (col || card)?.remove();
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
                    const userId = f.querySelector('select[name="user_id"]')?.value || null;
                    const dow = f.querySelector('select[name="dow"]')?.value ?? '';
                    const school = f.querySelector('input[name="school_name"]')?.value ?? '';
                    const st = f.querySelector('input[name="start_time"]')?.value ?? '';
                    const et = f.querySelector('input[name="end_time"]')?.value ?? '';
                    const es = f.querySelector('input[name="effective_start"]')?.value ?? '';
                    const ee = f.querySelector('input[name="effective_end"]')?.value ?? '';
                    const memo = f.querySelector('textarea[name="handover_memo"]')?.value ?? '';
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
