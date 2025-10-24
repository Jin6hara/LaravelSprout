{{-- resources/views/schedule/lineEdit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Schedule Lines</h2>
    </div>
</div>

{{-- 検索フォーム（基準日/スケジュール） --}}
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
                <label class="form-label small mb-1">Schedule</label>
                <select name="schedule_id" class="form-select form-select-sm">
                    <option value="">(All)</option>
                    <option value="null" @selected($scheduleId==='null' )>Not Assigned</option>
                    @foreach($scheduleOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected($scheduleId==$opt['id'])>
                        {{ $opt['label'] ?? ('Schedule #' . $opt['id']) }}
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
    {{-- ★ 新規追加ボタン --}}
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
{{-- ★ 1カード/行に固定 --}}
<div class="row g-2">
    @foreach($lines as $line)
    <div class="col-12">
        <div class="card h-100">

            {{-- ヘッダー --}}
            <div class="card-header py-1">
                @php
                // 担当ユーザー（active_on が無ければ today 基準）
                $chips = collect($usersBySchedule[$line->schedule_id] ?? []);
                $baseLabel = $activeOn ?: 'today';

                // 検索期間（active_until が無ければ active_on と同じ日）
                $searchStart = $activeOn ?: now()->toDateString();
                $searchEnd = request('active_until') ?: $searchStart;

                // Line の有効期間
                $lineStart = optional($line->effective_start)->toDateString();
                $lineEnd = optional($line->effective_end)->toDateString(); // NULL想定なら '—' にする
                @endphp

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold">#{{ $line->id }}</span>

                    {{-- 担当ユーザー（横並び・コンパクト） --}}
                    <div class="mt-1">

                        @if($chips->isNotEmpty())
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($chips as $u)
                            <span class="badge rounded-pill text-bg-secondary">
                                {{ $u->family_name }}@if(!empty($u->first_name)) {{ ' ' . $u->first_name }} @endif
                                @if(!empty($u->employee_code)) [{{ $u->employee_code }}] @endif
                            </span>
                            @endforeach
                        </div>
                        @else
                        <div class="text-muted small">—</div>
                        @endif
                    </div>

                    <span class="badge text-bg-light text-truncate" style="max-width: 240px;">
                        {{ $line->schedule->label ?? 'Not Assigned' }}
                    </span>

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

                    {{-- 期間（Lineの有効期間） --}}
                    <span class="ms-auto small text-muted">
                        期間: {{ $lineStart }} 〜 {{ $lineEnd ?: '—' }}
                    </span>
                </div>
            </div>
            {{-- ヘッダー --}}

            <form method="POST" action="{{ route('schedule_lines.update', $line) }}">
                @csrf
                @method('PUT')

                {{-- 自分のフォーム識別用（oldスコープ） --}}
                <input type="hidden" name="__line_id" value="{{ $line->id }}">

                @php $isMyOld = old('__line_id') == $line->id; @endphp

                <div class="card-body py-1 border-bottom">
                    <div class="row g-1 align-items-end">

                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label small mb-1">Schedule (Owner)</label>
                            <select name="schedule_id" class="form-select form-select-sm">
                                <option value="">— Select —</option>
                                @foreach($scheduleOptions as $opt)
                                <option value="{{ $opt['id'] }}" @selected($line->schedule_id === $opt['id'])>
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

                            {{-- ★  削除（AJAX） --}}
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger mt-3 mt-lg-0 js-delete-line"
                                data-delete-url="{{ route('schedule_lines.destroy', $line) }}"
                                data-line-id="{{ $line->id }}">
                                削除
                            </button>

                            {{-- ★ 複写ボタン（AJAX） --}}
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary mt-3 mt-lg-0 js-copy-line"
                                data-copy-url="{{ route('schedule_lines.copy', $line) }}"
                                data-line-id="{{ $line->id }}"
                                data-current-schedule="{{ $line->schedule_id ?? '' }}"
                                data-effective-end="{{ optional($line->effective_end)?->toDateString() ?? '' }}"
                                data-handover-memo="{{ $line->handover_memo }}" {{-- ★ Memo --}}>
                                複写
                            </button>

                            <a href="{{ route('schedule_details.edit', $line) }}"
                                class="btn btn-sm btn-outline-info mt-3 mt-lg-0">
                                詳細
                            </a>
                            
                        </div>
                    </div>
                </div>
            </form>

            {{-- ▼▼▼ 閲覧専用：Schedule Details（高密度） ▼▼▼ --}}
            @include('schedule.detailsView', ['line' => $line, 'seriesByLine' => $seriesByLine])
            {{-- ▲▲▲ 詳細ここまで ▲▲▲ --}}

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
                    <label class="form-label small">コピー先 Schedule</label>
                    <select id="copy-schedule-id" class="form-select form-select-sm">
                        <option value="">（Not Assigned / NULL）</option>
                        @foreach($scheduleOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['label'] ?? ('Schedule #'.$opt['id']) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">別のスケジュール所有者へ複写する場合はここで選択。</div>
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
        // CSRF トークンを取得
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            document.querySelector('input[name="_token"]')?.value;

        function showFlash(message, type = 'success') {
            const area = document.getElementById('flash-area');
            if (!area) return;

            // Bootstrap alert を生成（success / danger）
            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
            // 先頭に差し込む
            area.prepend(wrapper.firstElementChild);
        }

        async function handleDelete(btn) {
            const url = btn.getAttribute('data-delete-url');
            const lineId = btn.getAttribute('data-line-id');

            if (!url) return;

            // 確認ダイアログ
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

                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));

                if (!res.ok || !data.ok) {
                    throw new Error(data?.message || '削除に失敗しました。');
                }

                // カードDOMを削除
                // ボタン-> col -> row -> card -> col-12 を辿る（構造に応じて調整）
                let card = btn.closest('.card');
                let col = card?.closest('.col-12') || card?.parentElement;
                (col || card)?.remove();

                // “フラッシュ風”に表示
                showFlash(data.message || `Line #${lineId} を削除しました。`, 'success');

            } catch (err) {
                console.error(err);
                showFlash(err.message || '削除に失敗しました。', 'danger');
                btn.disabled = false;
            }
        }

        // クリック委譲
        document.addEventListener('click', function(e) {
            const t = e.target;
            if (t && t.classList.contains('js-delete-line')) {
                handleDelete(t);
            }
        }, false);
    })();
</script>

<script>
    /**
     * Schedule Line 複写 + リロード越しフラッシュ（対象A）
     * 依存: Bootstrap JS（Modal, Alert）
     * 前提: <div id="flash-area"> がページ内に1ヶ所あること
     */
    (function() {
        // ========= 共通ユーティリティ =========
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
            try {
                sessionStorage.setItem('flash', JSON.stringify({
                    message,
                    type,
                    t: Date.now()
                }));
            } catch (e) {}
        }

        function restoreFlashOnce() {
            try {
                const raw = sessionStorage.getItem('flash');
                if (!raw) return;
                const {
                    message,
                    type
                } = JSON.parse(raw);
                if (message) showFlash(message, type || 'success');
                sessionStorage.removeItem('flash');
            } catch (e) {}
        }

        // ========= ページロード時：保存済みフラッシュを表示 =========
        restoreFlashOnce();

        // ========= 複写モーダルコンテキスト =========
        let copyCtx = {
            url: null,
            lineId: null,
            currentSchedule: ''
        };

        // 複写ボタン → モーダル起動＆初期値セット
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-copy-line');
            if (!btn) return;

            copyCtx.url = btn.getAttribute('data-copy-url');
            copyCtx.lineId = btn.getAttribute('data-line-id');
            copyCtx.currentSchedule = btn.getAttribute('data-current-schedule') || '';

            // 開始は空白、終了は元行の effective_end
            const srcEnd = btn.getAttribute('data-effective-end') || '';
            const startEl = document.getElementById('copy-start');
            const endEl = document.getElementById('copy-end');
            if (startEl) startEl.value = '';
            if (endEl) endEl.value = srcEnd;

            // ★ メモ初期値（ボタンの data-handover-memo から）
            const memoEl = document.getElementById('copy-memo');
            if (memoEl) memoEl.value = btn.getAttribute('data-handover-memo') || '';

            // schedule 初期選択（元の schedule_id）
            const sel = document.getElementById('copy-schedule-id');
            if (sel) sel.value = copyCtx.currentSchedule;

            const modalEl = document.getElementById('copyLineModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        // 「複写する」
        document.getElementById('copy-submit')?.addEventListener('click', async () => {
            const start = document.getElementById('copy-start')?.value;
            const end = document.getElementById('copy-end')?.value;
            const scheduleId = document.getElementById('copy-schedule-id')?.value || null;

            // ★ 空なら null
            const memoInput = document.getElementById('copy-memo');
            const memoValue = memoInput?.value?.trim() || '';
            const memo = memoValue === '' ? null : memoValue;

            if (!start || !end) {
                showFlash('開始日と終了日を入力してください。', 'danger');
                return;
            }
            if (start > end) {
                showFlash('終了日は開始日以降にしてください。', 'danger');
                return;
            }
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
                        schedule_id: scheduleId,
                        handover_memo: memo, // ← これが実際に null または文字列で送信されます
                    }),
                });

                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '複写に失敗しました。');

                // 既存のフラッシュ保存＆クエリ維持リロード（そのまま）
                persistFlash(data.message || '複写が完了しました。', 'success');
                const currentUrl = new URL(window.location.href);
                window.location.href = currentUrl.toString();

            } catch (err) {
                console.error(err);
                showFlash(err.message || '複写に失敗しました。', 'danger');
            } finally {
                modal.hide();
            }
        });
    })();
</script>
<script>
    // ========= 空白Line追加 =========
    document.getElementById('add-line-btn')?.addEventListener('click', async () => {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        const sel = document.querySelector('select[name="schedule_id"]');
        const scheduleId = sel?.value || null;

        try {
            const res = await fetch('/schedule_lines', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    schedule_id: scheduleId
                }),
            });
            const data = await res.json();

            if (!res.ok || !data.ok) throw new Error(data?.message || '追加に失敗しました。');

            // フラッシュ保存 + 再読み込み（既存と同様）
            sessionStorage.setItem('flash', JSON.stringify({
                message: data.message || '追加が完了しました。',
                type: 'success',
            }));

            const currentUrl = new URL(window.location.href);
            window.location.href = currentUrl.toString(); // クエリ維持リロード

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
            try {
                sessionStorage.setItem('flash', JSON.stringify({
                    message,
                    type,
                    t: Date.now()
                }));
            } catch (e) {}
        }

        // ★ 一括保存
        document.getElementById('bulk-save-btn')?.addEventListener('click', async () => {
            // 収集: 表示されている各行フォームから値を集める
            const forms = document.querySelectorAll(
                'form[action*="schedule_lines"][method="post"], form[action*="schedule_lines"][method="POST"], form[action*="schedule_lines"][method="put"], form[action*="schedule_lines"][method="PUT"]'
            );

            const items = [];
            forms.forEach((f) => {
                try {
                    const id = f.querySelector('input[name="__line_id"]')?.value || null; // 自分のフォーム識別
                    if (!id) return;

                    const scheduleId = f.querySelector('select[name="schedule_id"]')?.value || null;
                    const dow = f.querySelector('select[name="dow"]')?.value ?? '';
                    const school = f.querySelector('input[name="school_name"]')?.value ?? '';
                    const st = f.querySelector('input[name="start_time"]')?.value ?? '';
                    const et = f.querySelector('input[name="end_time"]')?.value ?? '';
                    const es = f.querySelector('input[name="effective_start"]')?.value ?? '';
                    const ee = f.querySelector('input[name="effective_end"]')?.value ?? '';
                    const memo = f.querySelector('textarea[name="handover_memo"]')?.value ?? ''; // ★ 追加：メモ

                    items.push({
                        id: Number(id),
                        schedule_id: scheduleId === '' ? null : scheduleId,
                        dow: dow === '' ? null : Number(dow),
                        school_name: school,
                        start_time: st, // 'HH:MM'
                        end_time: et, // 'HH:MM'
                        effective_start: es,
                        effective_end: ee,
                        handover_memo: memo, // ★ 追加：メモ
                    });
                } catch (e) {
                    console.warn('collect error:', e);
                }
            });

            if (!items.length) {
                showFlash('保存対象がありません。', 'danger');
                return;
            }

            try {
                const res = await fetch(`{{ route('schedule_lines.bulk_update') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        items
                    }),
                });
                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));

                if (res.ok && (data.ok || data.updated > 0)) {
                    if (data.errors && data.errors.length) {
                        const list = data.errors.map(e => `#${e.id ?? '-'}: ${e.messages.join(' / ')}`).join('<br>');
                        showFlash(`${data.message}<br>${list}`, 'warning');
                    } else {
                        persistFlash(data.message || '一括保存が完了しました。', 'success');
                        const currentUrl = new URL(window.location.href); // クエリ維持
                        window.location.href = currentUrl.toString();
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