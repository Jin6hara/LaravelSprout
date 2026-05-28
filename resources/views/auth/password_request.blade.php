@extends('layouts.app')

@section('content')
<div class="card shadow-sm mx-auto" style="max-width: 500px;">
    <div class="card-body">
        <h2 class="card-title text-center mb-4">Reset Password</h2>

        <form method="POST" action="{{ route('password.reset.email') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Email or Employee Code</label>
                <input type="text" name="login" class="form-control @error('login') is-invalid @enderror"
                    value="{{ old('login') }}" autocomplete="username" required autofocus>
                @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Send Reset Email</button>
            </div>
        </form>
    </div>
</div>
@endsection
