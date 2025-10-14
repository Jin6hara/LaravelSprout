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

    $userOptions = $userOptions ?? collect();

    $statusOptions = ['pending'=>'Pending','in_process'=>'In Process','fixed'=>'Fixed','filled'=>'Filled'];
    $typeOptions   = [
    'regular_time' => 'RT',
    'none_required' => 'None Requred',
    'overtime' => 'OT',
    'schedule_change' => 'SC',
    'rostered_working_day' => 'RWD',
    'special' => 'SP'
    ];
  @endphp

  @if($events->count())
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">イベント一覧（編集）</h5>
      <small class="text-muted">{{ $events->total() }} 件</small>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
      @foreach($events as $event)
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
          <form method="POST" action="{{ route('events.update', $event) }}" class="h-100 d-flex flex-column">
            @csrf
            @method('PUT')

            <div class="card-body">

              <div class="mb-2 d-grid gap-2">
                <select name="type" class="form-select form-select-sm">
                  @foreach($typeOptions as $v => $label)
                    <option value="{{ $v }}" @selected($event->type===$v)>{{ $label }}</option>
                  @endforeach
                </select>
                <select name="status" class="form-select form-select-sm">
                  @foreach($statusOptions as $v => $label)
                    <option value="{{ $v }}" @selected($event->status===$v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mb-2">
                <label class="form-label small mb-1">Title</label>
                <input type="text" name="title" class="form-control form-control-sm" value="{{ old('title',$event->title) }}">
              </div>

              <div class="mb-2">
                <label class="form-label small mb-1">School</label>
                <input type="text" name="school_name" class="form-control form-control-sm" value="{{ old('school_name',$event->school_name) }}">
              </div>

              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label small mb-1">Date</label>
                  <input type="date" name="event_date" class="form-control form-control-sm"
                         value="{{ old('event_date', $fmtDate($event->event_date)) }}">
                </div>
                <div class="col-3">
                  <label class="form-label small mb-1">Start</label>
                  <input type="time" name="start_time" class="form-control form-control-sm js-time"
                         value="{{ old('start_time', $fmtTime($event->start_time)) }}" inputmode="numeric" step="60">
                </div>
                <div class="col-3">
                  <label class="form-label small mb-1">End</label>
                  <input type="time" name="end_time" class="form-control form-control-sm js-time"
                         value="{{ old('end_time', $fmtTime($event->end_time)) }}" inputmode="numeric" step="60">
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label small mb-1">Total (H:MM)</label>
                <input type="text" name="total_duration" class="form-control form-control-sm js-total"
                       placeholder="H:MM" value="{{ old('total_duration',$event->total_duration) }}">
              </div>

              <div class="mb-2">
                <label class="form-label small mb-1">Leave Type</label>
                <textarea name="Leave_type" class="form-control form-control-sm" rows="1">{{ old('Leave_type',$event->Leave_type) }}</textarea>
              </div>
              <div class="mb-2">
                <label class="form-label small mb-1">Lesson</label>
                <textarea name="Lesson" class="form-control form-control-sm" rows="2">{{ old('Lesson',$event->Lesson) }}</textarea>
              </div>
              <div class="mb-2">
                <label class="form-label small mb-1">Notes</label>
                <textarea name="notes" class="form-control form-control-sm" rows="2">{{ old('notes',$event->notes) }}</textarea>
              </div>

              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label small mb-1">Assigned User</label>
                  <select name="assigned_user_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($userOptions as $u)
                      <option value="{{ $u->id }}" @selected($event->assigned_user_id===$u->id)>{{ $u->name }} [{{ $u->employee_code }}]</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-12 mt-2">
                  <label class="form-label small mb-1">Original User</label>
                  <select name="original_user_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($userOptions as $u)
                      <option value="{{ $u->id }}" @selected($event->original_user_id===$u->id)>{{ $u->name }} [{{ $u->employee_code }}]</option>
                    @endforeach
                  </select>
                </div>
              </div>

            </div>

            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
              <small class="text-muted">Updated: {{ optional($event->updated_at)->format('Y-m-d H:i') }}</small>
              <button type="submit" class="btn btn-sm btn-primary">保存</button>
            </div>
          </form>
        </div>
      </div>
      @endforeach
    </div>

    <div class="mt-3">
      {{ $events->withQueryString()->links() }}
    </div>
  @else
    <div class="alert alert-light border">データがありません。</div>
  @endif
</div>
@endsection

@push('scripts')
<script>
  // Start/End から H:MM を算出（入力時に自動更新）
  document.addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-time')) return;
    const card = e.target.closest('.card');
    const start = card.querySelector('input[name="start_time"]').value;
    const end   = card.querySelector('input[name="end_time"]').value;
    const out   = card.querySelector('input[name="total_duration"]');
    if (!start || !end) return;
    const toMin = (t) => { const [h,m] = t.split(':').map(Number); return h*60 + m; };
    const pad   = (n) => n.toString().padStart(2,'0');
    let diff = toMin(end) - toMin(start);
    if (diff < 0) diff += 1440; // 日跨ぎ
    out.value = Math.floor(diff/60) + ':' + pad(diff%60);
  });
</script>
@endpush
