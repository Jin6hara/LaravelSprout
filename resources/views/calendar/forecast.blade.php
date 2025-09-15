{{-- resources/views/calendar/forecast.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Forecast Calendar</h2>
    <span class="badge text-bg-info">Admin View</span>
</div>

<div id="calendar"></div>

<!-- 詳細モーダル（indexと同じ） -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">読み込み中…</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css" rel="stylesheet">
<link href="{{ asset('css/forecast-custom.css') }}" rel="stylesheet">
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
  <script>
    window.calendarEventsUrl = "{{ route('calendar.forecast.events') }}";
  </script>
  <script src="{{ asset('js/forecast.js') }}?v={{ filemtime(public_path('js/forecast.js')) }}"></script>
@endpush