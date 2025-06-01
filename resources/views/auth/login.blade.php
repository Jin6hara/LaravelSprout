@extends('layouts.app')

@section('content')
<div class="card shadow-sm mx-auto" style="max-width: 500px;">
    <div class="card-body">
        <h2 class="card-title text-center mb-4">ログイン</h2>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">メールアドレス</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">パスワード</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                       required>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">ログイン</button>
            </div>
        </form>
    </div>
</div>
@endsection
