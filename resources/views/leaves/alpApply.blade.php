{{-- resources/views/leaves/alpApply.blade.php --}}
@extends('layouts.app')

@section('content')
@if(isset($fy))
<div class="mb-3 p-2 rounded bg-light">
    FY: {{ $fy }} / 残有給: <strong>{{ number_format($remaining, 2) }}</strong> 日
</div>
@endif
<div class="container max-w-xl mx-auto">
    <h1 class="text-lg font-bold mb-4">有給申請</h1>

    <form method="post" action="{{ $action }}">
        @csrf

        @isset($targetUser)
        <div class="mb-3">
            <label>対象ユーザー</label>
            <input type="text" class="form-control" value="{{ $targetUser->name }} (ID:{{ $targetUser->id }})" readonly>
            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
        </div>
        @else
        <input type="hidden" name="user_id" value="{{ auth()->id() }}">
        @endisset

        <div class="mb-3">
            <label>開始日</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required>
            @error('start_date')<div class="text-danger">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label>終了日（任意）</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
            @error('end_date')<div class="text-danger">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label>理由（任意）</label>
            <textarea name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
        </div>

        <button class="btn btn-primary">申請する</button>
    </form>
</div>
@endsection