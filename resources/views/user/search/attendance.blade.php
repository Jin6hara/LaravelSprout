{{-- ユーザーの出勤・勤怠実績を月単位で検索・一覧表示するビュー --}}
@extends('layouts.app')

@section('title', 'Attendance Search')

@push('styles')
<style>
  .attendance-page {
    max-width: 1320px;
    margin: 20px auto;
  }

  .attendance-table {
    min-width: 1120px;
    table-layout: fixed;
  }

  .attendance-table th,
  .attendance-table td {
    vertical-align: middle;
  }

  .attendance-table .cell-nowrap {
    white-space: nowrap;
  }

  .attendance-table .cell-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .attendance-actions {
    white-space: nowrap;
  }

  .attendance-actions .btn + .btn {
    margin-left: 4px;
  }
</style>
@endpush

@section('content')
<div class="attendance-page">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="mb-0">Attendance Search</h1>
    <a href="{{ route('user.master_list') }}" class="btn btn-sm btn-outline-secondary">Master List</a>
  </div>

  <form method="GET" action="{{ route('user.search.attendance') }}" class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label for="attendanceDate" class="form-label">Date</label>
          <input type="date"
                 id="attendanceDate"
                 name="date"
                 value="{{ old('date', $date) }}"
                 class="form-control"
                 required>
        </div>

        <div class="col-md-6">
          <label for="attendanceMode" class="form-label">Search Type</label>
          <select id="attendanceMode" name="mode" class="form-select">
            <option value="available" @selected($mode === 'available')>Search teachers with a regular day off</option>
            <option value="regular_on" @selected($mode === 'regular_on')>Search teachers with a regular shift</option>
          </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Search</button>
          <a href="{{ route('user.search.attendance') }}" class="btn btn-outline-secondary">Clear</a>
        </div>
      </div>
    </div>
  </form>

  @error('date')
    <div class="alert alert-danger">{{ $message }}</div>
  @enderror

  @if($searched)
    <div class="alert alert-light border mb-3">
      <div>
        Date: <strong>{{ $date }}</strong>
        / Mode:
        <strong>{{ $mode === 'regular_on' ? 'Teachers with a regular shift' : 'Teachers with a regular day off' }}</strong>
        / Results: <strong>{{ number_format($results->count()) }}</strong>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle attendance-table">
        <colgroup>
          <col style="width: 150px;">
          <col style="width: 78px;">
          <col style="width: 180px;">
          <col style="width: 46px;">
          <col style="width: 280px;">
          <col style="width: 116px;">
          <col style="width: 56px;">
          <col style="width: 134px;">
          <col style="width: 100px;">
          <col style="width: 110px;">
        </colgroup>
        <thead>
          <tr>
            <th></th>
            <th>Code</th>
            <th>Name</th>
            <th>
              <input type="checkbox" class="form-check-input" id="selectAllEmails" aria-label="Select all emails">
            </th>
            <th>Email</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Rest Pattern</th>
            <th>District</th>
            <th>Department</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            @php($user = $row['user'])
            <tr>
              <td class="attendance-actions">
                <a href="{{ route('admin.user.profile', $user) }}" class="btn btn-sm btn-outline-primary">Profile</a>
                <a href="{{ route('calendar.index.user', $user) }}?month={{ substr($date, 0, 7) }}" class="btn btn-sm btn-outline-secondary">Schedule</a>
              </td>
              <td class="cell-nowrap">{{ $user->employee_code }}</td>
              <td class="cell-truncate" title="{{ $user->family_name }} {{ $user->first_name }}">{{ $user->family_name }} {{ $user->first_name }}</td>
              <td>
                <input type="checkbox"
                       class="form-check-input email-check"
                       value="{{ $user->email }}"
                       @disabled(blank($user->email))
                       aria-label="Select {{ $user->family_name }} {{ $user->first_name }} email">
              </td>
              <td class="cell-truncate" title="{{ $user->email }}">{{ $user->email }}</td>
              <td class="cell-nowrap">{{ $user->phone_number }}</td>
              <td class="cell-nowrap">{{ $row['type_code'] }}</td>
              <td class="cell-truncate" title="{{ $row['rest_pattern'] }}">{{ $row['rest_pattern'] }}</td>
              <td class="cell-truncate" title="{{ $user->district?->name }}">{{ $user->district?->name }}</td>
              <td class="cell-truncate" title="{{ $user->department?->name }}">{{ $user->department?->name }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="text-center text-muted py-4">No results.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
      <button type="button" class="btn btn-sm btn-primary" id="copySelectedEmails">
        Copy selected emails
      </button>
      <input type="text"
             class="form-control form-control-sm flex-grow-1"
             id="selectedEmailsOutput"
             readonly
             placeholder="Selected emails for BCC">
      <span class="small text-muted" id="selectedEmailsCount">0 selected</span>
    </div>
  @endif
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllEmails');
    const checks = Array.from(document.querySelectorAll('.email-check'));
    const output = document.getElementById('selectedEmailsOutput');
    const count = document.getElementById('selectedEmailsCount');
    const copyButton = document.getElementById('copySelectedEmails');

    function selectedEmails() {
      return checks
        .filter(check => check.checked && check.value)
        .map(check => check.value);
    }

    function refreshOutput() {
      const emails = selectedEmails();
      output.value = emails.join('; ');
      count.textContent = `${emails.length} selected`;

      if (selectAll) {
        const enabled = checks.filter(check => !check.disabled);
        selectAll.checked = enabled.length > 0 && enabled.every(check => check.checked);
        selectAll.indeterminate = enabled.some(check => check.checked) && !selectAll.checked;
      }
    }

    selectAll?.addEventListener('change', function () {
      checks.forEach(check => {
        if (!check.disabled) check.checked = selectAll.checked;
      });
      refreshOutput();
    });

    checks.forEach(check => check.addEventListener('change', refreshOutput));

    copyButton?.addEventListener('click', async function () {
      refreshOutput();
      if (!output.value) return;

      try {
        await navigator.clipboard.writeText(output.value);
        copyButton.textContent = 'Copied';
        setTimeout(() => copyButton.textContent = 'Copy selected emails', 1200);
      } catch (e) {
        output.select();
        document.execCommand('copy');
      }
    });

    refreshOutput();
  });
</script>
@endsection
