{{-- resources/views/posts/received/index.blade.php --}}
@extends('layouts.app')

@section('content')
<example-component></example-component> {{-- ★ テスト用 Vue コンポーネント --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Inbox</h2>

    {{-- admin以上は代理検索フォーム表示 --}}
    @if(auth()->user()->hasAnyRole(['admin','super_admin']))
    <form method="GET" action="{{ route('messages.index') }}" class="d-flex gap-2">
        <input type="text" name="employee_code" value="{{ request('employee_code') }}"
            class="form-control form-control-sm" placeholder="Search by Employee Code">
        <button class="btn btn-outline-secondary btn-sm" type="submit">Search</button>
    </form>
    @endif
</div>

{{-- 代理閲覧バッジ（検索仕様） --}}
@if($isProxy)
<span class="badge text-bg-info">
    Admin View: {{ $target->first_name }} {{ $target->family_name }} [{{ $target->employee_code }}]
</span>
@endif

<div class="card mt-2">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 120px;">Date</th>
                    <th>Title</th>
                    <th style="width: 220px;">Sender</th>
                    <th style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $p)
                @php
                // 対象pivotは with で1件のみ読み込んでいる想定
                $pv = $p->viewers->first()?->pivot;
                $confirmed = !is_null($pv?->confirmed_at);
                @endphp
                <tr onclick="window.location='{{ route('messages.show', array_merge([$p], request()->only('employee_code'))) }}'" style="cursor:pointer;">
                    <td>{{ $p->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $p->title ?? '((No Title))' }}</td>
                    <td>
                        {{ $p->author->family_name }} {{ $p->author->first_name }}
                        <span class="text-muted">[{{ $p->author->employee_code }}]</span>
                    </td>
                    <td>
                        @if($confirmed)
                        <span class="badge text-bg-success d-inline-block text-center" style="min-width:90px;">Confirmed</span>
                        @else
                        <span class="badge text-bg-warning d-inline-block text-center" style="min-width:90px;">Unconfirmed</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No Messages</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-body py-2">
        {{ $posts->links() }}
    </div>
</div>
@endsection