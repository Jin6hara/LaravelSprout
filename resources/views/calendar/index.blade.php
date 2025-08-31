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
<link href="{{ asset('css/fullcalendar-custom.css') }}" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
  window.calendarEventsUrl = "{{ route('calendar.events') }}";
  window.calendarUserId = @json($viewUser->id);
</script>
<script src="{{ asset('js/fullcalendar.js') }}"></script>
@endpush