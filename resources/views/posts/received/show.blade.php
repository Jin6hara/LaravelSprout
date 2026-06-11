{{-- 受信メッセージの詳細とコメントスレッドを表示するビュー --}}
{{-- resources/views/posts/received/show.blade.php --}}
@extends('layouts.app')

@section('content')

@include('posts.received.statusList')

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">Message</h2>

    <a href="{{ route('messages.index', request()->only('employee_code')) }}"
        class="btn btn-outline-secondary btn-sm">Back to Inbox</a>
</div>

@if($isProxy)
<span class="badge text-bg-info mb-2">
    Admin View: {{ $target->first_name }} {{ $target->family_name }} [{{ $target->employee_code }}]
</span>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div>
                <div class="small text-muted">{{ $post->created_at->format('Y-m-d H:i') }}</div>
                <h4 class="mb-1">{{ $post->title ?? '(No TItle)' }}</h4>
                <div class="text-muted">
                    From: {{ $post->author->family_name }} {{ $post->author->first_name }}
                    <span class="opacity-75">[{{ $post->author->employee_code }}]</span>
                </div>
            </div>
            {{-- Confirm ボタン（本人かつ recipients かつ未確認のみ） --}}
            @if($me->id === $target->id && $pivot && is_null($pivot->confirmed_at))
            <form method="POST" action="{{ route('messages.confirm', $post) }}">
                @csrf
                <button class="btn btn-success btn-sm">Confirm</button>
            </form>
            @endif
        </div>

        <hr>
        <div class="mt-2" style="white-space:pre-wrap;">{{ $post->body }}</div>

        {{-- 添付があるなら表示 --}}
        @if($post->attachments()->exists())
        <hr>
        <div>
            <div class="fw-bold mb-2">添付</div>
            @foreach($post->attachments as $att)
            <div class="mb-1">
                <a href="{{ route('posts.attachments.show', [$post, $att]) }}" target="_blank">
                    {{ $att->original_name ?? basename($att->path) }}
                </a>
                <span class="text-muted small">({{ number_format(($att->size ?? 0)/1024,1) }} KB)</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- === コメント一覧 & 返信 =================================== --}}
<div class="card">
    <div class="card-header py-2">
        <strong>Replies</strong>
    </div>
    <div class="card-body">

        {{-- 投稿フォーム（返信可のみ） --}}
        @if(method_exists($post, 'allowsReplies') ? $post->allowsReplies() : true)
        @php
        $replyProps = [
        'actionUrl' => route('messages.comments.store', $post),
        'csrf' => csrf_token(),
        'parkingSelector' => '#replyFormParking',
        'initialBody' => old('body', ''),
        'initialParentId' => old('parent_id', ''),
        ];
        @endphp

        {{-- Vue mount point --}}
        <div id="replyComposer" data-props='@json($replyProps)'></div>

        {{-- ここにフォームが表示される（初期位置/キャンセル時の戻り場所） --}}
        <div id="replyFormParking"></div>
        @else
        <div class="alert alert-warning py-2 mb-3">
            Replies are closed for this message.
        </div>
        @endif
    </div>
    <div class="card-body border-top">
        {{-- コメント一覧（親のみ） --}}
        @forelse($comments as $c)
        @include('posts.partials.comment_thread', ['comment' => $c, 'depth' => 0, 'post' => $post, 'me' => $me])
        @empty
    </div>
    <div class="text-muted">No replies yet.</div>
    @endforelse
</div>

</div>
@endsection