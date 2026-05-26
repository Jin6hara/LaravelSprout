@extends('layouts.app')

@section('title', 'Teacher Master List')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
<link rel="stylesheet" href="{{ asset('css/masterList.css') }}?v={{ filemtime(public_path('css/masterList.css')) }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
<script src="{{ asset('js/masterList.js') }}?v={{ filemtime(public_path('js/masterList.js')) }}"></script>
@endpush

@section('content')
<div class="page-wrap master-list-page">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="mb-0">Teacher Master List</h1>

    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
      Dashboard
    </a>
  </div>

  <div class="header-box mb-4">
    <div class="meta w-100">
      <div>Total Teachers: <strong>{{ number_format($summary['count'] ?? 0) }}</strong></div>
      <div class="muted">Current employment/rest pattern is shown first when available.</div>
    </div>
  </div>

  <div id="masterListSheet"></div>

  <script id="masterListData" type="application/json">
    @json($rows)
  </script>
</div>
@endsection
