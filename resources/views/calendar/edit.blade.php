{{-- resources/views/calendar/edit.blade.php --}}

@extends('layouts.app')
@section('title', 'イベント一覧（編集）')

@section('content')
<div class="container py-3">

  @php
  // ★ 追加：安全フォーマッタ
  $fmtDate = function ($v) {
  if (!$v) return '';
  try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return ''; }
  };
  $fmtTime = function ($v) {
  if (empty($v)) return '';
  // cast が 'datetime:H:i' の場合: string "HH:MM" が来る想定
  if ($v instanceof \DateTimeInterface) return $v->format('H:i');
  $s = (string) $v;
  // "HH:MM[:SS]" から先頭の "HH:MM" だけ抽出（"2025-" のような文字列は弾く）
  if (preg_match('/^\s*(\d{2}:\d{2})/', $s, $m)) return $m[1];
  // DBの生値（time型なら "HH:MM:SS"）を使いたい場合のフォールバック
  return '';
  };
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Shift Assigner</h5>
    <small class="text-muted">{{ $events->total() }} 件</small>
  </div>

  {{-- ▼ 欠席作成 -------------------------------------------------------------------------------------------}}
  <form method="POST" action="{{ route('leaves.store') }}" class="mb-2">
    @csrf
    {{-- 固定項目（hidden） --}}
    <input type="hidden" name="kind" value="absence">
    <input type="hidden" name="excused" value="unexcused">
    <input type="hidden" name="status" value="pending">

    <div class="card">
      <div class="card-body p-2">
        <div class="row g-2 align-items-end">
          {{-- Original User --}}
          <div class="col-12 col-md-3 col-lg-3">
            <label class="form-label small mb-1">Original User</label>
            <select name="user_id" class="form-select form-select-sm" required>
              <option value="">Please select</option>
              @foreach($userOptions as $u)
                <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>
                  {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                </option>
              @endforeach
            </select>
          </div>

          {{-- Date（= start_date・既定は本日） --}}
          <div class="col-12 col-md-3 col-lg-3">
            <label class="form-label small mb-1">Date</label>
            <input type="date"
                  name="start_date"
                  class="form-control form-control-sm"
                  value="{{ old('start_date', now()->toDateString()) }}"
                  required>
          </div>

          {{-- 送信ボタン --}}
          <div class="col-12 col-md-2 col-lg-2 d-grid">
            <button type="submit" class="btn btn-sm btn-warning">
              Create Absence
            </button>
          </div>

          {{-- 任意: 期間指定を使うなら end_date も出す（単日は不要） --}}
          {{-- 
          <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label small mb-1">End Date（任意）</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="">
          </div>
          --}}
        </div>
      </div>
    </div>
  </form>
  {{-- ▲ 欠席作成 -------------------------------------------------------------------------------------------}}

  {{-- 検索フォーム -------------------------------------------------------------------------------------------}}
  <form method="GET" action="{{ route('calendar.edit') }}" class="search mb-2">
    <div class="card">
      <div class="card-body p-2">
        <div class="row g-2 align-items-end">

          {{-- title（部分一致）--}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Title</label>
            <input type="text" name="title" class="form-control form-control-sm"
                  value="{{ request('title') }}" placeholder="">
          </div>

          {{-- Original User --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Original User</label>
            <select name="original_user_id" class="form-select form-select-sm">
              <option value="">( All )</option>
              @foreach($userOptions as $u)
                <option value="{{ $u->id }}" @selected(request('original_user_id') == $u->id)>
                  {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                </option>
              @endforeach
            </select>
          </div>

          {{-- Leave type（部分一致） --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Leave type</label>
            <input type="text" name="Leave_type" class="form-control form-control-sm"
                  value="{{ request('Leave_type') }}" placeholder="">
          </div>

          {{-- School（部分一致） --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">School</label>
            <input type="text" name="school_name" class="form-control form-control-sm"
                  value="{{ request('school_name') }}" placeholder="">
          </div>

          {{-- Assigned User --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Assigned User</label>
            <select name="assigned_user_id" class="form-select form-select-sm">
              <option value="">( All )</option>
              @foreach($userOptions as $u)
                <option value="{{ $u->id }}" @selected(request('assigned_user_id') == $u->id)>
                  {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                </option>
              @endforeach
            </select>
          </div>

          {{-- Lesson（部分一致） --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Lesson</label>
            <input type="text" name="Lesson" class="form-control form-control-sm"
                  value="{{ request('Lesson') }}" placeholder="">
          </div>

          {{-- status --}}
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">( All )</option>
              @foreach($statusOptions as $v => $label)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          {{-- type --}}
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Type</label>
            <select name="type" class="form-select form-select-sm">
              <option value="">( All )</option>
              @foreach($typeOptions as $v => $label)
                <option value="{{ $v }}" @selected(request('type') === $v)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          {{-- Date（当日一致） --}}
          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Date / Start Date</label>
            <input type="date" name="event_date" class="form-control form-control-sm"
                  value="{{ old('event_date', request('event_date', now()->toDateString())) }}">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label small mb-1">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-sm"
                  value="{{ old('end_date', request('end_date')) }}">
          </div>

          {{-- Placeholder --}}
          <div class="col-12 col-md-3"> 
            <label class="form-label small mb-1"></label>
          </div>

          {{-- 操作ボタン --}}  
          <div class="col-12 col-md-3 d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Search</button>
            <a href="{{ route('calendar.edit') }}" class="btn btn-sm btn-outline-secondary flex-fill">Clear</a>
          </div>

        </div>
      </div>
    </div>
  </form>
  {{-- ▲ ここまで検索フォーム -----------------------------------------------------------------------------------------}}

  
  <div class="d-flex align-items-center gap-1 mb-2">
    {{-- 空白イベント追加 -------------------------------------------------------------------------------------------}}
    <form method="POST" action="{{ route('events.store.blank') }}" class="m-0 p-0">
      @csrf
      <input type="hidden" name="event_date" value="{{ request('event_date', now()->toDateString()) }}">
      <button type="submit" class="btn btn-sm btn-success">
        ＋ Add Blank
      </button>
    </form>
    <button type="button"
      class="btn btn-sm btn-primary"
      id="js-bulk-save"
      data-url="{{ route('events.bulk_update') }}">
      Bulk Save
    </button>
  </div>

  @if($events->count())
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
    @foreach($events as $event)

    @php
      $now = now();

      $diffUpdated = $event->updated_at?->diffInMinutes($now);
      $diffCreated = $event->created_at?->diffInMinutes($now);

      $isNew = $event->created_at && $event->created_at->equalTo($event->updated_at);

      if ($event->created_at && $event->created_at->equalTo($event->updated_at)) {
          $cls = 'text-success';
      } elseif ($diffUpdated <= 10) {
          $cls = 'text-danger';    // 10分以内に更新
      } elseif ($diffUpdated <= 30) {
          $cls = 'text-warning';   // 30分以内
      } elseif ($diffUpdated <= 60) {
          $cls = 'text-dark';      // 1時間以内
      } else {
          $cls = 'text-muted';     // それ以降
      }

      // 表示ラベル
      $action = $isNew ? 'Created' : 'Updated';
      $time  = optional($event->updated_at)->format('Y-m-d H:i');
    @endphp

    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
      <div class="card h-100 shadow-sm">
         <form method="POST" 
            action="{{ route('events.update', $event) }}" 
            class="h-100 d-flex flex-column js-event-form" 
            data-event-id="{{ $event->id }}">
          @csrf
          @method('PUT')

          <div class="card-body p-2 light-blue">
            <div class="mb-0 d-grid gap-0"> {{-- gapは下記四項目すべてに適用する --}}
              {{-- 0 --}}
              <div class="mb-0">
                @php
                    $titleOptions = [
                        'Support Shift',
                        'OPPT/ML',
                        '#Memo',
                        'Cover Shift',
                    ];
                @endphp
                <label class="form-label small text-muted mb-0">Title</label>
                <input
                  list="titleOptions"
                  name="title"
                  class="form-control form-control-sm"
                  value="{{ old('title', $event->title) }}"
                  placeholder=""
                />
                <datalist id="titleOptions">
                  @foreach ($titleOptions as $opt)
                    <option value="{{ $opt }}">
                  @endforeach
                </datalist>
              </div>
              {{-- 1 --}}
              <div class="col-12">
                <label class="form-label small text-muted mb-0">Original User</label>
                <select name="original_user_id" class="form-select form-select-sm">
                  <option value="">—</option>
                  @foreach($userOptions as $u)
                  <option value="{{ $u->id }}" @selected($event->original_user_id===$u->id)>{{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]</option>
                  @endforeach
                </select>
              </div>
              {{-- 2 --}}
              <div class="mb-0">
                <label class="form-label small text-muted mb-0">Leave Type</label>
                <input type="text" name="Leave_type" class="form-control form-control-sm" value="{{ old('Leave_type',$event->Leave_type) }}">
              </div>
              {{-- 3 --}}
              <div class="mb-0">
                <label class="form-label small text-muted mb-0">School</label>
                <input
                  list="schoolOptions"
                  name="school_name"
                  class="form-control form-control-sm"
                  value="{{ old('school_name', $event->school_name) }}"
                  placeholder=""
                />
                <datalist id="schoolOptions">
                  @foreach ($schoolNames as $s)
                    <option value="{{ $s }}">
                  @endforeach
                </datalist>
              </div>
            </div>
            {{-- 4 --}}
            <div class="row g-1 mb-0">
              <div class="col-8">
                <label class="form-label small text-muted mb-0">Date</label>
                <input type="date" name="event_date" class="form-control form-control-sm"
                  value="{{ old('event_date', $fmtDate($event->event_date)) }}">
              </div>
              <div class="col-4">
                <label class="form-label small text-muted mb-0">Total</label>
                <input type="text" name="total_duration" class="form-control form-control-sm js-total"
                  placeholder="H:MM" value="{{ old('total_duration',$event->total_duration) }}" readonly>
              </div>
            </div>
            {{-- 5 --}}
            <div class="row g-1 mb-0">
              <div class="col-6">
                <label class="form-label small text-muted mb-0">Start</label>
                <input type="time" name="start_time" class="form-control form-control-sm js-time"
                  value="{{ old('start_time', $fmtTime($event->start_time)) }}" inputmode="numeric" step="60">
              </div>
              <div class="col-6">
                <label class="form-label small text-muted mb-0">End</label>
                <input type="time" name="end_time" class="form-control form-control-sm js-time"
                  value="{{ old('end_time', $fmtTime($event->end_time)) }}" inputmode="numeric" step="60">
              </div>
            </div>
            {{-- 6 --}}
            <div class="mb-0">
              <label class="form-label small text-muted mb-0">Lesson</label>
              <textarea name="Lesson" class="form-control form-control-sm" rows="1">{{ old('Lesson',$event->Lesson) }}</textarea>
            </div>
            <div class="mb-0">
              <label class="form-label small text-muted mb-0">Notes</label>
              <textarea name="notes" class="form-control form-control-sm" rows="3">{{ old('notes',$event->notes) }}</textarea>
            </div>
            {{-- 7 --}}
            <div class="row g-1 mb-1">
              <div class="col-12">
                <label class="form-label small text-muted mb-0">Assigned User</label>
                <select name="assigned_user_id" class="form-select form-select-sm">
                  <option value="">—</option>
                  @foreach($userOptions as $u)
                  <option value="{{ $u->id }}" @selected($event->assigned_user_id===$u->id)>{{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]</option>
                  @endforeach
                </select>
              </div>
            </div>
            {{-- 8 --}}
            <div class="row g-1 mb-1">
              <div class="col-6">
                <select name="type" class="form-select form-select-sm">
                  @foreach($typeOptions as $v => $label)
                  <option value="{{ $v }}" @selected($event->type===$v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-6">
                <select name="status" class="form-select form-select-sm">
                  @foreach($statusOptions as $v => $label)
                  <option value="{{ $v }}" @selected($event->status===$v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            {{-- 9 --}}
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-2 gap-1">             
              <button type="button"
                class="btn btn-sm btn-outline-secondary js-duplicate"
                data-store="{{ route('events.store') }}">
                複写
              </button>
              <button type="button"
                class="btn btn-sm btn-outline-danger js-delete"
                data-url="{{ route('events.destroy', $event) }}"
                data-date="{{ $event->event_date?->format('Y-m-d') ?? '未設定' }}">
                削除
              </button>
              <button type="submit" class="btn btn-sm btn-primary">保存</button>
            </div>
            <small class="{{ $cls }} text-left d-block mt-1">
              {{ $action }}: {{ $time }}
            </small>
          </div>
          {{-- 10 --}}
        </form>
      </div>
    </div>
    @endforeach
  </div>

  <form id="js-delete-form" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
  </form>

  <div class="mt-3">
    {{ $events->withQueryString()->links() }}
  </div>
  @else
  <div class="alert alert-light border">データがありません。</div>
  @endif
</div>
@endsection

@push('styles')
<style>
  /* ページ専用の薄青 */
  .card-body.p-2.light-blue {
    background-color: #eef6ff;
    /* ごく淡い青 */
  }

  /* 検索専用の薄青 */
  .card-body.p-2 {
    background-color: #eef6ff;
    /* ごく淡い青 */
  }  
</style>
@endpush

@push('scripts')
<script>
  // ---- helpers ----
  function hhmmToMin(t) {
    if (!t) return null;
    const parts = String(t).trim().replace('：',':').split(':');
    if (parts.length < 2) return null;
    const h = Number(parts[0]), m = Number(parts[1]);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return h*60 + m;
  }
  function pad2(n){ return String(n).padStart(2,'0'); }

  function recalcTotal(card, {force=false} = {}) {
    const start = card.querySelector('input[name="start_time"]')?.value || '';
    const end   = card.querySelector('input[name="end_time"]')?.value || '';
    const out   = card.querySelector('input[name="total_duration"]');
    if (!out) return;

    // 既に Total が入っている場合は、明示的に force のときだけ上書き
    if (!force && out.value) return;

    const s = hhmmToMin(start);
    const e = hhmmToMin(end);
    if (s == null || e == null) return;

    let diff = e - s;
    if (diff < 0) diff += 1440; // 日跨ぎ
    out.value = Math.floor(diff/60) + ':' + pad2(diff%60);
  }

  // 1) ページ読み込み時：Total 未入力なら自動計算
  function initTotals() {
    document.querySelectorAll('.card').forEach(card => recalcTotal(card));
  }
  document.addEventListener('DOMContentLoaded', initTotals);
  // Turbo を使っている場合（任意）
  document.addEventListener('turbo:load', initTotals);

  // 2) Start/End 変更時：常に再計算（上書き）
  document.addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-time')) return;
    const card = e.target.closest('.card');
    if (!card) return;
    recalcTotal(card, {force:true});
  });

  // 3) Total を空でフォーカスアウトしたとき：再計算（便利オプション）
  document.addEventListener('blur', (e) => {
    if (e.target?.name !== 'total_duration') return;
    const card = e.target.closest('.card');
    if (!card) return;
    if (!e.target.value) recalcTotal(card, {force:true});
  }, true);
</script>

<script>
// 4keta
document.addEventListener('input', (e) => {
  if (e.target.name !== 'event_date') return;
  const val = e.target.value.trim();
  // 年4桁-月2桁-日2桁 以外をリアルタイムで矯正
  if (!/^\d{0,4}(-\d{0,2}){0,2}$/.test(val)) {
    e.target.value = val.replace(/[^0-9-]/g, '').slice(0,10);
  }
});
</script>

<script>
  // 削除確認ダイアログ（日付表示）
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-delete');
    if (!btn) return;

    const date = btn.dataset.date || 'このイベント';
    if (!confirm(`${date} のイベントを削除します。よろしいですか？`)) return;

    const form = document.getElementById('js-delete-form');
    form.action = btn.dataset.url;
    form.submit();
  });
</script>

<script>
  // 複写：現在のフォーム値で /events に POST
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-duplicate');
    if (!btn) return;

    const form = btn.closest('form');
    if (!form) return;

    // PUT のメソッド偽装を外す
    const spoof = form.querySelector('input[name="_method"]');
    if (spoof) spoof.remove();

    // 送信先を store に差し替え、method を POST に
    form.action = btn.dataset.store;
    form.method = 'POST';

    // 複写フラグ（必要ならコントローラで分岐可能）
    if (!form.querySelector('input[name="_duplicate"]')) {
      const f = document.createElement('input');
      f.type = 'hidden';
      f.name = '_duplicate';
      f.value = '1';
      form.appendChild(f);
    }

    form.submit();
  });
</script>

<script>
// 一括保存
(function(){
  const bulkBtn = document.getElementById('js-bulk-save');
  if (!bulkBtn) return;

  // ルートURL
  const bulkUrl = bulkBtn.dataset.url;

  // CSRFトークン（layouts.app に <meta name="csrf-token"> がある前提）
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
           || document.querySelector('input[name="_token"]')?.value;

  function parseEventIdFromAction(action){
    // 末尾の数値をIDと解釈（/events/123）
    const m = String(action).match(/\/events\/(\d+)(?:\?.*)?$/);
    return m ? parseInt(m[1], 10) : null;
  }

  function formToObject(form){
    const fd = new FormData(form);
    const obj = {};
    for (const [k,v] of fd.entries()) {
      if (k === '_token' || k === '_method') continue; // 個別フォームの偽装系は除外
      obj[k] = v;
    }
    return obj;
  }

  bulkBtn.addEventListener('click', async () => {
    // まずはイベント編集フォーム（識別クラス）を優先的に取得。無ければ従来セレクタを使用。
    let cards = document.querySelectorAll('.js-event-form');
    if (!cards.length) {
      cards = document.querySelectorAll('.row .card form'); // 従来の対象（後方互換）
    }
    const items = [];

    cards.forEach(form => {
      // data-event-id を優先、無ければ従来の URL 解析で補完
      const dataId = form.dataset?.eventId ? parseInt(form.dataset.eventId, 10) : null;
      const urlId  = parseEventIdFromAction(form.action);
      const id = dataId || urlId;
      if (!id) return; // 新規POSTフォーム（複写直後未反映など）は対象外
      const data = formToObject(form);
      data.id = id;
      items.push(data);
    });

    if (!items.length) {
      alert('保存対象がありません。');
      return;
    }

    // 送信
    try {
      const res = await fetch(bulkUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ items }),
      });
      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        alert(json?.message || '保存に失敗しました。');
        return;
      }
      // ✅ 成功時はアラートを出さず、フラッシュ表示のために即リロード
      location.reload();
    } catch (err) {
      console.error(err);
      alert('通信に失敗しました。');
    }
  });
})();
</script>
@endpush