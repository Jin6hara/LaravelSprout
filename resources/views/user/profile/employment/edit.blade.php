{{-- ユーザーの雇用情報（雇用形態・期間等）を編集するフォームビュー --}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">

        {{-- ページヘッダー --}}
        <div class="d-flex align-items-center mb-3">
            <a href="{{ route('employment_terms.details', $user) }}" class="btn btn-outline-secondary btn-sm me-3">
                &larr; 雇用履歴に戻る
            </a>
            <h4 class="mb-0">雇用情報の編集</h4>
        </div>

        {{-- ユーザー名表示 --}}
        <p class="text-muted">対象：{{ $user->name }}（{{ $user->employee_code }}）</p>

        {{-- バリデーションエラー --}}
        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- 編集フォーム --}}
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('employment_terms.update', $employmentTerm) }}">
                    @csrf
                    @method('PUT')

                    {{-- 雇用形態コード --}}
                    <div class="mb-3">
                        <label for="type_code" class="form-label">雇用形態コード <span class="text-danger">*</span></label>
                        <input type="text"
                               id="type_code"
                               name="type_code"
                               class="form-control @error('type_code') is-invalid @enderror"
                               value="{{ old('type_code', $employmentTerm->type_code) }}"
                               required
                               maxlength="50"
                               placeholder="例：full_time, part_time, contract">
                        @error('type_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 雇用形態名 --}}
                    <div class="mb-3">
                        <label for="type_name" class="form-label">雇用形態名 <span class="text-danger">*</span></label>
                        <input type="text"
                               id="type_name"
                               name="type_name"
                               class="form-control @error('type_name') is-invalid @enderror"
                               value="{{ old('type_name', $employmentTerm->type_name) }}"
                               required
                               maxlength="100"
                               placeholder="例：正社員、契約社員、パートタイム">
                        @error('type_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 開始日 --}}
                    <div class="mb-3">
                        <label for="start_date" class="form-label">開始日 <span class="text-danger">*</span></label>
                        <input type="date"
                               id="start_date"
                               name="start_date"
                               class="form-control @error('start_date') is-invalid @enderror"
                               value="{{ old('start_date', $employmentTerm->start_date?->format('Y-m-d')) }}"
                               required>
                        @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 終了日 --}}
                    <div class="mb-3">
                        <label for="end_date" class="form-label">終了日</label>
                        <input type="date"
                               id="end_date"
                               name="end_date"
                               class="form-control @error('end_date') is-invalid @enderror"
                               value="{{ old('end_date', $employmentTerm->end_date?->format('Y-m-d')) }}">
                        <div class="form-text">空白の場合は継続中（無期限）として扱います。</div>
                        @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 備考 --}}
                    <div class="mb-3">
                        <label for="note" class="form-label">備考</label>
                        <input type="text"
                               id="note"
                               name="note"
                               class="form-control @error('note') is-invalid @enderror"
                               value="{{ old('note', $employmentTerm->note) }}"
                               maxlength="255">
                        @error('note')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ボタン --}}
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <a href="{{ route('employment_terms.details', $user) }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
@endsection
