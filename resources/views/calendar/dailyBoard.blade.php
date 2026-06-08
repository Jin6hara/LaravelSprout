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
    $previousDate = \Illuminate\Support\Carbon::parse($selectedDate)->subDay()->toDateString();
    $nextDate = \Illuminate\Support\Carbon::parse($selectedDate)->addDay()->toDateString();
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
                <div class="btn-group btn-group-sm" role="group" aria-label="Daily navigation">
                    <a href="{{ route('calendar.daily_assigner', ['date' => $previousDate]) }}" class="btn btn-outline-secondary">&lt;</a>
                    <a href="{{ route('calendar.daily_assigner', ['date' => $nextDate]) }}" class="btn btn-outline-secondary">&gt;</a>
                </div>
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

        <div class="dsb-width-handle" title="Resize SUB width" aria-hidden="true">
            <span class="dsb-width-grip">&#8942;</span>
        </div>

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

    <div class="d-flex align-items-center gap-2 mt-2 mb-2">
        <div class="fw-semibold">Shift Editor</div>
        <form method="POST" action="{{ route('leaves.store') }}" class="d-flex align-items-center gap-1">
            @csrf
            <input type="hidden" name="kind" value="absence">
            <input type="hidden" name="excused" value="unexcused">
            <input type="hidden" name="status" value="pending">
            <input type="hidden" name="start_date" value="{{ $selectedDate }}">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.daily_assigner', ['date' => $selectedDate]) }}">
            <input type="hidden" name="user_id" id="dailyAbsenceUserId">
            <datalist id="dailyAbsenceUserOptions"></datalist>
            <input type="text"
                name="user_lookup"
                class="form-control form-control-sm"
                style="width: 16rem"
                placeholder="Search user"
                list="dailyAbsenceUserOptions"
                autocomplete="off"
                required
                data-user-autocomplete
                data-user-autocomplete-url="{{ route('api.users.search') }}"
                data-user-autocomplete-hidden="#dailyAbsenceUserId"
                data-user-autocomplete-limit="20">
            <button type="submit" class="btn btn-sm btn-warning text-nowrap">Create Absence for {{ $selectedDate }}</button>
        </form>
        <button type="button" class="btn btn-sm btn-success text-nowrap" data-bs-toggle="modal" data-bs-target="#shiftCreateModal">
            + Add Shift
        </button>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span id="dailyBoardSaveStatus" class="small text-muted d-none"></span>
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

            <div id="dailyEditorScroll" class="table-responsive dsb-editor-scroll">
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
                            <td class="text-muted">
                                #{{ $event->id }}
                                <input type="hidden" name="updated_at" value="{{ $event->updated_at?->format('Y-m-d H:i:s') }}">
                            </td>
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
                                <textarea name="Lesson" class="form-control form-control-sm" rows="1" style="min-width:16.2rem">{{ $event->Lesson }}</textarea>
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
                                <select name="type" class="form-select form-select-sm" style="min-width:4.8rem">
                                    @foreach($typeOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($event->type === $v)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="status" class="form-select form-select-sm js-dsb-status" style="min-width:7rem">
                                    @foreach($statusOptions as $v => $label)
                                        <option value="{{ $v }}" @selected($event->status === $v)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <textarea name="notes" class="form-control form-control-sm" rows="1" style="min-width:17rem">{{ $event->notes }}</textarea>
                            </td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-success js-dsb-row-save">Save</button>
                                <button type="button"
                                    class="btn btn-sm btn-outline-secondary js-shift-copy"
                                    data-store="{{ route('events.copy') }}">
                                    Copy
                                </button>
                                @if($event->source_leave_id)
                                    <button type="button"
                                        class="btn btn-sm btn-secondary"
                                        disabled
                                        title="Managed by absence">
                                        Delete
                                    </button>
                                @else
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger js-delete"
                                        data-url="{{ route('events.destroy', $event) }}"
                                        data-date="{{ $event->event_date?->format('Y-m-d') ?? 'not set' }}">
                                        Delete
                                    </button>
                                @endif
                                <div class="form-check mb-0">
                                    <input class="form-check-input js-daily-exclude-event" type="checkbox" value="{{ $event->id }}" id="dailyEx{{ $event->id }}">
                                    <label class="form-check-label small" for="dailyEx{{ $event->id }}">
                                        Exclude
                                    </label>
                                </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <form id="js-delete-form" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    <div class="dsb-resize-handle" data-target="dailyEditorPane" data-storage-key="dailyShiftBoardEditorHeight">
        <span class="dsb-resize-grip">&#8942;&#8942;&#8942;</span>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="{{ route('calendar.edit.pdf.preview', ['mode' => 'tentative', 'event_date' => $selectedDate]) }}"
            target="_blank"
            rel="noopener"
            class="btn btn-sm btn-outline-primary js-daily-sublist-preview">
            Tentative Sublist Preview
        </a>
        <a href="{{ route('calendar.edit.pdf.preview', ['mode' => 'master', 'event_date' => $selectedDate]) }}"
            target="_blank"
            rel="noopener"
            class="btn btn-sm btn-outline-primary js-daily-sublist-preview">
            Master Sublist Preview
        </a>
        <a href="{{ route('calendar.edit.pdf.preview', ['mode' => 'final', 'event_date' => $selectedDate]) }}"
            target="_blank"
            rel="noopener"
            class="btn btn-sm btn-outline-primary js-daily-sublist-preview">
            Final Sublist Preview
        </a>
    </div>
</div>

<div class="modal fade" id="shiftCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('events.store') }}" class="modal-content shift-copy-form">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ route('calendar.daily_assigner', ['date' => $selectedDate]) }}">
            <div class="modal-header">
                <h5 class="modal-title mb-0">New Shift for {{ $selectedDate }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body shift-copy-modal-body">
                <datalist id="dailyCreateTitleOptions">
                    @foreach($titleOptions as $opt)
                        <option value="{{ $opt }}">
                    @endforeach
                </datalist>
                <datalist id="dailyCreateSchoolOptions">
                    @foreach($schoolNames as $s)
                        <option value="{{ $s }}">
                    @endforeach
                </datalist>
                <datalist id="dailyCreateOriginalUserOptions"></datalist>
                <datalist id="dailyCreateAssignedUserOptions"></datalist>

                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Date</label>
                        <input type="date" name="event_date" class="form-control form-control-sm" value="{{ $selectedDate }}" readonly required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Title</label>
                        <input list="dailyCreateTitleOptions" name="title" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Leave</label>
                        <input type="text" name="Leave_type" class="form-control form-control-sm">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">Original User</label>
                        <input type="hidden" name="original_user_id" id="dailyCreateOriginalUserId">
                        <input type="text"
                            name="original_user_lookup"
                            class="form-control form-control-sm"
                            list="dailyCreateOriginalUserOptions"
                            autocomplete="off"
                            data-user-autocomplete
                            data-user-autocomplete-url="{{ route('api.users.search') }}"
                            data-user-autocomplete-hidden="#dailyCreateOriginalUserId"
                            data-user-autocomplete-limit="20">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">Assigned User</label>
                        <input type="hidden" name="assigned_user_id" id="dailyCreateAssignedUserId">
                        <input type="text"
                            name="assigned_user_lookup"
                            class="form-control form-control-sm"
                            list="dailyCreateAssignedUserOptions"
                            autocomplete="off"
                            data-user-autocomplete
                            data-user-autocomplete-url="{{ route('api.users.search') }}"
                            data-user-autocomplete-hidden="#dailyCreateAssignedUserId"
                            data-user-autocomplete-limit="20">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">School</label>
                        <input list="dailyCreateSchoolOptions" name="school_name" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Start</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" step="60">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">End</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" step="60">
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-1">Lesson</label>
                        <textarea name="Lesson" class="form-control form-control-sm" rows="2"></textarea>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            @foreach($typeOptions as $v => $label)
                                <option value="{{ $v }}" @selected($v === \App\Enums\ShiftType::RegularTime->value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach($statusOptions as $v => $label)
                                <option value="{{ $v }}" @selected($v === \App\Enums\EventStatus::Pending->value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Total</label>
                        <input type="text" name="total_duration" class="form-control form-control-sm" placeholder="Auto">
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-1">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-success">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="shiftCopyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('events.copy') }}" class="modal-content shift-copy-form" id="shiftCopyForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="shiftCopyModalTitle">Copy Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body shift-copy-modal-body">
                <datalist id="dailyCopyTitleOptions">
                    @foreach($titleOptions as $opt)
                        <option value="{{ $opt }}">
                    @endforeach
                </datalist>
                <datalist id="dailyCopySchoolOptions">
                    @foreach($schoolNames as $s)
                        <option value="{{ $s }}">
                    @endforeach
                </datalist>

                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Date</label>
                        <input type="date" name="event_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Title</label>
                        <input list="dailyCopyTitleOptions" name="title" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Leave</label>
                        <input type="text" name="Leave_type" class="form-control form-control-sm">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">Original User</label>
                        <select name="original_user_id" class="form-select form-select-sm">
                            <option value="">-</option>
                            @foreach($userOptions as $u)
                                <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">Assigned User</label>
                        <select name="assigned_user_id" class="form-select form-select-sm">
                            <option value="">-</option>
                            @foreach($userOptions as $u)
                                <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">School</label>
                        <input list="dailyCopySchoolOptions" name="school_name" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Start</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" step="60">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">End</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" step="60">
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-1">Lesson</label>
                        <textarea name="Lesson" class="form-control form-control-sm" rows="2"></textarea>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm" required>
                            @foreach($typeOptions as $v => $label)
                                <option value="{{ $v }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm" required>
                            @foreach($statusOptions as $v => $label)
                                <option value="{{ $v }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Total</label>
                        <input type="text" name="total_duration" class="form-control form-control-sm" placeholder="Auto">
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-1">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Create Copy</button>
            </div>
        </form>
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

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title" id="deleteConfirmLabel">Delete Confirmation</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="deleteConfirmText">Are you sure you want to delete this shift?</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css" rel="stylesheet">
<link href="{{ asset('css/forecast-custom.css') }}" rel="stylesheet">
<link href="{{ asset('css/shift-copy-modal.css') }}?v={{ filemtime(public_path('css/shift-copy-modal.css')) }}" rel="stylesheet">
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
<script src="{{ asset('js/user-autocomplete.js') }}?v={{ filemtime(public_path('js/user-autocomplete.js')) }}"></script>
<script src="{{ asset('js/shift-copy-modal.js') }}?v={{ filemtime(public_path('js/shift-copy-modal.js')) }}"></script>
<script>
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-delete');
        if (!btn) return;

        const modalText = document.getElementById('deleteConfirmText');
        modalText.textContent = `Are you sure you want to delete ${btn.dataset.date || 'this shift'}?`;

        const form = document.getElementById('js-delete-form');
        form.action = btn.dataset.url;

        const modalEl = document.getElementById('deleteConfirmModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.onclick = () => {
            modal.hide();
            form.submit();
        };
    });
</script>
<script>
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.js-daily-sublist-preview');
        if (!link) return;

        const url = new URL(link.href);
        url.searchParams.delete('exclude_event_ids[]');
        document.querySelectorAll('.js-daily-exclude-event:checked').forEach((checkbox) => {
            url.searchParams.append('exclude_event_ids[]', checkbox.value);
        });
        link.href = url.toString();
    });
</script>
<script src="{{ asset('js/daily-shift-board.js') }}?v={{ filemtime(public_path('js/daily-shift-board.js')) }}"></script>
@endpush
