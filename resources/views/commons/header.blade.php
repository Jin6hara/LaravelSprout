<link rel="stylesheet" href="{{ asset('css/header.css') }}?v={{ filemtime(public_path('css/header.css')) }}">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">Route Hub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">

            {{-- 左: scope selector (admin / super_admin のみ) --}}
            @auth
            @if(auth()->user()->isAdmin() && $scopeSelectorData)
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    @include('commons.scope_selector')
                </li>
            </ul>
            @endif
            @endauth

            {{-- 右: 既存ナビアイテム（変更なし） --}}
            <ul class="navbar-nav ms-auto">
                @auth
                @include('commons.role.adminHeader')
                @include('commons.role.generalHeader')
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-link nav-link" type="submit">Log Out</button>
                    </form>
                </li>
                @else
                <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Please Login</a></li>
                @endauth
            </ul>

        </div>
    </div>
</nav>