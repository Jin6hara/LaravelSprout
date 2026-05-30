@php
    $pattern = $commutePattern ?? null;
    $owner = $user ?? $pattern?->user;
    $isAdminViewer = auth()->user()?->hasAnyRole(['admin', 'super_admin']);
    $moreUrl = $isAdminViewer && $owner
        ? route('routes.user', ['user' => $owner])
        : route('routes.index');
    $visibleLegs = $pattern?->legs
        ? $pattern->legs->filter(fn ($leg) => (int) $leg->cost > 0)->values()
        : collect();
@endphp

@if($pattern && $visibleLegs->isNotEmpty())
    <div class="commute-pattern-card mb-2">
        <div class="commute-pattern-card__head">
            <div class="commute-pattern-card__meta">
                <span class="commute-pattern-card__title">Pattern</span>
                <span>{{ $pattern->valid_from?->format('Y-m-d') }} - {{ $pattern->valid_to?->format('Y-m-d') }}</span>
                <span class="commute-pattern-card__station">{{ $pattern->closest_station }}</span>
                @if($pattern->train_line)
                    <span class="commute-pattern-card__line">{{ $pattern->train_line }}</span>
                @endif
            </div>

            {{-- expenses/edit: More ボタン --}}
            @if($showMore ?? false)
                <a href="{{ $moreUrl }}" class="btn btn-outline-primary btn-sm commute-pattern-card__more">
                    More
                </a>
            @endif
        </div>

        <div class="commute-pattern-card__body">
            <div class="commute-pattern-legs">
                @foreach($visibleLegs as $leg)
                    @php
                        $dow = is_object($leg->dow) ? $leg->dow->value : $leg->dow;
                        $tripType = is_object($leg->trip_type) ? $leg->trip_type->value : $leg->trip_type;
                        $tripDisp = $tripType === 'one_way' ? 'OW' : 'RT';
                        $from = $leg->station_from ?: '-';
                        $to = $leg->station_to ?: '-';
                    @endphp
                    <div class="commute-pattern-leg">
                        <div class="commute-pattern-leg__day">{{ $dow }}</div>
                        <div class="commute-pattern-leg__route">
                            <span class="commute-pattern-leg__route-text" title="{{ $from }} → {{ $to }}">
                                {{ $from }} <span class="text-muted">→</span> {{ $to }}
                            </span>
                        </div>
                        <div class="commute-pattern-leg__fare">
                            <span>¥{{ number_format((int) $leg->cost) }}</span>
                            <span class="commute-pattern-leg__trip">{{ $tripDisp }}</span>
                        </div>
                        @if($leg->note)
                            <div class="commute-pattern-leg__note" title="{{ $leg->note }}">{{ $leg->note }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@elseif(!$pattern)
    <div class="text-muted small">No commuting pattern to display.</div>
@endif

@once
<style>
    .commute-pattern-card {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background: #fff;
        overflow: hidden;
    }

    .commute-pattern-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        padding: 5px 8px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .commute-pattern-card__meta {
        min-width: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 3px 8px;
        font-size: .78rem;
        line-height: 1.2;
    }

    .commute-pattern-card__title {
        font-weight: 700;
        color: #212529;
    }

    .commute-pattern-card__station,
    .commute-pattern-card__line {
        min-width: 0;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .commute-pattern-card__more {
        flex: 0 0 auto;
        min-width: 54px;
        padding-block: 2px;
    }

    .commute-pattern-card__body {
        padding: 4px;
    }

    .commute-pattern-legs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
        gap: 4px;
    }

    .commute-pattern-leg {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        align-items: center;
        gap: 5px;
        padding: 4px 6px;
        border: 1px solid #edf0f2;
        border-radius: 5px;
        background: #fcfcfd;
        font-size: .78rem;
        line-height: 1.2;
        min-width: 0;
    }

    .commute-pattern-leg__day {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
        background: #fff;
        font-weight: 700;
    }

    .commute-pattern-leg__route {
        min-width: 0;
        font-weight: 600;
    }

    .commute-pattern-leg__route-text,
    .commute-pattern-leg__note {
        display: block;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .commute-pattern-leg__note {
        grid-column: 2 / 4;
        margin-top: -2px;
        color: #6c757d;
        font-size: .72rem;
    }

    .commute-pattern-leg__fare {
        display: flex;
        flex-direction: row;
        align-items: flex-end;
        gap: 4px;
        white-space: nowrap;
        font-weight: 700;
    }

    .commute-pattern-leg__trip {
        color: #6c757d;
        font-size: .72rem;
        font-weight: 400;
    }

    @media (max-width: 575.98px) {
        .commute-pattern-card__head {
            align-items: center;
        }

        .commute-pattern-card__meta {
            gap: 3px 7px;
        }

        .commute-pattern-card__station,
        .commute-pattern-card__line {
            max-width: 130px;
        }

        .commute-pattern-legs {
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
        }
    }
</style>
@endonce
