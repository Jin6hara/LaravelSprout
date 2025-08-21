<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">ルートハブ</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                @auth
                <a class="nav-link" href="{{ auth()->user()->isAdmin() 
                ? route('admin.user.profile', ['user' => auth()->user()->employee_code]) 
                : route('user.profile') }}">
                マイページ
                </a>
                <li class="nav-item"><a class="nav-link" href="">交通費精算</a></li>
                <li class="nav-item"><a class="nav-link" href="">お問い合わせ</a></li>
                <li class="nav-item">
                @role('admin|super_admin')
                <a class="nav-link" href="{{ route('admin.dashboard') }}">管理者画面</a>
                <li class="nav-item">
                    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                    <a href="{{ route('notifications.index') }}" class="nav-link position-relative">
                        通知
                        <i class="bi bi-bell"></i>
                        @if($unread > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            {{ $unread }}
                        </span>
                        @endif
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('register.showForm') }}">新規登録</a></li>
                @endrole
                </li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-link nav-link" type="submit">ログアウト</button>
                    </form>
                </li>
                @else
                <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">ログインしてください</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>