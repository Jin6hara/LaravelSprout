@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-4">
    <h2 class="mb-0">CSV Management</h2>
</div>

<div class="row g-4">
    {{-- Lesson CSV --}}
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">
                    <i class="fas fa-book me-2 text-primary"></i>Lesson CSV
                </h5>
                <p class="card-text text-muted flex-grow-1">
                    lessons テーブルの全件を CSV でエクスポート、または CSV ファイルからインポートします。
                </p>
                <a href="{{ route('csv.lessons.show') }}" class="btn btn-outline-primary mt-2">
                    Export / Import
                </a>
            </div>
        </div>
    </div>

    {{-- 今後ここに他テーブルの CSV カードを追加 --}}
</div>
@endsection
