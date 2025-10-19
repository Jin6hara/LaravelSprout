@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Absence Calendar</h2>
    <span class="badge text-bg-info">Admin View</span>
</div>

{{-- ▼▼ 月選択フォーム（GET） ▼▼ --}}
<form method="GET" action="{{ url()->current() }}" class="mb-1">
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
  </div>
</form>
{{-- ▲▲ 月選択フォーム（GET） ▲▲ --}}

<div id="calendar"></div>

<!-- 詳細モーダル（indexと同じ） -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">Reading…</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css" rel="stylesheet">
<link href="{{ asset('css/leave-custom.css') }}" rel="stylesheet">
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
  <script>
    window.calendarEventsUrl = "{{ route('calendar.leave.events') }}";
    window.initialDate = "{{ request('month', now()->format('Y-m')) }}";
  </script>
  <script src="{{ asset('js/leave.js') }}?v={{ filemtime(public_path('js/leave.js')) }}"></script>
@endpush