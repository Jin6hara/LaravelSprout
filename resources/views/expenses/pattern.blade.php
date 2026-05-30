{{-- resources/views/expenses/pattern.blade.php --}}
@extends('layouts.app')

@section('title', 'Commuting Expense Pattern')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
<link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ filemtime(public_path('css/expenses.css')) }}">
@endpush

@section('content')
@php
    $newUrl = $isAdminMode
        ? route('expenses.admin.pattern', ['user' => $user, 'new' => 1])
        : route('expenses.pattern', ['new' => 1]);
    $loadUrl = $isAdminMode
        ? route('expenses.admin.pattern', ['user' => $user])
        : route('expenses.pattern');
    $backUrl = $isAdminMode
        ? route('expenses.admin.edit', ['user' => $user])
        : route('expenses.edit');
@endphp

<div class="page-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">
      Commuting Pattern
      <small class="text-muted fs-6">
        for {{ $user->first_name }} {{ $user->family_name }} ({{ $user->employee_code }})
      </small>
    </h1>

    <div class="d-flex flex-wrap gap-2">
      <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm btn-route-fixed">Back</a>
      <a href="{{ $newUrl }}" class="btn btn-outline-primary btn-sm btn-route-fixed">＋ New Pattern</a>
    </div>
  </div>

  @if($patterns->isNotEmpty())
    <form method="GET" action="{{ $loadUrl }}" class="mb-3 d-flex align-items-center gap-2">
      <label for="patternPick" class="form-label m-0">Pattern</label>
      <select id="patternPick" name="pattern" class="form-select form-select-sm" style="max-width:420px">
        @foreach($patterns as $p)
          <option value="{{ $p->id }}" @selected($pattern?->id === $p->id)>
            {{ $p->valid_from?->format('Y-m-d') }} - {{ $p->valid_to?->format('Y-m-d') }}
            / {{ $p->closest_station }}
          </option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-sm btn-outline-primary">Load</button>
    </form>
  @endif

  <div class="header-box mb-2">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="closestStation" class="form-label small mb-0">Closest Station <span class="text-danger">*</span></label>
        <input type="text" id="closestStation" class="form-control form-control-sm"
          value="{{ old('closest_station', $pattern?->closest_station) }}" required>
      </div>

      <div class="col-md-3">
        <label for="trainLine" class="form-label small mb-0">Train Line</label>
        <input type="text" id="trainLine" class="form-control form-control-sm"
          value="{{ old('train_line', $pattern?->train_line) }}">
      </div>

      <div class="col-md-2">
        <label for="validFrom" class="form-label small mb-0">Valid From <span class="text-danger">*</span></label>
        <input type="date" id="validFrom" class="form-control form-control-sm"
          value="{{ $defaultValidFrom }}" required>
      </div>

      <div class="col-md-2">
        <label for="validTo" class="form-label small mb-0">Valid To <span class="text-danger">*</span></label>
        <input type="date" id="validTo" class="form-control form-control-sm"
          value="{{ $defaultValidTo }}" required>
      </div>

      <div class="col-md-2 d-flex gap-2 justify-content-md-end">
        <button id="savePatternBtn" class="btn btn-primary btn-sm" type="button">Save</button>
        @if($pattern)
          <button id="deletePatternBtn" class="btn btn-outline-danger btn-sm" type="button">Delete</button>
        @endif
      </div>

      <div class="col-12">
        <label for="patternReason" class="form-label small mb-0">Reason for Submitting Expenses</label>
        <textarea id="patternReason" rows="2" class="form-control form-control-sm">{{ old('reason', $pattern?->reason) }}</textarea>
      </div>
    </div>
  </div>

  <div class="header-box mb-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <label for="pickDow" class="form-label m-0">Day</label>
      <select id="pickDow" class="form-select form-select-sm" style="width:110px">
        @foreach($dowValues as $dow)
          <option value="{{ $dow }}">{{ $dow }}</option>
        @endforeach
      </select>
      <button id="addDowBtn" class="btn btn-success btn-sm" type="button">＋ Add Day</button>
    </div>
  </div>

  <div id="patternSheetScroll">
    <div id="patternSheet"></div>
  </div>

  <div class="mt-2 d-flex gap-2">
    <a href="https://world.jorudan.co.jp/mln/en/?sub_lang=nosub"
      class="btn btn-outline-secondary btn-sm"
      target="_blank" rel="noopener noreferrer">
      Open Jorudan (Japanese Transit Planner)
    </a>
    <a href="https://www.google.com/maps/"
      class="btn btn-outline-secondary btn-sm"
      target="_blank" rel="noopener noreferrer">
      Open Google Maps
    </a>
  </div>

  <div class="modal fade" id="confirmPatternDeleteModal" tabindex="-1" aria-labelledby="confirmPatternDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark py-2 custom-orange-header">
          <h6 class="modal-title" id="confirmPatternDeleteLabel">Delete Confirmation</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body small">
          Are you sure you want to delete this commute pattern?
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmPatternDeleteYes" class="btn btn-danger btn-sm">Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .btn-route-fixed {
    width: 150px;
    text-align: center;
    white-space: nowrap;
  }

  #patternSheetScroll {
    width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    text-align: left;
  }

  #patternSheet {
    width: 100%;
    max-width: 100%;
    height: auto;
    margin: 0 auto;
  }

  #patternSheet .jss,
  #patternSheet .jss_container,
  #patternSheet .jspreadsheet {
    margin-left: auto;
    margin-right: auto;
  }

  @media (min-width: 1200px) {
    #patternSheetScroll {
      text-align: center;
    }

    #patternSheet {
      display: inline-block;
      width: auto;
      min-width: 1140px;
      text-align: left;
      vertical-align: top;
    }
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
<script>
  window.COMMUTE_PATTERN_BOOTSTRAP = {
    csrfToken: @json(csrf_token()),
    targetUserId: @json($user->id),
    patternId: @json($pattern?->id),
    initialRows: @json($rows),
    dowValues: @json($dowValues),
    saveUrl: @json(route('api.commute_patterns.batch')),
    deleteUrl: @json($pattern ? route('api.commute_patterns.destroy', $pattern) : null),
  };
</script>
<script src="{{ asset('js/commute-patterns.js') }}?v={{ filemtime(public_path('js/commute-patterns.js')) }}" defer></script>
@endpush
