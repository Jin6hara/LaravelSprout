@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">カレンダー</h2>
  {{-- 役割ヒント --}}
  @role('admin|super_admin')
    <span class="badge text-bg-primary">管理者ビュー：{{ $viewUser->name ?? ('ID:'.$viewUser->id) }} のカレンダー</span>
  @else
    <span class="badge text-bg-secondary">講師ビュー：自分のシフト</span>
  @endrole
</div>

<div id="calendar"></div>

<!-- 詳細モーダル -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">詳細</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="eventModalBody">
        読み込み中…
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css" rel="stylesheet">
<style>
  /* 今日の日付背景 */
  .fc-day-today {
    background-color: rgba(0, 149, 248, 0.39) !important;
    border: 2px solid rgba(233, 233, 233, 1) !important; }
  /* 祝日背景 */
  .fc-holiday { 
    background-color: rgba(141, 38, 38, 0.54) !important;
    border: 1px solid rgba(255, 255, 255, 1) !important; }
  /* ユーザー休日: 法定休（背景青・区別しやすい色） */
  .fc-off-statutory { 
    background-color: rgba(51, 84, 138, 0.47) !important;
    border: 1px solid rgba(255, 255, 255, 1) !important; }
  /* ユーザー休日: 所定休（背景薄青・区別しやすい色） */
  .fc-off-prescribed { 
    background-color: rgba(48, 107, 134, 0.51) !important;
    border: 1px solid rgba(255, 255, 255, 1) !important; }
  /* 会社休業日 */
  .fc-company-break {
    background-color: rgba(32, 124, 37, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 1) !important;
    font-weight: 600;
  }
   /* RWD（黄色）/ ORDは所定休と同じ色=fc-off-prescribed を使用 */
  .fc-rwd { 
    background-color: rgba(255,193,7,.25) !important; 
    border: 1px solid rgba(255,193,7,.45) !important;
    font-weight: 600; 
  }
  /* Regular shift (level5) */
  .fc-regular-shift { 
    background-color: rgba(0, 134, 45, 0.18) !important; 
    border: 1px solid rgba(25,135,84,.35) !important;
  }
  .fc-regular-shift-sub {
    background-color: rgba(255,193,7,.25) !important; 
    border: 1px solid rgba(255,193,7,.45) !important;
    font-weight: 600;
  }
  .fc-overtime { 
    background-color: rgba(25,135,84,.12) !important; 
    border: 1px solid rgba(25,135,84,.35) }
  /* カレンダー高さ調整 */
  #calendar { max-width: 1100px; margin: 10 auto; }

</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'ja',
    initialView: 'dayGridMonth',
    height: 'auto',
    firstDay: 0, // 0:日曜はじまり
    eventOrder: "extendedProps.category,title,start",
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listWeek'
    },
    buttonText: { today: '今日', month: '月', week: '週', day: '日', list: 'リスト' },
    dayMaxEventRows: true,
    navLinks: true,
    nowIndicator: true,

    // ここがLaravelのJSONエンドポイント
    events: {
      url: "{{ route('calendar.events') }}",
      extraParams: { user_id: @json($viewUser->id) },
      failure: () => alert('イベント取得に失敗しました'),
      error: (xhr) => console.error('FC error:', xhr?.xhr?.responseText || xhr)
    },

    eventContent: function(arg) {
  // ここで表示内容を完全にカスタマイズできる
  // 例: タイトルのみ表示
    return { html: arg.event.title };
   },

    // イベントクリック時にモーダル表示
    eventClick(info) {
      info.jsEvent.preventDefault();
      const e = info.event;
      const p = e.extendedProps || {};
      let html = `<div class="mb-2"><strong>${e.title}</strong></div>`;
      if (p.details) {
        // 管理者: サブ必要の内訳表示
        html += '<ul class="list-group">';
        p.details.forEach(d => {
          html += `<li class="list-group-item d-flex justify-content-between">
                     <span>${d.campus} (${d.reason ?? '—'})</span>
                     <span class="badge text-bg-danger">必要 ${d.needed}</span>
                   </li>`;
        });
        html += '</ul>';
      } else {
        // 講師: シフト詳細
        html += `<div>種別：${p.type ?? '—'}</div>`;
        html += `<div>場所：${p.location ?? '—'}</div>`;
        if (p.start_time || p.end_time) {
          html += `<div>時間：${p.start_time ?? ''} 〜 ${p.end_time ?? ''}</div>`;
        }
      }
      document.getElementById('eventModalBody').innerHTML = html;
      const modal = new bootstrap.Modal(document.getElementById('eventModal'));
      modal.show();
    },
  });
  

  calendar.render();
});
</script>
@endpush
