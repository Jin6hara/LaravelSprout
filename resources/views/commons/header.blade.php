<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">ミチログ</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="">ホーム</a></li>
                <li class="nav-item"><a class="nav-link" href="">紹介</a></li>
                <li class="nav-item"><a class="nav-link" href="">お問い合わせ</a></li>
                @auth
                    <li class="nav-item"><a class="nav-link" href="">マイページ</a></li>
                    <li class="nav-item">
                        <form method="POST" action="">
                            @csrf
                            <button class="btn btn-link nav-link" type="submit">ログアウト</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="">ログイン</a></li>
                    <li class="nav-item"><a class="nav-link" href="">登録</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>