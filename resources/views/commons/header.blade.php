<style>
    /* 上下0 / 左右1px に変更 */
    #navbarNav {
        padding: 0 1px !important;
    }

    /* ベースライン起因の微妙な浮きを防ぐ（任意だが効きます） */
    .navbar-nav {
        margin-bottom: 0 !important;
        align-items: center;
    }

    .navbar .nav-item {
        display: flex;
        align-items: center;
    }

    .header-btn {
        line-height: 1;
        vertical-align: middle;
        margin: 0;
    }
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">Route Hub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
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