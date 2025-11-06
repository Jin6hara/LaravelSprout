@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">User → Schedules CSV Import</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('csv.user_schedule.import') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">CSV ファイル</label>
                    <input type="file" name="csv_file" class="form-control" required>
                    <small class="text-muted">UTF-8 / 1行目ヘッダ必須</small>
                </div>

                <input class="form-check-input" type="checkbox" name="update" id="update" value="1">
                <label class="form-check-label" for="update">
                    既存分ある際は更新する
                </label>

                <div class="col-12">
                    <button class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <h5>CSV ヘッダ例（最低限）</h5>
        <pre class="bg-light p-2">
user_id,label,total_minutes,effective_start,effective_end
000013,TS,465,2025-04-01,2026-03-31
000014,TS,360,2025-04-01,2026-03-31
    </pre>
        <small class="text-muted">
            ※ <code>user_id</code> には 6桁の社員コードを入れてください（社員テーブルの <code>employee_code</code> と突合）。<br>
            ※ 列名は <code>employee_code</code> でもOK（いずれでもマッチします）。<br>
            ※ 作成した Schedule の ID は、この後の「Schedule Line CSV インポート」で <b>schedule_id</b> として使用（＝裏で line に紐付け）。<br>
        </small>
    </div>
</div>
@endsection