@extends('layouts.app')

@section('title', 'Attendance Search')

@section('content')
<div class="page-wrap">
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
          <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   id="regularOn"
                   name="regular_on"
                   value="1"
                   @checked($mode === 'regular_on')>
            <label class="form-check-label" for="regularOn">
              通常出勤する人を探す（未チェック: 残業できる人）
            </label>
          </div>
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
        <strong>{{ $mode === 'regular_on' ? '通常出勤する人' : '残業できる人' }}</strong>
        / Results: <strong>{{ number_format($results->count()) }}</strong>
      </div>
      <div class="text-muted small">
        Calendar Resolver の最終returnで判定しています。
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th style="width: 44px;">
              <input type="checkbox" class="form-check-input" id="selectAllEmails" aria-label="Select all emails">
            </th>
            <th>Employee Code</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone Number</th>
            <th>Type Code</th>
            <th>Rest Pattern</th>
            <th>District</th>
            <th>Department</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            @php($user = $row['user'])
            <tr>
              <td>
                <input type="checkbox"
                       class="form-check-input email-check"
                       value="{{ $user->email }}"
                       @disabled(blank($user->email))
                       aria-label="Select {{ $user->family_name }} {{ $user->first_name }} email">
              </td>
              <td>{{ $user->employee_code }}</td>
              <td>{{ $user->family_name }} {{ $user->first_name }}</td>
              <td>{{ $user->email }}</td>
              <td>{{ $user->phone_number }}</td>
              <td>{{ $row['type_code'] }}</td>
              <td>{{ $row['rest_pattern'] }}</td>
              <td>{{ $user->district?->name }}</td>
              <td>{{ $user->department?->name }}</td>
              <td class="text-end">
                <a href="{{ route('admin.user.profile', $user) }}" class="btn btn-sm btn-outline-primary">Profile</a>
                <a href="{{ route('calendar.index.user', $user) }}?month={{ substr($date, 0, 7) }}" class="btn btn-sm btn-outline-secondary">Schedule</a>
              </td>
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
