@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Approval Request Details</h2>

    <div class="card mt-3">
        <div class="card-body approval-show-panel">
            @php
            $meta       = $approvalRequest->metadata ?? [];
            $approvable = $approvalRequest->approvable;
            $isLeave    = $approvable instanceof \App\Models\Leave;
            $generatedShiftDetails = collect($generatedShiftDetails ?? []);
            @endphp

            <table class="table table-bordered">
                <tr>
                    <th>Title</th>
                    <td>{{ $approvalRequest->title }}</td>
                </tr>
                <tr>
                    <th>Requester</th>
                    <td>{{ $approvalRequest->requester->name }} [{{ $approvalRequest->requester->employee_code }}]</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @if($approvalRequest->current_state === 'pending')
                        <span class="badge bg-warning">Pending</span>
                        @elseif($approvalRequest->current_state === 'approved')
                        <span class="badge bg-success">Approved</span>
                        @elseif($approvalRequest->current_state === 'denied')
                        <span class="badge bg-danger">Denied</span>
                        @endif
                    </td>
                </tr>

                @if($isLeave)
                {{-- Leave request --}}
                <tr>
                    <th>Request Type</th>
                    <td>
                        @php
                        $kind = $meta['kind'] ?? $approvable->kind ?? null;
                        $kindLabel = match($kind) {
                        'paid' => 'Paid Leave (ALP)',
                        'special' => 'Special Leave',
                        default => $kind,
                        };
                        @endphp
                        {{ $kindLabel ?? '-' }}
                    </td>
                </tr>
                @if(($meta['kind'] ?? null) === 'special' || ($approvable->kind ?? null) === 'special')
                <tr>
                    <th>Special Leave Type</th>
                    <td>{{ $meta['special_type'] ?? $approvable->special_type ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Attachment</th>
                    <td>
                        @if($approvable->attachment)
                        <a href="{{ route('leaves.attachments.show', ['leave' => $approvable->id, 'attachment' => $approvable->attachment->id]) }}" target="_blank">
                            {{ $approvable->attachment->original_name ?? 'Open attachment' }}
                        </a>
                        @if($approvable->attachment->size)
                        <small>　{{ number_format($approvable->attachment->size / 1024, 1) }} KB</small>
                        @endif
                        @else
                        -
                        @endif
                    </td>
                </tr>
                @endif
                <tr>
                    <th>Target Date</th>
                    <td>{{ $dateSummary ?? ($meta['date'] ?? optional($approvable->start_date)->format('Y-m-d') ?? '-') }}</td>
                </tr>
                <tr>
                    <th>Reason</th>
                    <td>{{ $meta['reason'] ?? ($approvable->reason ?? '-') }}</td>
                </tr>

                @else
                {{-- Role change request --}}
                <tr>
                    <th>Target User</th>
                    <td>{{ $meta['target_user_name'] ?? 'Unknown' }}</td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td>
                        {{ $meta['current_role'] ?? '—' }}
                        →
                        <strong>{{ $meta['requested_role'] ?? '—' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>District</th>
                    <td>
                        {{ $meta['current_district'] ?? '—' }}
                        →
                        <strong>{{ $meta['requested_district'] ?? '—' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td>
                        {{ $meta['current_department'] ?? '—' }}
                        →
                        <strong>{{ $meta['requested_department'] ?? '—' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>Management Scope</th>
                    <td>
                        <div class="d-flex gap-4">
                            <div>
                                <div class="text-muted small mb-1">Current</div>
                                @php $currentScopes = $meta['current_scopes'] ?? []; @endphp
                                @if(empty($currentScopes))
                                    <span class="text-muted">—</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($currentScopes as $s)
                                        <li>{{ $s['district'] ?? '—' }} ／ {{ $s['department'] ?? '—' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="text-muted align-self-center">→</div>
                            <div>
                                <div class="text-muted small mb-1">Requested</div>
                                @php $requestedScopes = $meta['requested_scopes'] ?? []; @endphp
                                @if(empty($requestedScopes))
                                    <span class="text-muted">—</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($requestedScopes as $s)
                                        <li><strong>{{ $s['district'] ?? '—' }} ／ {{ $s['department'] ?? '—' }}</strong></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Reason</th>
                    <td>{{ $meta['reason'] ?? '—' }}</td>
                </tr>
                @endif
            </table>

            {{-- Action history --}}
            <h5 class="mt-4">Action History</h5>
            <ul class="list-group mb-4">
                @forelse ($approvalRequest->actions as $action)
                <li class="list-group-item">
                    <strong>{{ $action->actor->name }}</strong>
                    <span class="text-primary">{{ $action->action }}</span>
                    ({{ $action->created_at->format('Y-m-d H:i') }})
                    <br>
                    Comment: {{ $action->comment ?? 'None' }}
                </li>
                @empty
                <li class="list-group-item">No approval or denial history yet.</li>
                @endforelse
            </ul>

            {{-- Approve / deny actions --}}
            @can('act', $approvalRequest)
            @if($approvalRequest->current_state === 'pending')
            <form action="{{ route('approvals.approve', $approvalRequest) }}" method="POST" class="d-inline">
                @csrf
                <input type="text" name="comment" class="form-control mb-2" placeholder="Approval comment (optional)">
                <button type="submit" class="btn btn-success mb-2">Approve</button>
            </form>

            <form action="{{ route('approvals.deny', $approvalRequest) }}" method="POST" class="d-inline ms-2 js-approval-deny-form">
                @csrf
                <input type="hidden" name="generated_shift_action" value="">
                <input type="text" name="comment" class="form-control mb-2" placeholder="Denial reason (optional)">
                <button type="submit" class="btn btn-danger mb-2">Deny</button>
            </form>
            @endif
            @endcan
        </div>
    </div>
</div>

@if($isLeave)
<script type="application/json" id="approval-generated-shifts">@json($generatedShiftDetails)</script>

<div class="modal fade" id="denyGeneratedShiftConfirmModal" tabindex="-1" aria-labelledby="denyGeneratedShiftConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-warning py-2">
                <h6 class="modal-title" id="denyGeneratedShiftConfirmLabel">Deny Confirmation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">This leave request has generated shift(s). Please choose what to do before denying it.</p>
                <div class="small text-muted mb-2" id="denyGeneratedShiftSummary"></div>
                <div class="generated-shift-list" id="denyGeneratedShiftWrap"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="denyKeepGeneratedShiftsBtn">Keep Shift</button>
                <button type="button" class="btn btn-danger btn-sm" id="denyDeleteGeneratedShiftsBtn">Delete Shift</button>
            </div>
        </div>
    </div>
</div>
@endif

<style>
.approval-show-panel {
    background-color: #eef6ff;
}

.approval-show-panel .form-control,
.approval-show-panel .form-select {
    background-color: #fff;
}

.generated-shift-list {
    max-height: min(52vh, 31rem);
    overflow-y: auto;
    padding-right: .25rem;
}

.generated-shift-card {
    background: #f8fbff;
    border: 1px solid #c8d8ec;
    border-radius: 6px;
    padding: .75rem;
}

.generated-shift-card + .generated-shift-card {
    margin-top: .5rem;
}

.generated-shift-meta {
    display: grid;
    gap: .5rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.generated-shift-field {
    min-width: 0;
}

.generated-shift-label {
    color: #6c757d;
    display: block;
    font-size: .75rem;
    line-height: 1.1;
}

.generated-shift-value {
    overflow-wrap: anywhere;
}

.generated-shift-wide {
    background: #fff;
    border: 1px solid #dce6f2;
    border-radius: 4px;
    margin-top: .5rem;
    padding: .5rem;
    white-space: pre-wrap;
}

@media (max-width: 576px) {
    .generated-shift-meta {
        grid-template-columns: 1fr;
    }
}
</style>

@if($isLeave)
<script>
function renderApprovalGeneratedShiftCards(wrap, shifts) {
    wrap.innerHTML = '';

    shifts.forEach((shift) => {
        const card = document.createElement('div');
        card.className = 'generated-shift-card';

        const title = document.createElement('div');
        title.className = 'fw-semibold mb-2';
        title.textContent = `Generated Shift #${shift.id || '-'}`;
        card.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'generated-shift-meta';
        [
            ['Leave ID', 'leave_id'],
            ['Date', 'date'],
            ['Original', 'original'],
            ['Assigned', 'assigned'],
            ['School', 'school'],
            ['Time', 'time'],
            ['Status', 'status'],
        ].forEach(([label, key]) => {
            const field = document.createElement('div');
            field.className = 'generated-shift-field';

            const labelEl = document.createElement('span');
            labelEl.className = 'generated-shift-label';
            labelEl.textContent = label;

            const valueEl = document.createElement('div');
            valueEl.className = 'generated-shift-value';
            valueEl.textContent = shift[key] || '-';

            field.append(labelEl, valueEl);
            meta.appendChild(field);
        });
        card.appendChild(meta);

        ['lesson', 'notes'].forEach((key) => {
            const wide = document.createElement('div');
            wide.className = 'generated-shift-wide';

            const labelEl = document.createElement('span');
            labelEl.className = 'generated-shift-label';
            labelEl.textContent = key === 'lesson' ? 'Lesson' : 'Notes';

            const valueEl = document.createElement('div');
            valueEl.className = 'generated-shift-value';
            valueEl.textContent = shift[key] || '-';

            wide.append(labelEl, valueEl);
            card.appendChild(wide);
        });

        wrap.appendChild(card);
    });
}

document.addEventListener('submit', (e) => {
    const form = e.target.closest('.js-approval-deny-form');
    if (!form) return;

    const actionInput = form.querySelector('input[name="generated_shift_action"]');
    if (actionInput?.value) return;

    const source = document.getElementById('approval-generated-shifts');
    const shifts = source ? JSON.parse(source.textContent || '[]') : [];
    if (shifts.length === 0) return;

    e.preventDefault();

    document.getElementById('denyGeneratedShiftSummary').textContent = `${shifts.length} generated shift(s) are linked to this request.`;
    renderApprovalGeneratedShiftCards(document.getElementById('denyGeneratedShiftWrap'), shifts);

    const modalEl = document.getElementById('denyGeneratedShiftConfirmModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    document.getElementById('denyKeepGeneratedShiftsBtn').onclick = () => {
        actionInput.value = 'detach';
        modal.hide();
        form.submit();
    };

    document.getElementById('denyDeleteGeneratedShiftsBtn').onclick = () => {
        actionInput.value = 'delete';
        modal.hide();
        form.submit();
    };
});
</script>
@endif
@endsection
