{{-- resources/views/schools/search.blade.php --}}
@extends('layouts.app')

@section('title', 'School Search')

@push('styles')
<style>
    .school-card .map {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-radius: .5rem;
        background: #f2f4f7;
    }

    .station-chip {
        display: inline-block;
        padding: .15rem .5rem;
        border: 1px solid #e5e7eb;
        border-radius: 9999px;
        font-size: .8rem;
        margin: 0 .25rem .25rem 0;
        background: #fff;
    }

    .guide-text {
        white-space: pre-wrap;
        font-size: .9rem;
        color: #4b5563;
    }
</style>
@endpush

@section('content')
<div class="container my-3" style="max-width:1100px;">
    {{-- 検索フォーム --}}
    <form method="GET" action="{{ route('schools.search') }}" class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-lg-5">
                    <label class="form-label small mb-0">Keyword</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ $q }}" placeholder="Name / Code / Kana / Alias ...">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Per page</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach([6,12,24,48] as $n)
                        <option value="{{ $n }}" @selected(request('per_page',12)==$n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <button class="btn btn-sm btn-primary w-100">Search</button>
                </div>
            </div>
        </div>
    </form>

    {{-- 結果 --}}
    @if($schools->isEmpty())
    <div class="alert alert-light border">No results.</div>
    @else
    <div class="row g-3">
        @foreach($schools as $s)
        @php
        $p = $s->currentProfile;
        $map = $p?->map_image_path;
        if ($map && !Str::startsWith($map, ['http://','https://','/'])) {
        $map = Storage::url($map);
        }
        @endphp
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="row g-0 align-items-stretch">

                    {{-- ✅ 左：Map画像（移動済） --}}
                    <div class="col-12 col-lg-5">
                        <div class="h-100 p-3 text-center">
                            @if($map)
                            <img src="{{ $map }}" class="w-100 rounded-3 mb-2"
                                alt="map" style="object-fit:cover; min-height:220px;">
                            @else
                            <div class="w-100 rounded-3 d-flex align-items-center justify-content-center text-muted"
                                style="background:#f3f4f6; min-height:220px;">
                                No Map Image
                            </div>
                            @endif

                            {{-- ✅ ③「マップを見る」リンク --}}
                            @if($p?->station_url)
                            <a href="{{ $p->station_url }}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fa fa-map-location-dot me-1"></i> View Map
                            </a>
                            @endif
                        </div>
                    </div>

                    {{-- ✅ 右：プロフィール＋駅ボックス群 --}}
                    <div class="col-12 col-lg-7">
                        <div class="p-3 h-100 d-flex flex-column">

                            {{-- 上段：学校プロフィール --}}
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="small text-muted">Code: {{ $s->school_code }}</div>
                                        <h5 class="mb-1">{{ $s->school_name }}</h5>
                                    </div>
                                    <span class="badge bg-{{ $s->is_active ? 'success' : 'secondary' }}">
                                        {{ $s->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                @if($p)
                                <div class="mt-1">
                                    <i class="fa fa-location-dot me-1 text-secondary"></i>
                                    <span class="small">{{ $p->address }}</span>
                                </div>
                                @if($p->description)
                                <div class="small text-muted mt-1">{{ $p->description }}</div>
                                @endif
                                @endif
                            </div>

                            {{-- 下段：駅情報（✅縦並び化 col-12固定） --}}
                            <div class="row g-2">
                                @forelse(($p?->stations ?? collect()) as $st)
                                <div class="col-12">
                                    <div class="border rounded-3 p-2 h-100 bg-white">
                                        <div class="d-flex justify-content-between">
                                            <strong class="me-2">{{ $st->station_name }}</strong>
                                            @if(!is_null($st->walk_minutes))
                                            <span class="badge bg-light text-dark">{{ $st->walk_minutes }} min</span>
                                            @endif
                                        </div>
                                        @if($st->line)
                                        <div class="text-muted small">{{ $st->line }}</div>
                                        @endif
                                        @if($st->guide_image_path)
                                        <div class="mt-2">
                                            <img src="{{ Str::startsWith($st->guide_image_path, ['http://','https://','/'])
                                      ? $st->guide_image_path
                                      : Storage::url($st->guide_image_path) }}"
                                                alt="guide"
                                                class="img-fluid rounded-2"
                                                style="max-height:120px; object-fit:cover;">
                                        </div>
                                        @endif
                                        @if($st->guide_text)
                                        <div class="small mt-2" style="white-space:pre-wrap;">
                                            {{ $st->guide_text }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @empty
                                <div class="col-12">
                                    <div class="border rounded-3 p-2 bg-light small text-muted">
                                        No station info.
                                    </div>
                                </div>
                                @endforelse
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-3">
        {{ $schools->links() }}
    </div>
    @endif
</div>
@endsection