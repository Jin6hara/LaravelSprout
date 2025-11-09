@php
// 親は下マージン、子は左に詰めて少しだけ奥行きを出す
$wrapperClass = $depth > 0 ? 'mt-2 ms-2' : 'mb-3';
@endphp

<div class="{{ $wrapperClass }}">
    {{-- 四角枠（各レベル共通） --}}
    <div class="border rounded p-1 p-md-2" style="background-color: #f7fbff2a;">
        <div class="d-flex justify-content-between">
            <div>
                <strong>
                    {{ $comment->author->family_name }} {{ $comment->author->first_name }}
                    <span class="opacity-75">[{{ $comment->author->employee_code }}]</span>
                </strong>
                <span class="text-muted small ms-1">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
            </div>

            @if(method_exists($post, 'allowsReplies') ? $post->allowsReplies() : true)
            <button
                class="btn btn-outline-primary btn-sm btn-reply"
                data-comment-id="{{ $comment->id }}"
                data-author-name="{{ $comment->author->family_name }} {{ $comment->author->first_name }}">
                Reply
            </button>
            @endif
        </div>

        <div class="mt-1" style="white-space: pre-wrap;">{{ $comment->body }}</div>

        {{-- 子（箱の中にさらに箱） --}}
        @if($comment->children->isNotEmpty())
        <div class="mt-2">
            @foreach($comment->children as $child)
            @include('posts.partials.comment_thread', ['comment' => $child, 'depth' => $depth + 1, 'post' => $post])
            @endforeach
        </div>
        @endif
    </div>
</div>