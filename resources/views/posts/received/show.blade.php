{{-- resources/views/posts/received/show.blade.php --}}
@extends('layouts.app')

@section('content')
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
            {{-- 自分宛かつ未確認なら Confirm ボタン表示 --}}
            @php($pv = $post->viewers->first()?->pivot)
            @if(auth()->id() === $target->id && is_null($pv?->confirmed_at))
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

{{-- 管理者向け：宛先と確認状況 --}}
@isset($recipients)
<div class="card">
    <div class="card-header">Recipients and Confirmation Status (Admin)</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Employee Code</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Confirmed At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recipients as $u)
                @php($cAt = $u->pivot?->confirmed_at)
                <tr>
                    <td>{{ $u->family_name }} {{ $u->first_name }}</td>
                    <td>{{ $u->employee_code }}</td>
                    <td>{{ $u->email }}</td>
                    <td>
                        @if($cAt)
                        <span class="badge text-bg-success d-inline-block text-center" style="min-width:90px;">Confirmed</span>
                        @else
                        <span class="badge text-bg-warning d-inline-block text-center" style="min-width:90px;">Unconfirmed</span>
                        @endif
                    </td>
                    <td>{{ $cAt ? \Carbon\Carbon::parse($cAt)->format('Y-m-d H:i') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endisset
@endsection