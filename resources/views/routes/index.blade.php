{{-- ユーザーの通勤経路（定期券・パターン）のサマリーを表示するビュー --}}
@extends('layouts.app')

@section('title', 'Commuting Route Summary')

@section('content')
@php
    $patternUrl = $isAdminMode
        ? route('expenses.admin.pattern', ['user' => $targetUser])
        : route('expenses.pattern');
    $passUrl = $isAdminMode
        ? route('commuter_passes.admin.create', ['user' => $targetUser])
        : route('commuter_passes.create');
    $backUrl = $isAdminMode
        ? route('expenses.admin.edit', ['user' => $targetUser])
        : route('expenses.edit');
@endphp

<div class="container-fluid py-2 commute-route-summary">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h5 class="mb-1 fw-bold">Commuting Route Summary</h5>
            <div class="text-muted small">
                {{ $targetUser->first_name }} {{ $targetUser->family_name }} [{{ $targetUser->employee_code }}]
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm commute-route-summary__btn">Back</a>
            <a href="{{ $passUrl }}" class="btn btn-outline-primary btn-sm commute-route-summary__btn">＋ Commuter Pass</a>
            <a href="{{ $patternUrl }}" class="btn btn-outline-primary btn-sm commute-route-summary__btn">＋ Pattern</a>
        </div>
    </div>

    <section class="mb-3">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0 fw-bold">Commuter Pass</h6>
            <span class="text-muted small">{{ $passes->count() }} item(s)</span>
        </div>

        @if($passes->count())
            <div class="commute-route-list">
                @foreach($passes as $pass)
                    <div class="commute-route-row">
                        <div class="commute-route-row__main">
                            <div class="fw-semibold">
                                {{ $pass->station_from }} <span class="text-muted">→</span> {{ $pass->station_to }}
                            </div>
                            <div class="text-muted small">
                                {{ $pass->date_from?->format('Y-m-d') }} - {{ $pass->date_to?->format('Y-m-d') }}
                            </div>
                            @if($pass->note)
                                <div class="text-muted small text-truncate">{{ $pass->note }}</div>
                            @endif
                        </div>
                        <div class="commute-route-row__amount">¥{{ number_format((int) $pass->cost) }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-muted small border rounded p-2 bg-white">No commuter pass to display.</div>
        @endif
    </section>

    <section>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0 fw-bold">Commute Pattern</h6>
            <span class="text-muted small">{{ $patterns->count() }} item(s)</span>
        </div>

        @forelse($patterns as $pattern)
            <div class="commute-route-pattern">
                @include('routes.showCard', ['commutePattern' => $pattern, 'user' => $targetUser])
                <div class="text-end">
                    <a href="{{ $isAdminMode
                            ? route('expenses.admin.pattern', ['user' => $targetUser, 'pattern' => $pattern->id])
                            : route('expenses.pattern', ['pattern' => $pattern->id]) }}"
                        class="btn btn-outline-secondary btn-sm">
                        Edit Pattern
                    </a>
                </div>
            </div>
        @empty
            <div class="text-muted small border rounded p-2 bg-white">No commute pattern to display.</div>
        @endforelse
    </section>
</div>
@endsection

@push('styles')
<style>
    .commute-route-summary__btn {
        min-width: 128px;
        white-space: nowrap;
    }

    .commute-route-list {
        display: grid;
        gap: 6px;
    }

    .commute-route-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        font-size: .875rem;
    }

    .commute-route-row__main {
        min-width: 0;
    }

    .commute-route-row__amount {
        font-weight: 700;
        white-space: nowrap;
    }

    .commute-route-pattern {
        margin-bottom: 10px;
    }

    @media (max-width: 575.98px) {
        .commute-route-summary__btn {
            flex: 1 1 120px;
            min-width: 0;
        }

        .commute-route-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .commute-route-row__amount {
            text-align: right;
        }
    }
</style>
@endpush
