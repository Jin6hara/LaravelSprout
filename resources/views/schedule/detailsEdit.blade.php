@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Details: Line #{{ $line->id }}</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">戻る</a>
        <button type="button" id="details-add-blank" class="btn btn-sm btn-outline-success"
            data-create-url="{{ route('schedule_details.store_blank', $line) }}">
            空白追加
        </button>
        <button type="button" id="details-bulk-save" class="btn btn-sm btn-primary">一括保存</button>
    </div>
</div>

<div id="flash-area" class="mb-2"></div>

@if($details->isEmpty())
<div class="alert alert-secondary">このラインには明細がありません。</div>
@else
<div class="row g-2">
    @foreach($details as $d)
    @php
    // start_time を "H:i" に安全正規化
    $stRaw = optional($d->start)->start_time; // '9:00:00' / '09:00' / DateTime / null
    $st = '';
    if ($stRaw instanceof \DateTimeInterface) {
    $st = \Carbon\Carbon::instance($stRaw)->format('H:i');
    } elseif (!is_null($stRaw)) {
    $s = str_replace('：', ':', trim((string)$stRaw)); // 全角コロン対応
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
    try { $st = \Carbon\Carbon::parse($s)->format('H:i'); } catch (\Throwable $e) { $st = ''; }
    } elseif (preg_match('/^\d{3,4}$/', $s)) {
    $s = str_pad($s, 4, '0', STR_PAD_LEFT);
    $st = substr($s, 0, 2) . ':' . substr($s, 2, 2);
    } else {
    try { $st = \Carbon\Carbon::parse($s)->format('H:i'); } catch (\Throwable $e) { $st = ''; }
    }
    }

    // total_minutes（= lesson_minute）
    $mins = optional($d->lesson)->lesson_minute;
    $mins = is_numeric($mins) ? (int)$mins : null;

    // end_time 計算
    $endCalc = '';
    if ($st && $mins !== null) {
    try { $endCalc = \Carbon\Carbon::createFromFormat('H:i', $st)->addMinutes($mins)->format('H:i'); }
    catch (\Throwable $e) { $endCalc = ''; }
    }

    $effStart = optional($d->effective_start)->toDateString();
    $effEnd = optional($d->effective_end)->toDateString();
    @endphp

    <div class="col-12 col-md-12 col-lg-12">
        <div class="card js-detail-row h-100" data-id="{{ $d->id }}">
            <div class="card-body py-1">
                <div class="row g-1">

                    <div class="col-3 col-md-2 col-lg-1">
                        <label class="form-label small mb-1">Start</label>
                        <input type="time" class="form-control form-control-sm js-start-time" value="{{ $st }}">
                    </div>

                    <div class="col-3 col-md-2 col-lg-1">
                        <label class="form-label small mb-1">End</label>
                        <input type="time" class="form-control form-control-sm js-end-time" value="{{ $endCalc }}" readonly>
                    </div>

                    <div class="col-3 col-md-2 col-lg-1">
                        <label class="form-label small mb-1">Code</label>
                        <input type="text" class="form-control form-control-sm js-lesson-code"
                            value="{{ $d->lesson->lesson_code ?? '' }}">
                    </div>

                    <div class="col-3 col-md-2 col-lg-2">
                        <label class="form-label small mb-1">Name</label>
                        <input type="text" class="form-control form-control-sm js-lesson-name"
                            value="{{ $d->lesson->lesson_name ?? '' }}" readonly>
                    </div>

                    <div class="col-3 col-md-2 col-lg-2">
                        <label class="form-label small mb-1">Note</label>
                        <input type="text" class="form-control form-control-sm js-lesson-note"
                            value="{{ $d->lesson->note ?? '' }}">
                    </div>

                    <div class="col-3 col-md-2 col-lg-2">
                        <label class="form-label small mb-1">Effective</label>
                        <input type="date" class="form-control form-control-sm js-eff-start" value="{{ $effStart }}">
                    </div>

                    <div class="col-3 col-md-2 col-lg-2">
                        <label class="form-label small mb-1">Until</label>
                        <input type="date" class="form-control form-control-sm js-eff-end" value="{{ $effEnd }}">
                    </div>

                    <div class="btn-group btn-group-sm col-3 col-md-2 col-lg-1">
                        <button type="button"
                            class="btn btn-outline-secondary js-copy-detail"
                            data-copy-url="{{ route('schedule_details.copy', $d) }}">
                            複写
                        </button>
                        <button type="button"
                            class="btn btn-outline-danger js-delete-detail"
                            data-delete-url="{{ route('schedule_details.destroy', $d) }}">
                            削除
                        </button>
                    </div>

                    {{-- ★ 追加：合計分は表示しないが、End再計算のため hidden で保持 --}}
                    <input type="hidden" class="js-total-min" value="{{ $mins ?? '' }}">
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection

@push('scripts')
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

        // lesson_code 変更 → レッスン取得して name / minute / note を反映し、End再計算
        document.addEventListener('change', async (e) => {
            const codeInput = e.target.closest('.js-lesson-code');
            if (!codeInput) return;

            const card = codeInput.closest('.js-detail-row');
            const code = codeInput.value.trim();
            if (!code) return;

            try {
                const res = await fetch(`{{ route('lessons.by_code', ['code' => '___CODE___']) }}`.replace('___CODE___', encodeURIComponent(code)));
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data?.message || 'レッスン取得に失敗');

                const lesson = data.lesson;
                card.querySelector('.js-lesson-name').value = lesson.lesson_name ?? '';

                // ★ total_minutes は画面表示しないため hidden に保持
                const minEl = card.querySelector('.js-total-min');
                if (minEl) minEl.value = (lesson.lesson_minute ?? '') + '';

                // note は lessons.note（空なら取得値で初期化）
                const noteEl = card.querySelector('.js-lesson-note');
                if (noteEl && !noteEl.value) noteEl.value = lesson.note ?? '';

                recalcEndTime(card);
            } catch (err) {
                console.error(err);
                showFlash(err.message || 'レッスン取得に失敗しました。', 'danger');
            }
        });

        // start_time 変更時：End 再計算（カード基準）
        document.addEventListener('change', (e) => {
            const st = e.target.closest('.js-start-time');
            if (!st) return;
            const card = st.closest('.js-detail-row');
            recalcEndTime(card);
        });

        function recalcEndTime(card) {
            if (!card) return;
            const start = (card.querySelector('.js-start-time')?.value || '').trim();
            const mins = parseInt(card.querySelector('.js-total-min')?.value || '0', 10);
            if (!start || !mins) return;

            // start(HH:MM) + mins → HH:MM
            const [h, m] = start.split(':').map(Number);
            const d = new Date(2000, 1, 1, h || 0, m || 0);
            d.setMinutes(d.getMinutes() + mins);
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            const endEl = card.querySelector('.js-end-time');
            if (endEl) endEl.value = `${hh}:${mm}`;
        }

        // 一括保存
        document.getElementById('details-bulk-save')?.addEventListener('click', async () => {
            // カードを対象に収集
            const rows = Array.from(document.querySelectorAll('.js-detail-row[data-id]'));
            if (!rows.length) return showFlash('保存対象がありません。', 'danger');

            const items = rows.map(card => {
                const id = Number(card.dataset.id);
                const code = card.querySelector('.js-lesson-code')?.value?.trim() || '';
                const note = card.querySelector('.js-lesson-note')?.value ?? '';
                const st = (card.querySelector('.js-start-time')?.value || '').trim();
                const es = (card.querySelector('.js-eff-start')?.value || '').trim();
                const ee = (card.querySelector('.js-eff-end')?.value || '').trim();

                return {
                    id,
                    lesson_code: code,
                    note,
                    start_time: st, // 'HH:MM'
                    effective_start: es || null, // 空は null
                    effective_end: ee || null, // 空は null
                };
            });

            try {
                const res = await fetch(`{{ route('schedule_details.bulk_update', $line) }}`, {
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
                        sessionStorage.setItem('flash', JSON.stringify({
                            message: data.message || '保存しました。',
                            type: 'success'
                        }));
                        const u = new URL(window.location.href);
                        window.location.href = u.toString();
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
        
        // ★ 空白追加（単純追加 → 成功したらリロード）
        document.getElementById('details-add-blank')?.addEventListener('click', async (e) => {
            const url = e.currentTarget.getAttribute('data-create-url');
            if (!url) return;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '追加に失敗しました。');

                sessionStorage.setItem('flash', JSON.stringify({
                    message: data.message || '追加しました。',
                    type: 'success'
                }));
                const u = new URL(window.location.href);
                window.location.href = u.toString();
            } catch (err) {
                console.error(err);
                showFlash(err.message || '追加に失敗しました。', 'danger');
            }
        });

        // ★ 複写（カード内ボタン → 成功したらリロード）
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.js-copy-detail');
            if (!btn) return;
            const url = btn.getAttribute('data-copy-url');
            if (!url) return;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '複写に失敗しました。');

                sessionStorage.setItem('flash', JSON.stringify({
                    message: data.message || '複写しました。',
                    type: 'success'
                }));
                const u = new URL(window.location.href);
                window.location.href = u.toString();
            } catch (err) {
                console.error(err);
                showFlash(err.message || '複写に失敗しました。', 'danger');
            }
        });

        // ★ 削除（カード内ボタン → 成功したらリロード）
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.js-delete-detail');
            if (!btn) return;
            const url = btn.getAttribute('data-delete-url');
            if (!url) return;

            // 簡易確認（alert dialog）
            if (!window.confirm('この明細を削除します。よろしいですか？')) return;

            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));
                if (!res.ok || !data.ok) throw new Error(data?.message || '削除に失敗しました。');

                sessionStorage.setItem('flash', JSON.stringify({
                    message: data.message || '削除しました。',
                    type: 'success'
                }));
                const u = new URL(window.location.href);
                window.location.href = u.toString();
            } catch (err) {
                console.error(err);
                showFlash(err.message || '削除に失敗しました。', 'danger');
            }
        });

        // リロード越しフラッシュ復元
        (function restoreFlashOnce() {
            try {
                const raw = sessionStorage.getItem('flash');
                if (!raw) return;
                const {
                    message,
                    type
                } = JSON.parse(raw);
                showFlash(message, type || 'success');
                sessionStorage.removeItem('flash');
            } catch (e) {}
        })();
    })();
</script>
@endpush