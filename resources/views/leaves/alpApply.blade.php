{{-- 有給休暇（ALP）の申請フォームを表示するビュー --}}
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

    {{-- ★ 添付を送るので enctype 追加 --}}
    <form method="post" action="{{ $action }}" enctype="multipart/form-data">
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

        {{-- ★ 申請タイプ --}}
        <div class="mb-3">
            <label class="form-label">申請タイプ</label>
            <select name="type" id="leave-type" class="form-select">
                <option value="paid" {{ old('type', 'paid') === 'paid' ? 'selected' : '' }}>有給（ALP）</option>
                <option value="special" {{ old('type') === 'special' ? 'selected' : '' }}>特別休暇</option>
            </select>
            @error('type')<div class="text-danger mt-1">{{ $message }}</div>@enderror
        </div>

        @php
        $oldDates = old('dates', []);
        if (empty($oldDates)) { $oldDates = ['']; }
        @endphp

        {{-- ★ 有給（paid）のときの日付入力（複数日） --}}
        <div class="mb-3" id="dates-block">
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

        {{-- ★ SpecialLeave のときは期間で指定 --}}
        <div class="mb-3" id="special-range-block" style="display:none;">
            <label class="form-label">期間（特別休暇）</label>
            <div class="d-flex align-items-center gap-2">
                <input type="date" id="special-start" class="form-control">
                <span class="mt-2">〜</span>
                <input type="date" id="special-end" class="form-control">
            </div>
            <div class="form-text mt-1">
                開始日と終了日を入力すると、その期間の各日が自動的に申請対象（日付リスト）になります。
            </div>
        </div>

        {{-- ★ special 用の項目（type=special のときだけ有効） --}}
        <div class="mb-3" id="special-fields">
            <label class="form-label">特別休暇の種類</label>
            <select name="special_type" class="form-select">
                <option value="">— Select type —</option>
                <option value="Wedding" {{ old('special_type') === 'Wedding' ? 'selected' : '' }}>Wedding</option>
                <option value="Bereavement" {{ old('special_type') === 'Bereavement' ? 'selected' : '' }}>Bereavement</option>
                <option value="Family Wedding" {{ old('special_type') === 'Family Wedding' ? 'selected' : '' }}>Family Wedding</option>
                <option value="Other" {{ old('special_type') === 'Other' ? 'selected' : '' }}>Other</option>
            </select>
            @error('special_type')<div class="text-danger mt-1">{{ $message }}</div>@enderror

            <label class="form-label mt-2">証明書（添付必須）</label>
            <input type="file" name="attachment" class="form-control">
            @error('attachment')<div class="text-danger mt-1">{{ $message }}</div>@enderror
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

        const typeSel = document.getElementById('leave-type');
        const datesBlock = document.getElementById('dates-block');
        const specialRangeBlock = document.getElementById('special-range-block');
        const specialFields = document.getElementById('special-fields');
        const specialTypeInput = document.querySelector('select[name="special_type"]');
        const attachmentInput = document.querySelector('input[name="attachment"]');
        const specialStart = document.getElementById('special-start');
        const specialEnd = document.getElementById('special-end');

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

        // ★ special の期間から dates[] を自動生成（裏側で送信）
        function generateDatesFromRange() {
            if (!specialStart || !specialEnd) return;
            const startVal = specialStart.value;
            const endVal = specialEnd.value;
            if (!startVal || !endVal || startVal > endVal) {
                return;
            }

            // dates[] をいったんクリアして、期間内の全日を hidden 的にセット
            datesWrapper.innerHTML = '';

            let current = new Date(startVal);
            const end = new Date(endVal);

            while (current <= end) {
                const ymd = current.toISOString().slice(0, 10);
                const row = document.createElement('div');
                row.className = 'date-row d-none';
                row.innerHTML = `<input type="date" name="dates[]" class="form-control" value="${ymd}" required>`;
                datesWrapper.appendChild(row);
                current.setDate(current.getDate() + 1);
            }
        }

        // ★ old('dates') がある場合、special の時は範囲に戻す
        function initSpecialRangeFromExistingDates() {
            if (!datesWrapper || !specialStart || !specialEnd) return;
            const inputs = datesWrapper.querySelectorAll('input[name="dates[]"]');
            if (!inputs.length) return;

            const values = Array.from(inputs)
                .map(el => el.value)
                .filter(Boolean)
                .sort();

            if (!values.length) return;

            if (!specialStart.value) specialStart.value = values[0];
            if (!specialEnd.value) specialEnd.value = values[values.length - 1];
        }

        // ★ special 選択時だけフィールドを有効＆必須にする
        function updateSpecialFields() {
            if (!typeSel) return;
            const isSpecial = typeSel.value === 'special';

            if (specialFields) {
                specialFields.style.display = isSpecial ? '' : 'none';
            }
            if (datesBlock) {
                // paid のときだけ日付リストを表示
                datesBlock.style.display = isSpecial ? 'none' : '';
            }
            if (specialRangeBlock) {
                // special のときだけ期間入力を表示
                specialRangeBlock.style.display = isSpecial ? '' : 'none';
            }

            if (specialTypeInput) specialTypeInput.required = isSpecial;
            if (attachmentInput) attachmentInput.required = isSpecial;

            if (specialStart) specialStart.required = isSpecial;
            if (specialEnd) specialEnd.required = isSpecial;

            if (isSpecial) {
                initSpecialRangeFromExistingDates();
                generateDatesFromRange();
            }
        }

        if (specialStart) {
            specialStart.addEventListener('change', generateDatesFromRange);
        }
        if (specialEnd) {
            specialEnd.addEventListener('change', generateDatesFromRange);
        }

        if (typeSel) {
            typeSel.addEventListener('change', updateSpecialFields);
            updateSpecialFields(); // 初期表示（old()対応）
        }

        updateRemoveButtons();
    });
</script>
@endsection