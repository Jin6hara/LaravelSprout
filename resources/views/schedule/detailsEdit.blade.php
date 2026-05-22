@extends('layouts.app')

@push('styles')
<style>
.details-edit-table {
    font-size: 0.8125rem;
}

.details-edit-table th,
.details-edit-table td {
    padding: 0.25rem 0.35rem;
    white-space: nowrap;
    vertical-align: middle;
}

.details-edit-table .form-control-sm,
.details-edit-table .form-select-sm {
    min-height: 28px;
    padding-top: 0.15rem;
    padding-bottom: 0.15rem;
    font-size: 0.8125rem;
}

.details-edit-table .btn-sm {
    padding: 0.15rem 0.35rem;
    font-size: 0.75rem;
}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="card col-12 col-md-12 col-lg-12">
        <div class="card-header py-3">
            <div class="d-flex flex-wrap align-items-center gap-2">

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
                <span class="small text-muted">
                    Period: {{ $lineStart }} 〜 {{ $lineEnd ?: '—' }}
                </span>

                {{-- ✅ ボタン群を右端に配置 --}}
                <div class="col-12 col-md-3 col-lg-2 d-flex align-items-end justify-content-end ms-auto">
                    {{-- <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">戻る</a> --}}
                    <div class="btn-group btn-group-sm w-100">
                        <button type="button"
                            id="details-add-blank"
                            class="btn btn-outline-success"
                            data-create-url="{{ route('schedule_details.store', $line) }}">
                            Add Blank
                        </button>
                        <button type="button"
                            id="details-bulk-save"
                            class="btn btn-primary">
                            Bulk Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($details->isEmpty())
<div class="alert alert-secondary">このラインには明細がありません。</div>
@else
<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle details-edit-table">
        <thead class="table-light">
            <tr>
                <th>Start</th>
                <th>End</th>
                <th>Code</th>
                <th>Note</th>
                <th>Memo</th>
                <th>Effective</th>
                <th>Until</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $d)
            @php
            // 開始・終了の算出（既に導入済みの TimeString 利用）
            $st = \App\Support\TimeString::normalizeToHm($d->start_time);
            $minsVal = optional($d->lesson)->lesson_minute;
            $mins = is_numeric($minsVal) ? (int)$minsVal : null;
            $endCalc = ($st && $mins !== null) ? \App\Support\TimeString::addMinutesHm($st, $mins) : '';

            // 期間（無ければ空文字）
            $effStart = optional($d->effective_start)->toDateString() ?? '';
            $effEnd = optional($d->effective_end)->toDateString() ?? '';
            @endphp

            <tr class="js-detail-row" data-id="{{ $d->id }}">
                <td>
                    <input type="time" class="form-control form-control-sm js-start-time" value="{{ $st }}">
                </td>
                <td>
                    <input type="time" class="form-control form-control-sm js-end-time" value="{{ $endCalc }}" readonly>
                </td>
                <td>
                    <input
                        type="text"
                        list="lesson-datalist"
                        class="form-control form-control-sm js-lesson-code"
                        style="min-width: 12rem;"
                        value="{{ $d->lesson->lesson_code ?? '' }}"
                        placeholder="lesson code"
                        autocomplete="off"
                    >
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm js-lesson-note"
                        value="{{ $d->lesson->note ?? '' }}" style="min-width: 8rem;">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm js-detail-note"
                        value="{{ $d->note ?? '' }}" style="min-width: 8rem;">
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm js-eff-start" value="{{ $effStart }}">
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm js-eff-end" value="{{ $effEnd }}">
                </td>
                <td>
                    <div class="d-flex gap-1 flex-nowrap">
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary js-copy-detail"
                            data-copy-url="{{ route('schedule_details.copy', $d) }}">
                            Copy
                        </button>
                        <button type="button"
                            class="btn btn-sm btn-outline-danger js-delete-detail"
                            data-delete-url="{{ route('schedule_details.destroy', $d) }}">
                            Delete
                        </button>
                    </div>
                    {{-- ★ 追加：合計分は表示しないが、End再計算のため hidden で保持 --}}
                    <input type="hidden" class="js-total-min" value="{{ $mins ?? '' }}">
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- lesson 補完用 datalist（全行で共有） --}}
<datalist id="lesson-datalist">
    @foreach($lessonOptions as $opt)
    <option value="{{ $opt->lesson_code }}">[{{ $opt->lesson_code }}] {{ $opt->ps_unique_lesson_code }}</option>
    @endforeach
</datalist>
@endif
@endsection

@push('scripts')
<script>
    (function() {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        // lesson_code 変更 → レッスン取得して name / minute / note を反映し、End再計算
        document.addEventListener('change', async (e) => {
            const codeInput = e.target.closest('.js-lesson-code');
            if (!codeInput) return;

            const card = codeInput.closest('.js-detail-row');
            const code = codeInput.value.trim();
            if (!code) return;

            try {
                const res = await fetch(
                    `{{ route('lessons.by_code', ['code' => '___CODE___']) }}`.replace('___CODE___', encodeURIComponent(code)),
                    { headers: { 'Accept': 'application/json' } }
                );
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data?.message || 'レッスン取得に失敗');

                const lesson = data.lesson;

                // ★ ここを安全にする
                const nameEl = card.querySelector('.js-lesson-name');
                if (nameEl) {
                    nameEl.value = lesson.lesson_name ?? '';
                }

                // ★ total_minutes は画面表示しないため hidden に保持
                const minEl = card.querySelector('.js-total-min');
                if (minEl) minEl.value = (lesson.lesson_minute ?? '') + '';

                // note は lessons.note（空なら取得値で初期化）
                const noteEl = card.querySelector('.js-lesson-note');
                if (noteEl && !noteEl.value) noteEl.value = lesson.note ?? '';

                recalcEndTime(card);
            } catch (err) {
                console.error(err);
                window.showToast(err.message || 'レッスン取得に失敗しました。', { variant: 'danger' });
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
            if (!rows.length) return window.showToast('保存対象がありません。', { variant: 'danger' });

            const items = rows.map(card => {
                const id = Number(card.dataset.id);
                const code = card.querySelector('.js-lesson-code')?.value?.trim() || '';
                const note = card.querySelector('.js-lesson-note')?.value ?? '';
                const detailNote = card.querySelector('.js-detail-note')?.value ?? null;
                const st = (card.querySelector('.js-start-time')?.value || '').trim();
                const es = (card.querySelector('.js-eff-start')?.value || '').trim();
                const ee = (card.querySelector('.js-eff-end')?.value || '').trim();

                return {
                    id,
                    lesson_code: code,
                    note,
                    detail_note: detailNote,
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
                        window.showToast(`${data.message} / ${list}`, { variant: 'warning' });
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
                window.showToast(err.message || '一括保存に失敗しました。', { variant: 'danger' });
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
                window.showToast(err.message || '追加に失敗しました。', { variant: 'danger' });
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
                window.showToast(err.message || '複写に失敗しました。', { variant: 'danger' });
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
                window.showToast(err.message || '削除に失敗しました。', { variant: 'danger' });
            }
        });

        // リロード越しフラッシュ復元
        document.addEventListener('DOMContentLoaded', function restoreFlashOnce() {
            try {
                const raw = sessionStorage.getItem('flash');
                if (!raw) return;
                const { message, type } = JSON.parse(raw);
                if (message) window.showToast(message, { variant: type || 'success' });
                sessionStorage.removeItem('flash');
            } catch (e) {}
        });
    })();
</script>
@endpush
