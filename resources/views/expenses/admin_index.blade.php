@extends('layouts.app')

@section('content')
<div class="container">
    <h3>交通費一覧（{{ $y }}-{{ sprintf('%02d',$m) }}）</h3>

    <div class="mb-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('expense.export.csv',['year'=>$y,'month'=>$m]) }}">CSV出力</a>
    </div>

    <table class="table table-sm">
        <thead>
            <tr>
                <th>コード</th>
                <th>氏名</th>
                <th>合計</th>
                <th>ステータス</th>
                <th>提出</th>
                <th>承認</th>
                <th>支払</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reports as $r)
            <tr>
                <td><a href="{{ route('expense.reports.show',$r) }}">{{ $r->employee_code }}</a></td>
                <td>{{ $r->employee_family_name }} {{ $r->employee_first_middle_name }}</td>
                <td class="text-end">{{ number_format($r->total_amount) }}</td>
                <td>{{ $r->status->value ?? $r->status }}</td>
                <td>{{ optional($r->submitted_at)->format('Y-m-d H:i') }}</td>
                <td>{{ optional($r->approved_at)->format('Y-m-d H:i') }}</td>
                <td>{{ optional($r->paid_at)->format('Y-m-d H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $reports->links() }}
</div>
@endsection