{{-- resources/views/expenses/report.blade.php --}}
@extends('layouts.app')

@section('title', 'Expense Reports（管理者・一覧）')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .page-wrap { max-width: 1300px; margin: 20px auto; }
    .header-box { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #78a3fa; }
    .header-box .meta { display:flex; flex-wrap:wrap; gap:16px; font-size:14px; align-items:center; }
    .muted { color:#6b7280; }
    .total { font-weight:700; }
    #sheet { width:100%; max-width:100%; margin-top:12px; }
    .search-form { display:flex; gap:8px; align-items:center; }
    .search-form select, .search-form input[type="number"] { padding:6px 8px; }
    .btn { padding:6px 10px; border:1px solid #ddd; border-radius:6px; background:#fff; cursor:pointer; }
  </style>
@endpush

@section('content')
<div class="page-wrap">
  {{-- ヘッダー / 月次検索 --}}
  <div class="header-box">
    <div class="meta">
      <form method="GET" action="{{ route('expenses.admin.report') }}" class="search-form">
        @php
          $y = (int)($summary['year'] ?? now('Asia/Tokyo')->year);
          $m = (int)($summary['month'] ?? now('Asia/Tokyo')->month);
        @endphp
        <label>Year
          <input type="number" name="year" value="{{ $y }}" min="2000" max="2099" />
        </label>
        <label>Month
          <select name="month">
            @for ($i=1; $i<=12; $i++)
              <option value="{{ $i }}" @selected($i===$m)>{{ $i }}</option>
            @endfor
          </select>
        </label>
        <button class="btn" type="submit">Search</button>
      </form>

      <div class="muted">Records: <span class="total">{{ number_format($summary['count'] ?? 0) }}</span></div>
      <div class="muted">Total Amount (JPY): <span class="total">{{ number_format($summary['total'] ?? 0) }}</span></div>
    </div>
  </div>

  {{-- JSpreadsheet 表示領域 --}}
  <div id="sheet"></div>
</div>
@endsection

@push('scripts')
  {{-- jsuites → jspreadsheet の順で必須 --}}
  <script src="https://cdn.jsdelivr.net/npm/jsuites@5/dist/jsuites.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const rows = @json($rows);

      const data = rows.map(r => ([
        r.employee_code ?? '',
        r.family_name ?? '',
        r.first_name ?? '',
        Number(r.total_amount ?? 0),
        r.status ?? '',
        r.submitted_at ?? '',
      ]));

      const el = document.getElementById('sheet');

      jspreadsheet(el, {
        data,
        tableOverflow: true,
        tableWidth: '100%',
        defaultColWidth: 160,
        columns: [
          { title: 'Employ Code', type: 'text', readOnly: true, width: 130 },
          { title: 'Family Name', type: 'text', readOnly: true, width: 150 },
          { title: 'First Name',  type: 'text', readOnly: true, width: 150 },
          { title: 'Total Amount (JPY)', type: 'numeric', readOnly: true, mask:'#,##0', decimal:',', width: 160 },
          { title: 'Status', type: 'text', readOnly: true, width: 120 },
          { title: 'Submitted At', type: 'text', readOnly: true, width: 170 },
        ],
        editable: false,
      });
    });
  </script>
@endpush

