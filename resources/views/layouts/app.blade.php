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
    @stack('styles')
</head>

<body>
    @include('commons.header')

    <div class="container mt-4">
        @include('commons.messages')
        @yield('content')
    </div>

    @include('commons.footer')

    <!-- JS Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>