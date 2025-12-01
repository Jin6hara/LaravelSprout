@php $wrapperClass = $depth > 0 ? 'mt-2 ms-2' : 'mb-3'; @endphp

<div class="{{ $wrapperClass }}">
    <div class="border rounded"
        style="padding-top: 3px; padding-right: 1px; padding-bottom: 3px; padding-left: 2px;
            background-color: {{ $depth > 0 ? '#fcfdffff' : '#fafffaff' }};">
        <div class="d-flex justify-content-between">
            <div>
                <strong>
                    {{ $comment->author->family_name }} {{ $comment->author->first_name }}
                    <span class="opacity-75">[{{ $comment->author->employee_code }}]</span>
                </strong>
                <span class="text-muted small ms-2">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                @if($depth > 0 && optional($comment->parent)->user_id !== optional($me)->id)
                <span class="badge text-bg-light ms-2">in reply to {{ optional($comment->parent?->author)->family_name }} {{ optional($comment->parent?->author)->first_name }}</span>
                @endif
            </div>

            {{-- 返信 --}}
            @php
            $isAdmin = method_exists($me, 'isAdmin') ? $me->isAdmin() : false;

            // 一般ユーザー画面は「自分スレッドのみ」描画なのでツリー内は全て返信可
            // 管理者は常に返信可。最終的な制限はサーバ側で再チェック。
            $canReplyThis = (method_exists($post, 'allowsReplies') ? $post->allowsReplies() : true)
            && ($isAdmin || true);
            @endphp
            @if($canReplyThis)
            <button
                class="mt-1 me-1 btn btn-outline-primary btn-sm btn-reply"
                data-comment-id="{{ $comment->id }}"
                data-author-name="{{ $comment->author->family_name }} {{ $comment->author->first_name }}">
                Reply
            </button>
            @endif
        </div>

        <div class="mt-1" style="white-space: pre-wrap;">{{ $comment->body }}</div>

        {{-- ★ このコメントに対する返信フォームの差し込み先（空） --}}
        <div class="reply-slot" data-reply-slot="{{ $comment->id }}"></div>

        {{-- 子孫（自分宛の返信もここで表示される） --}}
        @foreach($comment->children as $child)
        @include('posts.partials.comment_thread', ['comment' => $child, 'depth' => $depth + 1, 'post' => $post, 'me' => $me])
        @endforeach
    </div>

</div>