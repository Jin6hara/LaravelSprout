@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">Sent Box</h2>

    <div class="small text-muted">
        @if($isAdmin)
        Showing posts sent by Admin/Super Admin.
        @else
        Showing only your sent posts.
        @endif
    </div>
</div>

<div class="card mt-2">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 120px;">Date</th>
                    <th>Title</th>
                    <th style="width: 220px;">Sender</th>
                    <th style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $p)
                @php
                $expired = $p->expires_at && \Carbon\Carbon::parse($p->expires_at)->isPast();
                $senderName = trim(($p->author->family_name ?? '').' '.($p->author->first_name ?? ''));
                $senderCode = $p->author->employee_code ?? null;
                @endphp
                <tr role="button"
                    class="cursor-pointer"
                    onclick="window.location='{{ route('messages.show', $p) }}'">
                    <td class="text-nowrap">
                        <div>{{ $p->created_at->format('Y-m-d') }}</div>
                        <div class="small text-muted">{{ $p->created_at->format('H:i') }}</div>
                    </td>

                    <td class="text-truncate" style="max-width: 0;">
                        <div class="fw-semibold">
                            {{ $p->title ?? '(No Title)' }}
                        </div>
                        <div class="small text-muted text-truncate">
                            {{ \Illuminate\Support\Str::limit($p->body, 90) }}
                        </div>
                    </td>

                    <td class="text-nowrap">
                        <div>{{ $senderName !== '' ? $senderName : 'Unknown' }}</div>
                        @if($senderCode)
                        <div class="small text-muted">[{{ $senderCode }}]</div>
                        @endif
                    </td>

                    <td class="text-nowrap">
                        @if($expired)
                        <span class="badge text-bg-secondary">Expired</span>
                        @else
                        <span class="badge text-bg-success">Active</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        No sent posts.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($posts->hasPages())
    <div class="card-footer">
        {{ $posts->links() }}
    </div>
    @endif
</div>
@endsection