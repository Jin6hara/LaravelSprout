{{-- resources/views/calendar/dailyBoard.blade.php --}}

@extends('layouts.app')
@section('title', 'Daily Shift Board')

@section('content')
@php
    $fmtDate = function ($v) {
        if (!$v) return '';
        try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return ''; }
    };
    $fmtTime = function ($v) {
        if (empty($v)) return '';
        if ($v instanceof \DateTimeInterface) return $v->format('H:i');
        $s = (string) $v;
        if (preg_match('/^\s*(\d{2}:\d{2})/', $s, $m)) return $m[1];
        return '';
    };
    $titleOptions = ['Support Shift', 'OPPT/ML', '#Memo', 'Cover Shift'];
@endphp

<div class="daily-shift-board">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h2 class="mb-0">Daily Shift Board</h2>
            <div class="text-muted small">Forecast List + Shift Assigner</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-info">Admin View</span>
            <a href="{{ route('calendar.forecast', ['month' => substr($selectedDate, 0, 7)]) }}" class="btn btn-sm btn-outline-secondary">Forecast</a>
            <a href="{{ route('calendar.edit', ['event_date' => $selectedDate]) }}" class="btn btn-sm btn-outline-secondary">Full Assigner</a>
        </div>
    </div>

    <form method="GET" action="{{ route('calendar.daily_assigner') }}" class="dsb-date-form mb-2">
        <div class="row g-2 align-items-end">
            <div class="col-7 col-sm-4 col-lg-2">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate }}" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Select</button>
            </div>
            <div class="col-auto">
                <a href="{{ route('calendar.daily_assigner', ['date' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">Today</a>
            </div>
            <div class="col text-end">
                <span class="text-muted small">{{ $events->count() }} Shift(s)</span>
            </div>
        </div>
    </form>

    <div class="dsb-top-grid">
        <section class="dsb-calendar-section">
            <div class="dsb-section-head">
                <span>Forecast List</span>
                <span class="text-muted small">{{ $selectedDate }}</span>
            </div>
            <div id="dailyCalendarPane" class="dsb-calendar-pane">
                <div id="dailyCalendar"></div>
            </div>
        </section>

        <aside class="dsb-sub-section">
            <div class="dsb-section-head">
                <span>SUB</span>
                <span id="dailySubCount" class="badge text-bg-secondary">0</span>
            </div>
            <div id="dailySubSummary" class="dsb-sub-summary">
                <div class="text-muted small">Loading...</div>
            </div>
        </aside>
    </div>

    <div class="dsb-resize-handle" data-target="dailyCalendarPane" data-storage-key="dailyShiftBoardCalendarHeight">
        <span class="dsb-resize-grip">&#8942;&#8942;&#8942;</span>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-2 mb-2">
        <div class="fw-semibold">Shift Editor</div>
        <div class="d-flex align-items-center gap-2">
            <span id="dailyBoardSaveStatus" class="small text-muted"></span>
            <button type="button"
                class="btn btn-sm btn-primary"
                id="dailyBoardBulkSave"
                data-url="{{ route('events.bulk_update') }}">
                Bulk Save
            </button>
        </div>
    </div>

    <div id="dailyEditorPane" class="dsb-editor-pane">
        @if($events->isEmpty())
            <div class="alert alert-light border mb-0">No event matches this date.</div>
        @else
            <datalist id="dailyTitleOptions">
                @foreach($titleOptions as $opt)
                    <option value="{{ $opt }}">
                @endforeach
            </datalist>
            <datalist id="dailySchoolOptions">
                @foreach($schoolNames as $s)
                    <option value="{{ $s }}">
                @endforeach
            </datalist>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle daily-shift-table">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Original</th>
                            <th>Leave</th>
                            <th>School</th>
                            <th>Date</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Total</th>
                            <th>Lesson</th>
                            <th>Assigned</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($events as $event)
                        <tr class="js-daily-event-row" data-event-id="{{ $event->id }}">
                            <td class="text-muted">#{{ $event->id }}</td>
                            <td>
                                <input list="dailyTitleOptions" name="title" class="form-control form-control-sm" value="{{ $event->title }}" style="min-width:9rem">
                            </td>
                            <td>
                                <select name="original_user_id" class="form-select form-select-sm" style="min-width:11rem">
                                    <option value="">-</option>
                                    @foreach($userOptions as $u)
                                        <option value="{{ $u->id }}" @selected($event->original_user_id === $u->id)>
                                            {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="Leave_type" class="form-control form-control-sm" value="{{ $event->Leave_type }}" style="min-width:7rem">
                            </td>
                            <td>
                                <input list="dailySchoolOptions" name="school_name" class="form-control form-control-sm" value="{{ $event->school_name }}" style="min-width:9rem">
                            </td>
                            <td>
                                <input type="date" name="event_date" class="form-control form-control-sm" value="{{ $fmtDate($event->event_date) }}" style="min-width:8.5rem">
                            </td>
                            <td>
                                <input type="time" name="start_time" class="form-control form-control-sm js-dsb-time" value="{{ $fmtTime($event->start_time) }}" step="60">
                            </td>
                            <td>
                                <input type="time" name="end_time" class="form-control form-control-sm js-dsb-time" value="{{ $fmtTime($event->end_time) }}" step="60">
                            </td>
                            <td>
                                <input type="text" name="total_duration" class="form-control form-control-sm js-dsb-total" value="{{ $event->total_duration }}" placeholder="H:MM" readonly style="min-width:4.5rem">
                            </td>
                            <td>
                                <textarea name="Lesson" class="form-control form-control-sm" rows="1" style="min-width:9rem">{{ $event->Lesson }}</textarea>
                            </td>
                            <td>
                                <select name="assigned_user_id" class="form-select form-select-sm" style="min-width:11rem">
                                    <option value="">-</option>
                                    @foreach($userOptions as $u)
                                        <option value="{{ $u->id }}" @selected($event->assigned_user_id === $u->id)>
                                            {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="type" class="form-select form-select-sm">
                                    @foreach($typeOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($event->type === $v)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="status" class="form-select form-select-sm js-dsb-status">
                                    @foreach($statusOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($event->status === $v)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <textarea name="notes" class="form-control form-control-sm" rows="1" style="min-width:10rem">{{ $event->notes }}</textarea>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-success js-dsb-row-save">Save</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="dsb-resize-handle" data-target="dailyEditorPane" data-storage-key="dailyShiftBoardEditorHeight">
        <span class="dsb-resize-grip">&#8942;&#8942;&#8942;</span>
    </div>
</div>

<div class="modal fade" id="dailyEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="dailyEventModalTitle">Shift Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dailyEventModalBody">Reading...</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css" rel="stylesheet">
<link href="{{ asset('css/forecast-custom.css') }}" rel="stylesheet">
<link href="{{ asset('css/daily-shift-board.css') }}?v={{ filemtime(public_path('css/daily-shift-board.css')) }}" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
    window.dailyShiftBoard = {
        selectedDate: @json($selectedDate),
        eventsUrl: @json(route('calendar.forecast.events')),
        bulkUpdateUrl: @json(route('events.bulk_update')),
    };
</script>
<script src="{{ asset('js/daily-shift-board.js') }}?v={{ filemtime(public_path('js/daily-shift-board.js')) }}"></script>
@endpush
