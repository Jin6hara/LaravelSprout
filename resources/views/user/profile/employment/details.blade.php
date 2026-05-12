@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">

        {{-- ページヘッダー --}}
        <div class="d-flex align-items-center mb-3">
            <a href="{{ route('admin.user.profile', $user) }}" class="btn btn-outline-secondary btn-sm me-3">
                &larr; プロフィールに戻る
            </a>
            <h4 class="mb-0">{{ $user->name }}さんの雇用履歴</h4>
        </div>

        {{-- ステータスメッセージ --}}
        @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- 雇用期間ごとのカード --}}
        @forelse($employmentTerms as $term)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <strong>{{ $term->type_name }}</strong>
                    <span class="text-muted ms-2">（{{ $term->type_code }}）</span>
                    &nbsp;
                    {{ $term->start_date?->format('Y/m/d') ?? '—' }}
                    〜
                    {{ $term->end_date?->format('Y/m/d') ?? '現在' }}
                </span>
                <a href="{{ route('employment_terms.edit', $term) }}" class="btn btn-sm btn-outline-primary">
                    編集
                </a>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-sm mb-0">
                    <colgroup>
                        <col style="width:130px">
                        <col>
                    </colgroup>
                    <tr>
                        <th>雇用形態</th>
                        <td>{{ $term->type_name }}（{{ $term->type_code }}）</td>
                    </tr>
                    <tr>
                        <th>開始日</th>
                        <td>{{ $term->start_date?->format('Y/m/d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>終了日</th>
                        <td>{{ $term->end_date?->format('Y/m/d') ?? '—（継続中）' }}</td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>{{ $term->note ?? '—' }}</td>
                    </tr>
                </table>

                {{-- 休職期間 --}}
                @if($term->leavePeriods->isNotEmpty())
                <h6 class="mt-3 mb-2">休職期間</h6>
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>開始日</th>
                            <th>終了日</th>
                            <th>理由</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($term->leavePeriods as $leave)
                        <tr>
                            <td>{{ $leave->start_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $leave->end_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $leave->reason ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <p class="text-muted mt-2 mb-0 small">休職記録なし</p>
                @endif
            </div>
        </div>
        @empty
        <div class="alert alert-info">雇用記録がありません。</div>
        @endforelse

    </div>
</div>
@endsection
