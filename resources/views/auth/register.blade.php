@extends('layouts.app')

@section('content')
<div class="card shadow-sm mx-auto" style="max-width: 500px;">
    <div class="card-body">
        <h2 class="card-title text-center mb-4">新規登録</h2>

        <form method="POST" action="{{ route('register.submit') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">名前</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name') }}">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">メールアドレス</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">社員番号</label>
                <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror"
                    value="{{ old('employee_code') }}">
                @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>


            <div class="mb-3">
                <label class="form-label">パスワード</label>
                <input type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">パスワード（確認）</label>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
            </div>

            <div class="mb-4">
                <label class="form-label">性別</label>
                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="unknown" {{ old('gender') == 'unknown' ? 'selected' : '' }}>未選択</option>
                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>男性</option>
                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>女性</option>
                    <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>その他</option>
                </select>
                @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            
            <div class="mb-3">
                <label class="form-label">電話番号（任意）</label>
                <input type="text" name="phone_number"
                    class="form-control @error('phone_number') is-invalid @enderror"
                    value="{{ old('phone_number') }}">
                @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">住所（任意）</label>
                <input type="text" name="address"
                    class="form-control @error('address') is-invalid @enderror"
                    value="{{ old('address') }}">
                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">入社日（start_date）</label>
                <input type="date" name="start_date"
                    class="form-control @error('start_date') is-invalid @enderror"
                    value="{{ old('start_date') }}">
                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">退職日（任意）</label>
                <input type="date" name="end_date"
                    class="form-control @error('end_date') is-invalid @enderror"
                    value="{{ old('end_date') }}">
                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">備考（任意）</label>
                <input type="text" name="note"
                    class="form-control @error('note') is-invalid @enderror"
                    value="{{ old('note') }}">
                @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i> 登録
                </button>
            </div>
        </form>
    </div>
</div>
@endsection