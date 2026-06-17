{{-- カレンダーパターン（休日・休業日・調整日等）を編集するページビュー --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Calendar Pattern Editor</h2>
  <a href="{{ route('data.list') }}" class="btn btn-sm btn-outline-secondary">&larr; Data</a>
</div>

@php
$cpepProps = [
    'patterns'        => $patterns,
    'eventsUrl'       => route('api.cpe.events'),
    'holidayUrl'      => route('api.cpe.holiday.index'),
    'adjustingUrl'    => route('api.cpe.adjusting.index'),
    'closureUrl'      => route('api.cpe.closure.index'),
    'patternRuleUrl'  => route('api.cpe.pattern_rule.index'),
];
@endphp
<div id="calendarPatternEditorPage" data-props='@json($cpepProps)'></div>
@endsection
