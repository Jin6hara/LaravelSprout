{{-- resources/views/schedule/lineEdit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Schedule Lines</h2>
        <div class="text-muted small">一行＝一カード（編集：上段 / 閲覧：下段 詳細）</div>
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
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
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
                            {{ $u->family_name }}
                            @if(!empty($u->first_name)) {{ $u->first_name }} @endif
                            [{{ $u->employee_code }}]
                        </span>
                        @endforeach
                    </div>
                    @else
                    <div class="text-muted small">—</div>
                    @endif
                </div>
            </div>
            {{-- ヘッダー --}}

            <form method="POST" action="{{ route('schedule_lines.update', $line) }}">
                @csrf
                @method('PUT')

                {{-- 自分のフォーム識別用（oldスコープ） --}}
                <input type="hidden" name="__line_id" value="{{ $line->id }}">

                @php $isMyOld = old('__line_id') == $line->id; @endphp

                <div class="card-body py-2 border-bottom">
                    <div class="row g-2 align-items-end">

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

                        <div class="col-12 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">DOW</label>
                            <select name="dow" class="form-select form-select-sm">
                                @foreach($dowOptions as $val => $label)
                                <option value="{{ $val }}" @selected(($isMyOld ? (int)old('dow') : $line->dow) === $val)>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-3 col-lg-2">
                            <label class="form-label small mb-1">School</label>
                            <input type="text" name="school_name" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('school_name') : $line->school_name }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">Start</label>
                            <input type="time" name="start_time" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('start_time') : \Illuminate\Support\Str::of($line->start_time)->substr(0,5) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-1">
                            <label class="form-label small mb-1">End</label>
                            <input type="time" name="end_time" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('end_time') : \Illuminate\Support\Str::of($line->end_time)->substr(0,5) }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-2">
                            <label class="form-label small mb-1">Effective Start</label>
                            <input type="date" name="effective_start" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('effective_start') : optional($line->effective_start)->toDateString() }}">
                        </div>

                        <div class="col-6 col-md-2 col-lg-2">
                            <label class="form-label small mb-1">Effective End</label>
                            <input type="date" name="effective_end" class="form-control form-control-sm"
                                value="{{ $isMyOld ? old('effective_end') : optional($line->effective_end)->toDateString() }}">
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
                                data-effective-end="{{ optional($line->effective_end)?->toDateString() ?? '' }}" {{-- ★ 新規追加 --}}>
                                複写
                            </button>
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

            // ✅ suggest = effective_end（複写元）を取得して終了日にセット
            const srcEnd = btn.getAttribute('data-effective-end') || ''; // ★ ボタン側で渡す
            const startEl = document.getElementById('copy-start');
            const endEl = document.getElementById('copy-end');

            if (startEl) startEl.value = ''; // 開始日は空白
            if (endEl) endEl.value = srcEnd; // 終了日は元行に合わせる

            // schedule 初期選択（元の schedule_id）
            const sel = document.getElementById('copy-schedule-id');
            if (sel) sel.value = copyCtx.currentSchedule;

            const modalEl = document.getElementById('copyLineModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        // 「複写する」
        document.getElementById('copy-submit')?.addEventListener('click', async () => {
            const startEl = document.getElementById('copy-start');
            const endEl = document.getElementById('copy-end');
            const sel = document.getElementById('copy-schedule-id');

            const start = startEl?.value;
            const end = endEl?.value;
            const scheduleId = (sel?.value || '') || null; // '' → null

            if (!start || !end) {
                showFlash('開始日と終了日を入力してください。', 'danger');
                return;
            }
            if (start > end) {
                showFlash('終了日は開始日以降にしてください。', 'danger');
                return;
            }
            if (!copyCtx.url) return;

            const modalEl = document.getElementById('copyLineModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

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
                    }),
                });

                const data = await res.json().catch(() => ({
                    ok: false,
                    message: 'Unexpected response'
                }));

                if (!res.ok || !data.ok) {
                    // 失敗をリロード越しに見せたい場合は下2行のコメントアウトを外す
                    // persistFlash(data?.message || '複写に失敗しました。', 'danger');
                    // return window.location.reload();
                    throw new Error(data?.message || '複写に失敗しました。');
                }

                // 成功：フラッシュを保存してリロード
                persistFlash(data.message || '複写が完了しました。', 'success');
                window.location.reload();

            } catch (err) {
                console.error(err);
                // 失敗は画面内に即表示（※リロードしたいなら persist + reload に切替）
                showFlash(err.message || '複写に失敗しました。', 'danger');
            } finally {
                modal.hide();
            }
        });
    })();
</script>
@endpush
@endsection