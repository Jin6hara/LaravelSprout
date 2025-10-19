@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Schedule</h2>
  {{-- 役割ヒント --}}
  @role('admin|super_admin')
  <span class="badge text-bg-primary">Admin View: {{ $viewUser->name ?? ('ID:'.$viewUser->id) }}</span>
  @else
  <span class="badge text-bg-secondary">Schedule</span>
  @endrole
</div>

{{-- ▼▼ 月選択フォーム（GET） ▼▼ --}}
<form method="GET" action="{{ url()->current() }}" class="mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3 col-lg-2">
      <label class="form-label small mb-1">Month</label>
      <input type="month"
             name="month"
             class="form-control form-control-sm"
             value="{{ old('month', request('month', now()->format('Y-m'))) }}"
             required>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-primary">Select</button>
    </div>

    {{-- ▼ 管理者だけ：対象ユーザーの選択 --}}
    @role('admin|super_admin')
    <div class="col-6 col-md-3 col-lg-2 ">
      <label class="form-label small mb-1 ">Teacher</label>
      <select name="user" class="form-select form-select-sm"
              onchange="this.form.action='/calendar/'+this.value; this.form.submit();">
        <option value="">（選択）</option>
        @foreach($userOptions as $u)
          <option value="{{ $u->employee_code }}"
            @selected((string)($viewUser->employee_code) === (string)$u->employee_code)>
            {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
          </option>
        @endforeach
      </select>
    </div>
    @endrole
    {{-- ▲ 管理者だけ：対象ユーザーの選択 --}}

  </div>
</form>
{{-- ▲▲ 月選択フォーム（GET） ▲▲ --}}

<div id="calendar"></div>

<!-- 詳細モーダル -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="eventModalBody">
        Reading…
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
  window.calendarUserId    = @json($viewUser->id);
  window.initialDate       = "{{ request('month', now()->format('Y-m')) }}";
</script>
<script src="{{ asset('js/fullcalendar.js') }}"></script>
@endpush