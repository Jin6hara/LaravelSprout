@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-bold">Route Declarations</h4>
        @isset($targetUser)
        <small class="text-muted">
            Viewing: {{ $targetUser->first_name ?? '' }} {{ $targetUser->family_name }}
            [{{ $targetUser->employee_code }}]
        </small>
        @endisset
    </div>

    <div class="row g-1">
        @forelse($declarations as $d)
        <div class="col-12 col-md-12 col-xl-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold">{{ $d->user->first_name ?? '' }} {{ $d->user->family_name ?? '' }}</span>
                        <small class="text-muted">[{{ $d->user->employee_code }}]</small>
                    </div>
                    <small class="text-muted">{{ $d->effective_date->format('Y-m-d') }}</small>
                </div>

                <div class="card-body p-1 small">
                    <table class="table table-sm table-borderless mb-1 align-middle">
                        <tr>
                            <th class="text-muted" style="width:25%">Closest Station: {{ $d->closest_station }}</th>
                            <td></td>
                            <th class="text-muted" style="width:25%">Train Line: {{ $d->train_line }}</th>
                            <td></td>
                            <th class="text-muted" style="width:50%">Reason: {{ $d->reason }}</th>
                            <td></td>
                        </tr>
                    </table>

                    {{-- Route Details --}}
                    @if($d->details->count())
                    <div class="table-responsive mb-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>DOW</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Type</th>
                                    <th>¥</th>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                @foreach($d->details as $det)
                                @php $tripType = is_object($det->trip_type) ? $det->trip_type->value : $det->trip_type; @endphp
                                <tr>
                                    <td>{{ $det->dow }}</td>
                                    <td>{{ $det->from_station }}</td>
                                    <td>{{ $det->to_station }}</td>
                                    <td>{{ str_replace('_',' ', ucfirst($tripType)) }}</td>
                                    <td>{{ number_format($det->amount) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- Commuter Pass Info --}}
                    @php
                    $passes = $d->user->commuterPasses ?? collect();
                    @endphp

                    @if($passes->count())
                    <div class="border-top pt-2">
                        <h6 class="fw-semibold small mb-1">Commuter Pass</h6>
                        @foreach($passes->sortByDesc('date_from') as $p)
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 small mb-1">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <div>
                                    {{ $p->station_from }} → {{ $p->station_to }}
                                    <span class="text-muted">
                                        ({{ $p->date_from->format('Y-m-d') }}〜{{ $p->date_to->format('Y-m-d') }})
                                    </span>
                                </div>
                                <div class="text-end">¥{{ number_format($p->cost) }}</div>
                                @if($p->note)
                                <div class="text-muted fst-italic">｜{{ $p->note }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif


                </div>

                <div class="card-footer text-end py-1 px-2 bg-white">
                    <small class="text-muted">
                        Submitted: {{ optional($d->submitted_at)->format('Y-m-d H:i') ?? 'N/A' }}
                    </small>
                </div>
            </div>
        </div>
        @empty
        <p class="text-muted">No declarations found.</p>
        @endforelse
    </div>
</div>

<style>
    .card {
        border-radius: .5rem;
    }

    .table-sm td,
    .table-sm th {
        padding: .25rem .5rem;
    }

    .table-striped>tbody>tr:nth-of-type(odd) {
        --bs-table-accent-bg: #f9f9f9;
    }
</style>
@endsection