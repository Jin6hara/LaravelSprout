<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <title>ルートハブ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <script defer src="https://use.fontawesome.com/releases/v6.5.0/js/all.js"></script>

    {{-- ★ ここで Vite 経由で app.js を読み込む --}}
    @vite('resources/js/app.js')

    @stack('styles')
</head>

<body>
    <div id="app"> {{-- ★ Vue の root 要素 --}}

        @include('commons.header')

        <div class="container mt-4">
            @include('commons.messages')
            @yield('content')
        </div>

        @include('commons.footer')

    </div>
    <!-- JS Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <div id="toastFlash"
        data-toast='@json(session("toast"))'
        data-errors='@json(session("toast_errors"))'>
    </div>

    @stack('scripts')
</body>

</html>