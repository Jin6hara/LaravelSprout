@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">User → Schedule → Line → Detail 一括CSVインポート</h2>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('csv.user_schedule_line.import') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">CSVファイル</label>
                    <input type="file" name="csv_file" class="form-control" required>
                    <small class="text-muted">
                        UTF-8 / 1行目ヘッダ必須。BOM付きでも読めます。<br>
                        時刻は <code>H:i</code>（例 15:00）、日付は <code>YYYY-MM-DD</code>。
                    </small>
                </div>

                <div class="col-12 form-check">
                    <input class="form-check-input" type="checkbox" name="update" id="update" value="1">
                    <label class="form-check-label" for="update">同一キー（user+契約期間+label / lineキー / detailキー）は <b>更新</b>扱い</label>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <h5>ヘッダ例（表示名でも実カラム名でもOK / 一部別名も可）</h5>
        <pre class="bg-light p-2 mb-2">
Employ Code ('user_id'),Type ('label'),Total Minutes ('total_minutes'),Contract Start ('effective_start'),Contract End ('effective_end'),
School ('school_name'),DOW ('dow'),Shift From ('start_time'),Shift To ('end_time'),Line Start ('effective_start'),Line End ('effective_end'),
Start At ('lesson_start_times'),Lesson Name ('lesson_code'),Lesson Start ('effective_start'),Lesson End ('effective_end')
000013,TS,465,2025-04-01,2026-03-31,Umeda GB,7:日,15:00,21:00,2025-04-01,2026-03-31,15:00,BW,2025-04-01,2026-03-31
</pre>
        <small class="text-muted">
            ※ DOW は <b>1:月〜6:土, 7:日</b> を想定（システム内では <b>7→0（日）</b> に変換）<br>
            ※ DB格納は TIME が秒を持つため、内部的に <b>「H:i:00」</b> で保存。UI表示は H:i でOK。<br>
            ※ Lesson Start At は既存の <code>lesson_start_times.start_time</code>（5分刻み）に存在する値を指定してください。
        </small>
    </div>
</div>

<div class="container mt-5">
    <h2 class="mb-3">User → Schedule → Line → Detail 一括エクスポート</h2>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('csv.user_schedule_line.export.download') }}" method="GET" class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Employee Code（6桁／部分一致も可）</label>
                    <input type="text" name="employee_code" class="form-control form-control-sm" placeholder="000013">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Schedule ID</label>
                    <input type="number" name="schedule_id" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Detail From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Detail To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active_only" id="active_only" value="1">
                        <label class="form-check-label" for="active_only">Active schedules only</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm">CSV をダウンロード</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-3">
        <small class="text-muted">
            形式はインポートテンプレと同じです（Excelテンプレへ貼り付け可）。<br>
            DOW はエクスポート時に「1:月...7:日」に整形されます。
        </small>
    </div>
</div>
@endsection