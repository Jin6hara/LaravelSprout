@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">User + Schedule + Line + Detail — CSV Import</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if(session('errors') && is_array(session('errors')) && count(session('errors')))
    <div class="alert alert-warning">
        <div class="fw-bold">ログ</div>
        <ul class="mb-0">
            @foreach(session('errors') as $e)
            <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('csv.user_schedule_line.import') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">CSV ファイル</label>
                    <input type="file" name="csv_file" class="form-control" required>
                    <small class="text-muted">UTF-8 / 1行目ヘッダ必須（BOM付きでも可）</small>
                </div>

                <div class="col-12 form-check">
                    <input class="form-check-input" type="checkbox" name="update" id="update" value="1">
                    <label class="form-check-label" for="update">同一（user+期間+label）の Schedule は <b>更新</b> にする</label>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <h5>CSV ヘッダ例</h5>
        <pre class="bg-light p-2 small">
user_id,label,total_minutes,effective_start,effective_end,school_name,dow,start_time,end_time,detail_effective_start,detail_effective_end,lesson_start_time,lesson_code
000013,TS,465,2025-04-01,2026-03-31,Umeda GB,火,1500,2100,2025-04-01,2026-03-31,1500,BW
</pre>
        <small class="text-muted">
            ・user_id は6桁の社員コード（users.employee_code）<br>
            ・detail_effective_* が空なら line の期間をそのまま使用<br>
            ・lesson_start_time は 1500 など4桁でOK（内部で HH:MM:SS 化してマスタからID解決）<br>
            ・lesson_code は BW / EV / EE など
        </small>
    </div>
</div>
@endsection