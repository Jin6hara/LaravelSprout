@extends('layouts.app')

@section('content')
<div class="container">
    <h2>承認依頼の詳細</h2>

    <div class="card mt-3">
        <div class="card-body">
            {{-- 承認リクエストの概要 --}}
            <table class="table table-bordered">
                <tr>
                    <th>タイトル</th>
                    <td>{{ $approvalRequest->title }}</td>
                </tr>
                <tr>
                    <th>依頼者</th>
                    <td>{{ $approvalRequest->requester->name }}</td>
                </tr>
                <tr>
                    <th>状態</th>
                    <td>
                        @if($approvalRequest->current_state === 'pending')
                            <span class="badge bg-warning">承認待ち</span>
                        @elseif($approvalRequest->current_state === 'approved')
                            <span class="badge bg-success">承認済み</span>
                        @elseif($approvalRequest->current_state === 'denied')
                            <span class="badge bg-danger">却下</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>対象ユーザー</th>
                    <td>
                        {{-- metadata からユーザー名を表示 --}}
                        {{ $approvalRequest->metadata['target_user_name'] ?? '不明' }}
                    </td>
                </tr>
                <tr>
                    <th>変更後ロール</th>
                    <td>
                        {{ $approvalRequest->metadata['requested_role'] ?? '-' }}
                    </td>
                </tr>
                <tr>
                    <th>理由</th>
                    <td>{{ $approvalRequest->metadata['reason'] ?? '-' }}</td>
                </tr>
            </table>

            {{-- 過去のアクション履歴 --}}
            <h5 class="mt-4">アクション履歴</h5>
            <ul class="list-group mb-4">
                @forelse ($approvalRequest->actions as $action)
                    <li class="list-group-item">
                        <strong>{{ $action->actor->name }}</strong>
                        が <span class="text-primary">{{ $action->action }}</span>
                        （{{ $action->created_at->format('Y-m-d H:i') }}）
                        <br>
                        コメント: {{ $action->comment ?? '（なし）' }}
                    </li>
                @empty
                    <li class="list-group-item">まだ承認/却下の履歴はありません。</li>
                @endforelse
            </ul>

            {{-- 承認・却下ボタン（権限がある場合のみ） --}}
            @can('act', $approvalRequest)
                @if($approvalRequest->current_state === 'pending')
                    <form action="{{ route('approvals.approve', $approvalRequest) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="text" name="comment" class="form-control mb-2" placeholder="承認コメント（任意）">
                        <button type="submit" class="btn btn-success mb-2">承認する</button>
                    </form>

                    <form action="{{ route('approvals.deny', $approvalRequest) }}" method="POST" class="d-inline ms-2">
                        @csrf
                        <input type="text" name="comment" class="form-control mb-2" placeholder="却下理由（任意）">
                        <button type="submit" class="btn btn-danger mb-2">却下する</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</div>
@endsection
