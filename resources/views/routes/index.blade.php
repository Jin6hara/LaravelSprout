@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-bold">Route Declarations</h5>
        @isset($targetUser)
        <div class="d-flex justify-content-between align-items-center mb-2">

            <span class="badge bg-info-subtle text-dark border px-3 py-2" style="font-size: 0.9rem;">
                👤 Viewing:
                <strong>{{ $targetUser->first_name ?? '' }} {{ $targetUser->family_name }}</strong>
                [{{ $targetUser->employee_code }}]
            </span>

        </div>
        @endisset
    </div>

    {{-- ▼ 定期券カード（Commuter Pass） --}}
    @php
    $passOwner = $targetUser ?? optional($declarations->first())->user;
    $passes = optional($passOwner)->commuterPasses ?? collect();
    @endphp
    @if($passes->count())
    <div class="card shadow-sm mt-3 mb-3">
        <div class="card-header py-2 px-3 bg-light d-flex align-items-center gap-2">
            <span class="fw-semibold small">Commuter Pass</span>
            <span class="text-muted small">({{ $passes->count() }})</span>
        </div>
        <div class="card-body p-2">
            @foreach($passes->sortByDesc('date_from') as $p)
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small py-1 border-bottom">
                <div class="text-truncate">
                    <strong>{{ $p->station_from }}</strong> → <strong>{{ $p->station_to }}</strong>
                    <span class="text-muted">
                        ({{ $p->date_from->format('Y-m-d') }}〜{{ $p->date_to->format('Y-m-d') }})
                    </span>
                    @if($p->note)
                    <span class="text-muted fst-italic">｜{{ $p->note }}</span>
                    @endif
                </div>
                <div class="text-end">¥{{ number_format($p->cost) }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ▼ すべてのルート申請カード --}}
    @forelse($declarations as $routeDecl)
    @include('routes.showCard', ['routeDecl' => $routeDecl])
    @empty
    <div class="text-muted small">No route declaration to display.</div>
    @endforelse
</div>
@endsection