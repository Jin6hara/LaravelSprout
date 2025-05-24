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
                <label class="form-label">パスワード</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">パスワード（確認）</label>
                <input type="password" name="password_confirmation" class="form-control">
            </div>

            <div class="mb-4">
                <label class="form-label">性別</label>
                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>男性</option>
                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>女性</option>
                    <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>その他</option>
                    <option value="unknown" {{ old('gender') == 'unknown' ? 'selected' : '' }}>不明</option>
                </select>
                @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
