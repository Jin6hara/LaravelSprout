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

<style>
    /* ✅ トーストの重なり順を少し上げる（モーダル等より下にしたい場合は調整） */
    .toast-container {
        z-index: 1080;
    }
</style>

<!-- ✅ トーストの土台（右上） -->
<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
    <div id="appToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="9000">
        <div class="d-flex">
            <div class="toast-body" id="appToastBody">Saved.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    <!-- 追加で複数並べる場合はここに複製してもOK -->
    <!-- 今回は 1 つを使い回す実装 -->
</div>

<script>
    // ✅ showToast 実装（Bootstrap 5 Toast 使用）
    window.showToast = function(message, {
        variant = 'dark',
        delay = 5000
    } = {}) {
        const toastEl = document.getElementById('appToast');
        const bodyEl = document.getElementById('appToastBody');
        if (!toastEl || !bodyEl) return;

        // カラー（bg- クラス）切替
        toastEl.classList.remove('bg-dark', 'bg-success', 'bg-danger', 'bg-primary', 'bg-warning', 'bg-info');
        toastEl.classList.add('bg-' + variant);

        // メッセージ
        bodyEl.textContent = message || '';

        // 遅延時間
        toastEl.setAttribute('data-bs-delay', String(delay));

        // 表示
        let toast = bootstrap.Toast.getInstance(toastEl);
        if (!toast) toast = new bootstrap.Toast(toastEl);
        toast.show();
    };

    // ✅ サーバからのトースト（セッションフラッシュ）を起動
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('toast'))
        showToast(@json(session('toast')), {
            variant: 'success',
            delay: 5000
        });
        @endif
    });
</script>

</html>