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

    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('user.search.attendance') }}" class="btn btn-sm btn-outline-primary">
        Attendance Search
      </a>
    </div>
  </div>

  <form method="GET" action="{{ route('user.master_list') }}" class="master-search mb-3" id="masterSearchForm">
    <div class="row g-3 align-items-end">
      <div class="col-lg-6">
        <label for="masterSearch" class="form-label">Search</label>
        <input type="text"
          id="masterSearch"
          name="search"
          value="{{ $search ?? '' }}"
          class="form-control form-control-sm"
          placeholder="Employee Code / First Name / Last Name / Phone / Type Code / Rest Pattern">
      </div>

      <div class="col-lg-4">
        <label class="form-label d-block">Status</label>
        <div class="d-flex flex-wrap gap-3">
          <div class="form-check">
            <input class="form-check-input master-status-check"
              type="checkbox"
              id="status-active"
              name="statuses[]"
              value="active"
              {{ $statuses->contains('active') ? 'checked' : '' }}>
            <label class="form-check-label" for="status-active">在籍者</label>
          </div>

          <div class="form-check">
            <input class="form-check-input master-status-check"
              type="checkbox"
              id="status-terminated"
              name="statuses[]"
              value="terminated"
              {{ $statuses->contains('terminated') ? 'checked' : '' }}>
            <label class="form-check-label" for="status-terminated">退職者</label>
          </div>
        </div>
      </div>

      <div class="col-lg-2 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
        <a href="{{ route('user.master_list') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
      </div>
    </div>
  </form>

  <div class="header-box mb-4">
    <div class="meta w-100 align-items-center">
      <div>Total Teachers: <strong>{{ number_format($summary['count'] ?? 0) }}</strong></div>
      <div class="muted">Current employment/rest pattern is shown first when available.</div>

      <div class="master-table-toolbar ms-auto">
        <label for="masterListHeight" class="form-label m-0 small text-muted">Table Height</label>
        <select id="masterListHeight" class="form-select form-select-sm master-height-select">
          <option value="420">Compact</option>
          <option value="560">Standard</option>
          <option value="720">Tall</option>
          <option value="full">Full</option>
        </select>
      </div>
    </div>
  </div>

  <div id="masterListSheet"></div>

  <script id="masterListData" type="application/json">
    @json($rows)
  </script>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const boxes = document.querySelectorAll('.master-status-check');
    boxes.forEach(box => {
      box.addEventListener('change', () => {
        const checked = Array.from(boxes).filter(b => b.checked);
        if (checked.length === 0) {
          box.checked = true;
        }
      });
    });
  });
</script>
@endsection
