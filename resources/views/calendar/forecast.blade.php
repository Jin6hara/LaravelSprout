@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Forecast Calendar</h2>
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
                <h5 class="modal-title mb-0">Details</h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <!-- 編集リンク（JSでhrefをセット。event_idが無ければ非表示のまま） -->
                    <a id="eventEditLink" href="#"
                      class="btn btn-sm btn-outline-secondary"
                      style="display:none; color:#6c757d;"
                      target="_blank" rel="noopener">Edit</a>
                    <!-- 対応するLeave編集へのショートカット（JSでhrefをセット） -->
                    <a id="leaveEditLink" href="#"
                      class="btn btn-sm btn-outline-secondary"
                      style="display:none; color:#6c757d;"
                      rel="noopener">Absence</a> {{--target="_blank" (タブの管理大変の為、削除)--}}
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" id="eventModalBody">Reading…</div>
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
    window.initialDate = "{{ request('month', now()->format('Y-m')) }}";
  </script>
  <script src="{{ asset('js/forecast.js') }}?v={{ filemtime(public_path('js/forecast.js')) }}"></script>
@endpush