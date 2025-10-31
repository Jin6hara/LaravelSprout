{{-- resources/views/leaves/create.blade.php --}}
@extends('layouts.app')
@section('content')
<div class="container max-w-xl mx-auto">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <h1 class="text-lg font-bold mb-4">Leave 新規</h1>
    <form method="post" action="{{ route('leaves.store') }}">
        @csrf
        <div class="mb-3">
            <label>対象ユーザー</label>
            <input type="number" name="user_id" class="form-control" value="{{ old('user_id',$defaultUserId) }}" required>
            @error('user_id')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
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
            <label>種別</label>
            <select name="kind" class="form-control" required>
                @foreach(['paid','special','absence','late','other'] as $k)
                <option value="{{ $k }}" @selected(old('kind')===$k)>{{ $k }}</option>
                @endforeach
            </select>
            @error('kind')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label>ステータス</label>
            <select name="status" class="form-control">
                @foreach(['approved','pending','rejected'] as $s)
                <option value="{{ $s }}" @selected(old('status','approved')===$s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>理由（任意）</label>
            <textarea name="reason" class="form-control">{{ old('reason') }}</textarea>
        </div>
        <button class="btn btn-primary">保存</button>
    </form>
</div>
@endsection