@extends('layouts.app')

@section('content')
<div class="card shadow-sm mx-auto" style="max-width: 500px;">
    <div class="card-body">

        @if(Auth::check())
        <div class="alert alert-warning">
            You are currently logged in as "{{ Auth::user()->email }}".<br>
            Signing in with another account will switch users.
        </div>
        @endif


        <h2 class="card-title text-center mb-4">Login</h2>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Email or Employee Code</label>
                <input type="text" name="login" class="form-control @error('login') is-invalid @enderror"
                    value="{{ old('login') }}" autocomplete="username" required autofocus>
                @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                    required>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('password.request') }}">Forgot your password?</a>
            </div>
        </form>
    </div>
</div>

<!-- Reload when returning with the browser back button. -->
<script>
    window.addEventListener("pageshow", function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

@endsection
