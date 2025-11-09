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

        {{-- 添付があるなら表示（任意：アイコン等はお好みで） --}}
        @if($post->attachments()->exists())
        <hr>
        <div>
            <div class="fw-bold mb-2">添付</div>
            @foreach($post->attachments as $att)
            <div class="mb-1">
                <a href="{{ Storage::url($att->path) }}" target="_blank">
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
        <form method="POST" action="{{ route('messages.comments.store', $post) }}" class="mb-3">
            @csrf
            <input type="hidden" name="parent_id" id="replyParentId" value="">
            <div class="mb-2">
                <label class="form-label small">Add a reply</label>
                <textarea name="body" class="form-control" rows="3" required>{{ old('body') }}</textarea>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary btn-sm" type="submit">Reply</button>
                <button class="btn btn-outline-secondary btn-sm d-none" type="button" id="cancelReplyBtn">Cancel replying</button>
                <span class="small text-muted" id="replyingToHint"></span>
            </div>
        </form>
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

{{-- 返信先セット用 JS --}}
<script>
    (function() {
        const parentIdInput = document.getElementById('replyParentId');
        const cancelBtn = document.getElementById('cancelReplyBtn');
        const hint = document.getElementById('replyingToHint');

        function setReplyTarget(id, name) {
            parentIdInput.value = id || '';
            if (id) {
                cancelBtn.classList.remove('d-none');
                hint.textContent = `Replying to: ${name}`;
            } else {
                cancelBtn.classList.add('d-none');
                hint.textContent = '';
            }
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-reply');
            if (!btn) return;
            setReplyTarget(btn.dataset.commentId, btn.dataset.authorName);
            // スクロールしてフォームにフォーカス
            const ta = document.querySelector('textarea[name="body"]');
            ta?.focus({
                preventScroll: false
            });
        });

        cancelBtn?.addEventListener('click', () => setReplyTarget('', ''));
    })();
</script>
@endsection