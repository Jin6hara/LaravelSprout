{{-- PDFプレビューを表示するVueコンポーネント埋め込みの専用ページビュー --}}
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0;padding:0;background:#e5e7eb;">
    <div id="pdfPreview"
         data-type="{{ $type }}"
         data-query="{{ $queryString }}">
    </div>
</body>
</html>
