<div class="col-lg-12 mb-3">
    @if(!empty($routeDecl))
    @php
    $d = $routeDecl;
    @endphp
    <div class="card shadow-sm">
        {{-- ヘッダー（横並び） --}}
        <div class="card-header py-2 px-3 bg-light d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <small class="fw-semibold">Eff: {{ \Illuminate\Support\Carbon::parse($d->effective_date)->format('Y-m-d') }}</small>
                <small class="fw-semibold">Station: {{ $d->closest_station }}</small>
                @if($d->train_line)
                <small class="fw-semibold">Line: {{ $d->train_line }}</small>
                @endif
                @if($d->reason)
                <small class="fw-semibold text-truncate" style="max-width: 100%;">Reason: {{ $d->reason }}</small>
                @endif
            </div>

            {{-- expenses/edit: More ボタン --}}
            @if(($showMore ?? false) && isset($d->user))
            @if(auth()->user()?->hasAnyRole(['admin', 'super_admin']))
            {{-- admin以上：今まで通りユーザー別のルート画面へ --}}
            <a href="{{ route('routes.user', ['user' => $d->user->employee_code]) }}"
                class="btn btn-outline-primary btn-sm">
                More
            </a>
            @else
            {{-- それ以外：routes.index へ --}}
            <a href="{{ route('routes.index') }}"
                class="btn btn-outline-primary btn-sm">
                More
            </a>
            @endif
            @endif
        </div>

        {{-- 明細：横並び --}}
        <div class="card-body p-1">
            @if($d->details && $d->details->count())
            <div class="d-flex flex-wrap gap-1">
                @foreach($d->details as $det)
                @php
                $tripType = is_object($det->trip_type) ? $det->trip_type->value : $det->trip_type;
                $tripDisp = $tripType === 'one_way' ? 'One-way' : 'Round-trip';
                @endphp
                <div class="border rounded-2 p-1 flex-shrink-0" style="min-width: auto;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge text-bg-light border">{{ $det->dow }}</span>
                        <span class="badge text-muted">¥{{ number_format($det->amount) }}</span>
                        <div class="small">{{ $tripDisp }}</div>
                    </div>
                    <div class="small">
                        <span class="fw-semibold">{{ $det->from_station }}</span> →
                        <span class="fw-semibold">{{ $det->to_station }}</span>
                    </div>
                    @if(!empty($det->note))
                    <div class="text-muted small mt-1 text-truncate" title="{{ $det->note }}">
                        {{ $det->note }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-muted small px-1">No route details.</div>
            @endif
        </div>
    </div>
    @else
    <div class="text-muted small">No route declaration to display.</div>
    @endif
</div>

<style>
    /* ルート明細カードをさらに見やすく */
    .card .border.rounded-2 {
        background-color: #fafafa;
        transition: 0.2s ease;
    }

    .card .border.rounded-2:hover {
        background-color: #e5eafa;
    }
</style>