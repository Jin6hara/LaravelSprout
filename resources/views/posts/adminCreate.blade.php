{{-- 管理者がスタッフ宛にメッセージを作成・送信するビュー --}}
@extends('layouts.app')

@section('content')
<div id="ExampleComponent">
    <example-component></example-component> {{-- ★ テスト用 Vue コンポーネント --}}
</div>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Create Message (Admin)</h2>
</div>

<form id="postForm" method="POST" action="{{ route('posts.send') }}" enctype="multipart/form-data">
    @csrf

    <div class="card mb-3">
        <div class="card-body">

            {{-- タイプ --}}
            <div class="mb-3">
                @php
                use App\Enums\PostType;

                $typeOptions = [
                ['value'=>'announcement','label'=>'Notification','defaultReply'=> PostType::Announcement->defaultAllowReplies() ? 1 : 0],
                ['value'=>'inquiry','label'=>'Inquiry','defaultReply'=> PostType::Inquiry->defaultAllowReplies() ? 1 : 0],
                ['value'=>'dm','label'=>'Direct Message','defaultReply'=> PostType::DirectMessage->defaultAllowReplies() ? 1 : 0],
                ['value'=>'notice_urgent','label'=>'Urgent','defaultReply'=> PostType::NoticeUrgent->defaultAllowReplies() ? 1 : 0],
                ['value'=>'maintenance','label'=>'Maintenance','defaultReply'=> PostType::Maintenance->defaultAllowReplies() ? 1 : 0],
                ['value'=>'other','label'=>'Other','defaultReply'=> PostType::Other->defaultAllowReplies() ? 1 : 0],
                ];

                $props = [
                'searchUrl' => route('api.users.search'),
                'typeOptions' => $typeOptions,
                'initialType' => old('type', 'announcement'),
                // old が無い場合は null 扱いにして「type既定」を効かせる
                'initialAllowReplies' => old('allow_replies') === null ? null : (int) old('allow_replies', 1),
                'initialRecipientIds' => array_map('intval', old('recipients', [])),
                ];
                @endphp

                <div id="adminCreateControls" data-props='@json($props)'></div>

                <div class="form-text">type decides default values such as allow replies.</div>
            </div>

            {{-- タイトル --}}
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control" maxlength="255">
            </div>

            {{-- 添付 --}}
            <div class="mb-3">
                <label class="form-label">Attachment(s)</label>
                <input type="file" name="attachments[]" class="form-control" multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                <div class="form-text">Up to 10 files, max 10MB each.</div>
            </div>

            {{-- 本文 --}}
            <div class="mb-3">
                <label class="form-label">Body <span class="text-danger">*</span></label>
                <textarea name="body" class="form-control" rows="8" required>{{ old('body') }}</textarea>
            </div>

            {{-- 期限 --}}
            <div class="mb-3">
                <label class="form-label">Expires At</label>
                <input type="datetime-local" name="expires_at"
                    value="{{ old('expires_at') }}"
                    class="form-control">
                <div class="form-text">Leave blank for no expiration. After the expiration date the message will be hidden from recipients (the sender can still view it).</div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">Send</button>
            </div>

        </div>
    </div>
</form>

@endsection