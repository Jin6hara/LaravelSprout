@extends('layouts.app')

@section('content')
<div class="container">
    <h2>承認依頼の詳細</h2>

    <div class="card mt-3">
        <div class="card-body">
            @php
            $meta       = $approvalRequest->metadata ?? [];
            $approvable = $approvalRequest->approvable;
            $isLeave    = $approvable instanceof \App\Models\Leave;
            @endphp

            <table class="table table-bordered">
                <tr>
                    <th>タイトル</th>
                    <td>{{ $approvalRequest->title }}</td>
                </tr>
                <tr>
                    <th>依頼者</th>
                    <td>{{ $approvalRequest->requester->name }} [{{ $approvalRequest->requester->employee_code }}]</td>
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

                @if($isLeave)
                {{-- 有給/特別休暇申請の場合 --}}
                <tr>
                    <th>申請種別</th>
                    <td>
                        @php
                        $kind = $meta['kind'] ?? $approvable->kind ?? null;
                        $kindLabel = match($kind) {
                        'paid' => '有給（ALP）',
                        'special' => '特別休暇',
                        default => $kind,
                        };
                        @endphp
                        {{ $kindLabel ?? '-' }}
                    </td>
                </tr>
                @if(($meta['kind'] ?? null) === 'special' || ($approvable->kind ?? null) === 'special')
                <tr>
                    <th>特別休暇の種類</th>
                    <td>{{ $meta['special_type'] ?? $approvable->special_type ?? '-' }}</td>
                </tr>
                <tr>
                    <th>添付</th>
                    <td>
                        @if($approvable->attachment)
                        <a href="{{ route('leaves.attachments.show', ['leave' => $approvable->id, 'attachment' => $approvable->attachment->id]) }}" target="_blank">
                            {{ $approvable->attachment->original_name ?? '添付ファイルを開く' }}
                        </a>
                        @if($approvable->attachment->size)
                        <small>　{{ number_format($approvable->attachment->size / 1024, 1) }} KB</small>
                        @endif
                        @else
                        -
                        @endif
                    </td>
                </tr>
                @endif
                <tr>
                    <th>対象日</th>
                    <td>{{ $dateSummary ?? ($meta['date'] ?? optional($approvable->start_date)->format('Y-m-d') ?? '-') }}</td>
                </tr>
                <tr>
                    <th>理由</th>
                    <td>{{ $meta['reason'] ?? ($approvable->reason ?? '-') }}</td>
                </tr>

                @else
                {{-- 権限変更申請の場合 --}}
                <tr>
                    <th>対象ユーザー</th>
                    <td>{{ $meta['target_user_name'] ?? '不明' }}</td>
                </tr>
                <tr>
                    <th>権限</th>
                    <td>
                        @php
                        $roleLabels = ['general' => '一般', 'admin' => '管理者', 'super_admin' => 'スーパー管理者'];
                        @endphp
                        {{ $roleLabels[$meta['current_role'] ?? ''] ?? ($meta['current_role'] ?? '—') }}
                        →
                        <strong>{{ $roleLabels[$meta['requested_role'] ?? ''] ?? ($meta['requested_role'] ?? '—') }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>所属地区</th>
                    <td>
                        {{ $meta['current_district'] ?? '—' }}
                        →
                        <strong>{{ $meta['requested_district'] ?? '—' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>所属部署</th>
                    <td>
                        {{ $meta['current_department'] ?? '—' }}
                        →
                        <strong>{{ $meta['requested_department'] ?? '—' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>管理範囲</th>
                    <td>
                        <div class="d-flex gap-4">
                            <div>
                                <div class="text-muted small mb-1">現在</div>
                                @php $currentScopes = $meta['current_scopes'] ?? []; @endphp
                                @if(empty($currentScopes))
                                    <span class="text-muted">—</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($currentScopes as $s)
                                        <li>{{ $s['district'] ?? '—' }} ／ {{ $s['department'] ?? '—' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="text-muted align-self-center">→</div>
                            <div>
                                <div class="text-muted small mb-1">変更後</div>
                                @php $requestedScopes = $meta['requested_scopes'] ?? []; @endphp
                                @if(empty($requestedScopes))
                                    <span class="text-muted">—</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($requestedScopes as $s)
                                        <li><strong>{{ $s['district'] ?? '—' }} ／ {{ $s['department'] ?? '—' }}</strong></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>申請理由</th>
                    <td>{{ $meta['reason'] ?? '—' }}</td>
                </tr>
                @endif
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
