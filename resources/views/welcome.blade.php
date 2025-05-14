@extends('layouts.app')

@section('content')
    <!-- サイトの代表的なシンボル -->
    <div class="text-center my-5">
        <img src="https://yt3.googleusercontent.com/Lo1rKynYelN-szR7fPpkfXFZrEd5Xb6ceikW6SAw7gyhySwIAc9ZUKoM-v4tMrgYqEQlcdDglGA=w1707-fcrop64=1,00005a57ffffa5a8-k-c0xffffffff-no-nd-rj" alt="ミチログ" class="img-fluid img-thumbnail" style="max-width: auto; height: auto;">
        <h2 class="mt-3">ようこそ ミチログへ</h2>
        <p class="text-muted">ここでは音楽、映像、技術が交差します。</p>
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
        <h4><i class="fas fa-bolt text-warning"></i>ミチログ</h4>
        <p><i class="fas fa-music text-info"></i> 音楽 × <i class="fas fa-code text-success"></i> コード × <i class="fas fa-video text-danger"></i> 映像</p>
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
                    <h5 class="modal-title" id="ymjModalLabel">ミチログについて</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    ─ ミチログ ─<br>
                    音楽、コード、映像。<br>
                    表現するすべての手段で、<br>
                    未知の中から、自分だけの道をつくる。
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
@endsection