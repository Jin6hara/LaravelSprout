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
  /* 祝日背景 */
  .fc-holiday { background-color: rgba(255, 7, 7, 0.15) !important; }
  /* 管理者のサブ必要イベント */
  .fc-need { background-color: rgba(220, 53, 69, .15) !important; border: 1px solid rgba(86, 220, 53, 0.4) }
  /* 講師のシフト */
  .fc-regular { background-color: rgba(255, 255, 255, 0.12) !important; border: 1px solid rgba(141, 41, 255, 0.35) }
  .fc-overtime { background-color: rgba(25,135,84,.12) !important; border: 1px solid rgba(25,135,84,.35) }
  /* カレンダー高さ調整 */
  #calendar { max-width: 1100px; margin: 0 auto; }
  /* ユーザー休日: 法定休（背景青・区別しやすい色） */
  .fc-off-statutory { background-color: rgba(13, 110, 253, 0.18) !important; }
  /* ユーザー休日: 所定休（背景薄青・区別しやすい色） */
  .fc-off-prescribed { background-color: rgba(41, 188, 255, 0.15) !important; }
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
      failure: () => alert('イベント取得に失敗しました')
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
