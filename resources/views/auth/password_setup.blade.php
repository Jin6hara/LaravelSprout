@extends('layouts.app')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
@php
    $isInvite = $setupToken->purpose === \App\Models\PasswordSetupToken::PURPOSE_INVITE;
@endphp

<div class="card shadow-sm mx-auto" style="max-width: 500px;">
    <div class="card-body">
        <h2 class="card-title text-center mb-4">
            {{ $isInvite ? 'Create Password' : 'Reset Password' }}
        </h2>

        <form method="POST" action="{{ route('password.setup.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" autocomplete="new-password"
                    class="form-control @error('password') is-invalid @enderror" required autofocus>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                    class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    {{ $isInvite ? 'Create Password' : 'Update Password' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
