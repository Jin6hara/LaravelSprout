<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">ミチログ</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">ホーム</a></li>
                <li class="nav-item"><a class="nav-link" href="">紹介</a></li>
                <li class="nav-item"><a class="nav-link" href="">お問い合わせ</a></li>
                @auth
                    <li class="nav-item">
                        @if(Auth::user()->role === 'admin')
                            <a class="nav-link" href="{{ route('admin.dashboard') }}">管理者画面</a>
                        @else
                            <a class="nav-link" href="">マイページ</a>
                        @endif
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-link nav-link" type="submit">ログアウト</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">ログイン</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('register.showForm') }}">登録</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>