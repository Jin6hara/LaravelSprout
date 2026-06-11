@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">通知</h2>
        @if(($unreadNotificationsCount ?? 0) > 0)
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button class="btn btn-sm btn-outline-primary">すべて既読にする</button>
        </form>
        @endif
    </div>

    {{-- 上段：未承認（pending or state不明） --}}
    <h5 class="mb-2">未処理</h5>
    <div class="list-group mb-4">
        @forelse ($topNotifications as $n)
            @php
                $data = $n->data ?? [];
                $isUnread = $n->is_unread ?? is_null($n->read_at);
                $state = $n->computed_state; // 'pending'|null
            @endphp
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $isUnread ? 'bg-light' : '' }}">
                <div class="me-3">
                    <div class="fw-semibold">
                        {{ $data['title'] ?? '通知' }}
                        @if($isUnread)
                            <span class="badge bg-danger ms-1">未読</span>
                        @endif
                        <span class="badge bg-warning text-dark ms-1">未承認</span>
                    </div>
                    <div class="small text-muted">
                        {{ $n->created_at->format('Y-m-d H:i') }}
                    </div>
                    <div class="mt-1">
                        @if(isset($data['url']))
                            <a href="{{ route('notifications.go', $n) }}" class="btn btn-sm btn-outline-primary">詳細へ</a>{{-- 詳細ページへ遷移+既読 --}}
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
            <div class="text-center text-muted py-4">未承認の通知はありません。</div>
        @endforelse
    </div>

    {{-- 下段：承認済み/却下済み --}}
    <h5 class="mb-2">処理済み</h5>
    <div class="list-group">
        @forelse ($bottomNotifications as $n)
            @php
                $data = $n->data ?? [];
                $isUnread = $n->is_unread ?? is_null($n->read_at);
                $state = $n->computed_state; // approved|rejected
                $badge = $state === 'approved' ? 'success' : ($state === 'rejected' ? 'secondary' : 'light');
                $stateLabel = $state === 'approved' ? '承認済み' : ($state === 'rejected' ? '差し戻し' : '処理済み');
            @endphp
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $isUnread ? 'bg-light' : '' }}">
                <div class="me-3">
                    <div class="fw-semibold">
                        {{ $data['title'] ?? '通知' }}
                        @if($isUnread)
                            <span class="badge bg-danger ms-1">未読</span>
                        @endif
                        <span class="badge bg-{{ $badge }} ms-1">{{ $stateLabel }}</span>
                    </div>
                    <div class="small text-muted">
                        {{ $n->created_at->format('Y-m-d H:i') }}
                    </div>
                    <div class="mt-1">
                        @if(isset($data['url']))
                            <a href="{{ route('notifications.go', $n) }}" class="btn btn-sm btn-outline-primary">詳細へ</a> {{-- 詳細ページへ遷移+既読 --}}
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
            <div class="text-center text-muted py-4">処理済みの通知はありません。</div>
        @endforelse
    </div>
</div>
@endsection
