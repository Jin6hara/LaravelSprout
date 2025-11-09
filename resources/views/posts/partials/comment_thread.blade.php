@php
// depth に応じて左インデント（Bootstrapの ms-2 と ps-2 を depth 回足す）
// ただしクラスを depth 回繰り返すと冗長になるので style でマージンでもOK。
// 要件が「padding は 2」なので、階層ごとに ms-2 ps-2 を積み増す形で。
@endphp

<div class="{{ $depth > 0 ? 'mt-2 border-start' : 'mb-3' }} ps-2 ms-2">
    <div class="d-flex justify-content-between">
        <div>
            <strong>
                {{ $comment->author->family_name }} {{ $comment->author->first_name }}
                <span class="opacity-75">[{{ $comment->author->employee_code }}]</span>
            </strong>
            <span class="text-muted small ms-2">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
        </div>

        @if(method_exists($post, 'allowsReplies') ? $post->allowsReplies() : true)
        <button
            class="btn btn-outline-primary btn-sm btn-reply"
            data-comment-id="{{ $comment->id }}" {{-- どの階層でもこのコメントへ返信 --}}
            data-author-name="{{ $comment->author->family_name }} {{ $comment->author->first_name }}">
            Reply
        </button>
        @endif
    </div>

    <div class="mt-1" style="white-space: pre-wrap;">{{ $comment->body }}</div>

    {{-- 子孫（直下の子のリスト） --}}
    @foreach($comment->children as $child)
    @include('posts.partials.comment_thread', ['comment' => $child, 'depth' => $depth + 1, 'post' => $post])
    @endforeach
</div>