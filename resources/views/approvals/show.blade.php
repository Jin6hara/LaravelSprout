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

<x-generated-shift-confirm-modal
    id="denyGeneratedShiftConfirmModal"
    title="Deny Confirmation"
    message="This leave request has generated shift(s). Please choose what to do before denying it."
/>
@endif

<link href="{{ asset('css/generated-shift-confirmation.css') }}?v={{ filemtime(public_path('css/generated-shift-confirmation.css')) }}" rel="stylesheet">

<style>
.approval-show-panel {
    background-color: #eef6ff;
}

.approval-show-panel .form-control,
.approval-show-panel .form-select {
    background-color: #fff;
}
</style>

@if($isLeave)
<script src="{{ asset('js/generated-shift-confirmation.js') }}?v={{ filemtime(public_path('js/generated-shift-confirmation.js')) }}"></script>
<script>
GeneratedShiftConfirmation.bindApprovalDenyForm({
    formSelector: '.js-approval-deny-form',
    sourceId: 'approval-generated-shifts',
    modalId: 'denyGeneratedShiftConfirmModal',
    text: 'This leave request has generated shift(s). Please choose what to do before denying it.',
    summaryBuilder: ({ shifts }) => `${shifts.length} generated shift(s) are linked to this request.`,
});
</script>
@endif
@endsection
