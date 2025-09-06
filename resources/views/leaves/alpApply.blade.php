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

        @php
        $oldDates = old('dates', []);
        if (empty($oldDates)) { $oldDates = ['']; }
        @endphp
        <div class="mb-3">
            <label class="form-label">申請日（複数可）</label>
            <div id="dates-wrapper">
                @foreach($oldDates as $i => $d)
                <div class="date-row d-flex align-items-start gap-2 mb-2">
                    <input type="date" name="dates[]" class="form-control" value="{{ $d }}" required>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-date" {{ $i === 0 && count($oldDates) === 1 ? 'disabled' : '' }}>削除</button>
                </div>
                @error("dates.$i")<div class="text-danger mb-2">{{ $message }}</div>@enderror
                @endforeach
            </div>
            <button type="button" id="add-date" class="btn btn-outline-secondary btn-sm">申請日を追加</button>
            @error('dates')<div class="text-danger mt-2">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label>理由（任意）</label>
            <textarea name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
            @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
        </div>

        <button class="btn btn-primary">申請する</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const datesWrapper = document.getElementById('dates-wrapper');
        const addBtn = document.getElementById('add-date');

        addBtn.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'date-row d-flex align-items-start gap-2 mb-2';
            row.innerHTML = `
            <input type="date" name="dates[]" class="form-control" required>
            <button type="button" class="btn btn-outline-danger btn-sm remove-date">削除</button>
        `;
            datesWrapper.appendChild(row);
            updateRemoveButtons();
        });

        datesWrapper.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-date')) {
                const rows = datesWrapper.querySelectorAll('.date-row');
                if (rows.length > 1) {
                    e.target.closest('.date-row').remove();
                    updateRemoveButtons();
                }
            }
        });

        function updateRemoveButtons() {
            const rows = datesWrapper.querySelectorAll('.date-row');
            rows.forEach(row => {
                const btn = row.querySelector('.remove-date');
                btn.disabled = rows.length === 1;
            });
        }
        updateRemoveButtons();
    });
</script>
@endsection