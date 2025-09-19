{{-- resources/views/expenses/edit.blade.php --}}
@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
<link href="https://unpkg.com/tabulator-tables@5.6.0/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .page-wrap {
        max-width: 1100px;
        margin: 20px auto;
    }

    .header-box {
        background: #f8f9fa;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .header-box .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 14px;
    }

    .total {
        font-weight: 700;
    }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@5.6.0/dist/js/tabulator.min.js"></script>
@endpush

@section('content')
<div class="page-wrap">
    <h1 class="mb-3">交通費（{{ $y }}年{{ $m }}月）</h1>

    <div class="header-box mb-4">
        <div class="meta">
            <div>講師: <strong>{{ $report->employee_family_name }} {{ $report->employee_first_middle_name }}</strong></div>
            <div>社員コード: <strong>{{ $report->employee_code }}</strong></div>
            <div>ステータス: <strong>{{ strtoupper($report->status->value ?? $report->status) }}</strong></div>
            <div class="total">合計: <strong>{{ number_format($report->total_amount) }}</strong> 円</div>
        </div>
    </div>

    <div id="expensesTable"></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = @json($rows);

        const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        const table = new Tabulator("#expensesTable", {
            data: rows,
            layout: "fitColumns",
            height: "600px",
            placeholder: "データがありません",
            columns: [
                // ✅ ID列は削除しました
                {
                    title: "日付",
                    field: "expense_date",
                    sorter: "date",
                    width: 120
                },
                {
                    title: "Weekday",
                    field: "weekday",
                    width: 100,
                    formatter: function(cell) {
                        const d = cell.getRow().getData().expense_date;
                        if (!d) return "";
                        const idx = new Date(d).getDay(); // 0=Sun ... 6=Sat（ローカルTZ）
                        return weekdayNames[idx] ?? "";
                    }
                },
                {
                    title: "出発",
                    field: "station_from",
                    width: 140
                },
                {
                    title: "到着",
                    field: "station_to",
                    width: 140
                },
                {
                    title: "往復/片道",
                    field: "trip_type",
                    width: 120
                },
                {
                    title: "区分",
                    field: "category",
                    width: 110
                },
                {
                    title: "金額(円)",
                    field: "cost",
                    hozAlign: "right",
                    width: 110,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        return v != null ? v.toLocaleString() : "";
                    }
                },
                {
                    title: "備考",
                    field: "note",
                    widthGrow: 2
                },
            ],
        });
    });
</script>
@endsection