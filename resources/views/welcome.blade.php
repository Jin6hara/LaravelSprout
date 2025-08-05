@extends('layouts.app')

@section('content')
<!-- サイトの代表的なシンボル -->
<div class="text-center my-5">
    <img src="{{ asset('image/RouteHub1.png') }}" alt="ミチログ" class="img-fluid img-thumbnail" style="max-width: auto; height: auto;">
    <h2 class="mt-3">ようこそルートハブへ</h2>
    <p class="text-muted">講師と本部をつなぐ、交通費と社内連携のポータルです。</p>
</div>

<!-- トピック一覧 -->
<div class="row row-cols-1 row-cols-md-2 g-4">
    {{-- @foreach ($topics as $topic)
            <div class="col">
                <div class="card h-100 shadow-sm">
                    @if ($topic['type'] === 'image')
                        <img src="{{ $topic['media'] }}" class="card-img-top" alt="Image for {{ $topic['title'] }}">
    @elseif ($topic['type'] === 'video')
    <div class="ratio ratio-16x9">
        <video controls>
            <source src="{{ $topic['media'] }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
    @elseif ($topic['type'] === 'audio')
    <div class="p-3">
        <audio controls class="w-100">
            <source src="{{ $topic['media'] }}" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>
    </div>
    @endif

    <div class="card-body">
        <h5 class="card-title">{{ $topic['title'] }}</h5>
        <p class="card-text">{{ $topic['description'] }}</p>
    </div>
</div>
</div>
@endforeach --}}
</div>

<!-- FontAwesome を使用した追加セクション -->
<div class="text-center mt-5">
    <h4><i class="fas fa-location-dot text-danger"></i>ルートハブ</h4>
    <p>
        <i class="fas fa-file-alt text-primary"></i> 精算 ×
        <i class="fas fa-compass text-success"></i> 管理 ×
        <i class="fas fa-envelope text-warning"></i> 通知
    </p>
</div>

<!-- Bootstrap JS機能（モーダル）を使用 -->
<div class="text-center mt-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ymjModal">
        <i class="fas fa-info-circle"></i> もっと知る
    </button>
</div>

<!-- モーダル本体 -->
<div class="modal fade" id="ymjModal" tabindex="-1" aria-labelledby="ymjModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ymjModalLabel">ルートハブについて</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                ─ ルートハブ ─ <br>
                <br>
                交通費と社内交流。<br>
                仕組みで支え、つながりを築く。<br>
                <br>
                働きやすさの先に、<br>
                新しいチームのカタチをつくる。
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>
@endsection