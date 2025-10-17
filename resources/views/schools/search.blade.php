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
        // storageパスなら Storage::url を使う
        if ($map && !Str::startsWith($map, ['http://','https://','/'])) {
        $map = Storage::disk(config('filesystems.default'))->url($map);
        }
        @endphp
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 school-card">
                <div class="p-2">
                    @if($map)
                    <img class="map" src="{{ $map }}" alt="map">
                    @else
                    <div class="map d-flex align-items-center justify-content-center text-muted">
                        No Map Image
                    </div>
                    @endif
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-muted">Code: {{ $s->school_code }}</div>
                            <h6 class="mb-1">{{ $s->school_name }}</h6>
                        </div>
                        <span class="badge bg-{{ $s->is_active ? 'success' : 'secondary' }}">
                            {{ $s->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    @if($p)
                    <div class="mb-2">
                        <i class="fa fa-location-dot me-1 text-secondary"></i>
                        <span class="small">{{ $p->address }}</span>
                    </div>

                    {{-- stations --}}
                    @if($p->stations->count())
                    <div class="mb-2">
                        @foreach($p->stations as $st)
                        <div class="station-chip" title="{{ $st->line }}">
                            {{ $st->station_name }}
                            @if(!is_null($st->walk_minutes)) · {{ $st->walk_minutes }}m
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- guide (最初の駅の説明を短く表示、詳細はモーダルなどに拡張可) --}}
                    @php $firstGuide = $p->stations->first()?->guide_text; @endphp
                    @if($firstGuide)
                    <div class="guide-text">{{ Str::limit($firstGuide, 180) }}</div>
                    @endif
                    @endif
                </div>
                <div class="card-footer bg-white border-0 pt-0">
                    {{-- 詳細ページがあればここにリンクを置く --}}
                    {{-- <a href="{{ route('schools.show', $s) }}" class="btn btn-sm btn-outline-secondary">Details</a> --}}
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