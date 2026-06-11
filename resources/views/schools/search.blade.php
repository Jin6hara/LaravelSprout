{{-- 学校名・地区等の条件で学校を検索・一覧表示するビュー --}}
{{-- resources/views/schools/search.blade.php --}}
@extends('layouts.app')

@section('title', 'School Search')

@push('styles')
<style>
    /* ── Search Hero ── */
    .search-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border-radius: 1.25rem;
        padding: 2rem 2.25rem;
        margin-bottom: 2rem;
        color: #fff;
    }
    .search-hero .form-control,
    .search-hero .form-select {
        height: 48px;
        border: none;
        border-radius: .75rem;
        font-size: .95rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.12);
    }
    .search-hero .btn-search {
        height: 48px;
        border-radius: .75rem;
        background: #fff;
        color: #4f46e5;
        font-weight: 600;
        border: none;
        transition: background .15s, box-shadow .15s;
    }
    .search-hero .btn-search:hover {
        background: #f0f0ff;
        box-shadow: 0 4px 12px rgba(0,0,0,.15);
    }

    /* ── Grid ── */
    .school-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.25rem;
    }

    /* ── Card ── */
    .school-card {
        background: #fff;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
        border: 1px solid rgba(0,0,0,.06);
        transition: transform .2s ease, box-shadow .2s ease;
        display: flex;
        flex-direction: column;
    }
    .school-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 28px rgba(79,70,229,.14);
    }

    /* ── Map ── */
    .card-map {
        width: 100%;
        height: 200px;
        object-fit: cover;
        flex-shrink: 0;
    }
    .card-map-placeholder {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: .85rem;
        gap: .4rem;
        flex-shrink: 0;
    }

    /* ── Status Pill ── */
    .pill-active   { background:#d1fae5; color:#065f46; padding:.2rem .65rem; border-radius:9999px; font-size:.72rem; font-weight:700; }
    .pill-inactive { background:#f3f4f6; color:#6b7280; padding:.2rem .65rem; border-radius:9999px; font-size:.72rem; font-weight:700; }

    /* ── Station Chip ── */
    .station-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .28rem .72rem;
        background: #ede9fe;
        color: #5b21b6;
        border-radius: 9999px;
        font-size: .78rem;
        font-weight: 500;
        cursor: pointer;
        user-select: none;
        border: none;
        transition: background .15s;
    }
    .station-chip:hover { background: #ddd6fe; }
    .station-chip .walk { opacity: .7; font-size: .73rem; }

    /* ── Station Detail Panel ── */
    .station-panel {
        background: #f8f7ff;
        border: 1px solid #ddd6fe;
        border-radius: .75rem;
        padding: .9rem 1rem;
        margin-top: .5rem;
        font-size: .82rem;
    }

    /* ── Empty State ── */
    .empty-state {
        padding: 5rem 0;
        text-align: center;
        color: #9ca3af;
    }
    .empty-state i { font-size: 3rem; opacity: .25; margin-bottom: 1rem; }
</style>
@endpush

@section('content')
<div class="container my-4" style="max-width:1200px;">

    {{-- ── Search Hero ── --}}
    <div class="search-hero">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-school fa-lg"></i>
            <h5 class="mb-0 fw-bold" style="font-size:1.2rem;">School Search</h5>
        </div>
        <form method="GET" action="{{ route('schools.search') }}">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md">
                    <input type="text" name="q"
                        class="form-control"
                        value="{{ $q }}"
                        placeholder="Name / Code / Kana / Alias...">
                </div>
                <div class="col-auto">
                    <select name="per_page" class="form-select" style="min-width:110px;">
                        @foreach([6,12,24,48] as $n)
                        <option value="{{ $n }}" @selected(request('per_page',12)==$n)>{{ $n }} / page</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-search px-4">
                        <i class="fa fa-magnifying-glass me-1"></i>Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Results ── --}}
    @if($schools->isEmpty())
    <div class="empty-state">
        <i class="fa fa-school d-block"></i>
        <p class="mb-0">No schools found.</p>
    </div>
    @else

    {{-- Count bar --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted" style="font-size:.83rem;">
            <strong class="text-dark">{{ $schools->total() }}</strong> schools found
        </span>
        <span class="text-muted" style="font-size:.83rem;">
            Page {{ $schools->currentPage() }} / {{ $schools->lastPage() }}
        </span>
    </div>

    {{-- Grid --}}
    <div class="school-grid">
        @foreach($schools as $s)
        @php
            $p   = $s->currentProfile;
            $map = $p?->map_image_path;
            if ($map && !Str::startsWith($map, ['http://','https://','/'])) {
                $map = Storage::url($map);
            }
            $stations = $p?->stations ?? collect();
        @endphp

        <div class="school-card">

            {{-- Map image --}}
            @if($map)
            <img src="{{ $map }}" class="card-map" alt="map">
            @else
            <div class="card-map-placeholder">
                <i class="fa fa-map"></i> No Map Image
            </div>
            @endif

            <div class="p-4 d-flex flex-column flex-grow-1">

                {{-- Header --}}
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold" style="font-size:1rem; line-height:1.3;">
                            {{ $s->aliases[0] ?? $s->school_name }}
                            <span class="text-muted fw-normal" style="font-size:.85rem;">[{{ $s->school_code }}] {{ $s->school_name }}</span>
                        </div>
                    </div>
                    <span class="{{ $s->is_active ? 'pill-active' : 'pill-inactive' }} ms-2 flex-shrink-0">
                        {{ $s->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                {{-- Address --}}
                @if($p?->address)
                <div class="text-muted mb-2" style="font-size:.82rem;">
                    <i class="fa fa-location-dot me-1 text-indigo-400" style="color:#818cf8;"></i>
                    {{ $p->address }}
                </div>
                @endif

                {{-- Description --}}
                @if($p?->description)
                <div class="text-muted mb-3" style="font-size:.81rem; line-height:1.55;">
                    {{ Str::limit($p->description, 90) }}
                </div>
                @endif

                {{-- Spacer --}}
                <div class="flex-grow-1"></div>

                {{-- Stations --}}
                @if($stations->isNotEmpty())
                <div class="border-top pt-3 mt-2">
                    <div class="text-muted mb-2" style="font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;">
                        <i class="fa fa-train me-1"></i>Nearest Stations
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($stations as $st)
                        <button class="station-chip"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#st-{{ $s->id }}-{{ $loop->index }}"
                                aria-expanded="false">
                            {{ $st->station_name }}
                            @if(!is_null($st->walk_minutes))
                            <span class="walk">{{ $st->walk_minutes }}min</span>
                            @endif
                            <i class="fa fa-chevron-down fa-xs"></i>
                        </button>
                        @endforeach
                    </div>

                    @foreach($stations as $st)
                    <div class="collapse" id="st-{{ $s->id }}-{{ $loop->index }}">
                        <div class="station-panel">
                            <div class="fw-semibold mb-1">{{ $st->station_name }}</div>
                            @if($st->line)
                            <div class="text-muted mb-1">
                                <i class="fa fa-route me-1"></i>{{ $st->line }}
                            </div>
                            @endif
                            @if($st->guide_image_path)
                            @php
                                $gi = $st->guide_image_path;
                                if (!Str::startsWith($gi, ['http://','https://','/'])) {
                                    $gi = Storage::url($gi);
                                }
                            @endphp
                            <img src="{{ $gi }}" alt="guide"
                                 class="img-fluid rounded-2 my-2"
                                 style="max-height:140px; width:100%; object-fit:cover;">
                            @endif
                            @if($st->guide_text)
                            <div style="white-space:pre-wrap; color:#4b5563;">{{ $st->guide_text }}</div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- View Map button --}}
                @if($p?->station_url)
                <a href="{{ $p->station_url }}" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-primary w-100 mt-3" style="border-radius:.6rem;">
                    <i class="fa fa-map-location-dot me-1"></i>View on Map
                </a>
                @endif

            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-4 d-flex justify-content-center">
        {{ $schools->links() }}
    </div>
    @endif

</div>
@endsection
