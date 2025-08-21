@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">通知</h2>
        @if(auth()->user()->unreadNotifications()->count() > 0)
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button class="btn btn-sm btn-outline-primary">すべて既読にする</button>
        </form>
        @endif
    </div>

    <div class="list-group">
        @forelse ($notifications as $n)
        @php
        $data = $n->data ?? [];
        $isUnread = is_null($n->read_at);
        @endphp
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $isUnread ? 'bg-light' : '' }}">
            <div class="me-3">
                <div class="fw-semibold">
                    {{ $data['title'] ?? '通知' }}
                    @if($isUnread)
                    <span class="badge bg-danger ms-1">未読</span>
                    @endif
                </div>
                <div class="small text-muted">
                    {{ $n->created_at->format('Y-m-d H:i') }}
                </div>
                <div class="mt-1">
                    @if(isset($data['url']))
                    <a href="{{ $data['url'] }}" class="btn btn-sm btn-outline-primary">詳細へ</a>
                    @endif
                </div>
            </div>

            <div class="text-nowrap">
                @if($isUnread)
                <form method="POST" action="{{ route('notifications.read', $n) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">既読</button>
                </form>
                @else
                <span class="text-muted small">既読</span>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">通知はありません。</div>
        @endforelse
    </div>

    @if ($notifications->hasPages())
    <div class="mt-3">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
@endsection