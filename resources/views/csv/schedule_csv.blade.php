@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-4 gap-3">
    <a href="{{ route('csv.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>CSV一覧に戻る
    </a>
    <h2 class="mb-0">Schedule CSV</h2>
</div>

{{-- 成功メッセージ --}}
@if (session('import_success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('import_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- バリデーション / パースエラー --}}
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><strong>エラー</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('import_errors'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><strong>インポートエラー（{{ count(session('import_errors')) }} 件）</strong>
        <ul class="mb-0 mt-1" style="max-height:300px;overflow-y:auto;">
            @foreach (session('import_errors') as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ========== Export ========== --}}
<div class="card mb-4 shadow-sm">
    <div class="card-header fw-bold">
        <i class="fas fa-file-export me-2 text-success"></i>CSV エクスポート
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            schedule_lines + schedule_details の全件を FileMaker 形式の CSV でダウンロードします。<br>
            ファイル名：<code>schedules_YYYYmmdd_His.csv</code>　文字コード：UTF-8<br>
            同一 schedule_line に複数 detail がある場合、1行目のみ schedule_line 情報を出力し、2行目以降は空白になります（FileMaker 形式）。<br>
            エクスポートした CSV をそのままインポートすることができます。
        </p>
        <a href="{{ route('csv.schedules.export') }}" class="btn btn-success">
            <i class="fas fa-download me-1"></i>CSV をダウンロード
        </a>
    </div>
</div>

{{-- ========== Import ========== --}}
<div class="card shadow-sm">
    <div class="card-header fw-bold">
        <i class="fas fa-file-import me-2 text-primary"></i>CSV インポート
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            FileMaker から出力した CSV、またはエクスポートした CSV をアップロードして schedule_lines / schedule_details に登録・更新します。<br>
            同一の <strong>user_id / dow / school_name / start_time / effective_start</strong> の schedule_line が存在する場合は更新、なければ新規作成です。<br>
            schedule_detail も同一の <strong>schedule_line_id / start_time / lesson_id / effective_start</strong> が存在する場合は更新、なければ新規作成です。<br>
            エラーが発生した場合は全件ロールバックされます。
        </p>

        {{-- カラム仕様 --}}
        <div class="mb-4">
            <h6 class="fw-bold">CSVヘッダー仕様（FileMaker 形式）</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered text-sm">
                    <thead class="table-light">
                        <tr>
                            <th>CSVカラム名</th>
                            <th>対応先</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>EndDate</code></td>                              <td>schedule_lines.effective_end</td>    <td>YYYY/M/D 形式。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>EndTime</code></td>                              <td>schedule_lines.end_time</td>         <td>H:i 形式。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>LineID</code></td>                               <td>FileMaker 側の識別子（一時キー）</td> <td>Laravel の ID とは別。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>StartDate</code></td>                            <td>schedule_lines.effective_start</td>  <td>YYYY/M/D 形式。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>StartTime</code></td>                            <td>schedule_lines.start_time</td>       <td>H:i 形式。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>ClassSlots::cClassName</code></td>               <td>lessons.fm_lesson_code → lesson_id</td> <td>trim して照合。見つからない場合はエラー</td></tr>
                        <tr><td><code>ClassSlots::EndDate</code></td>                  <td>schedule_details.effective_end</td>  <td>YYYY/M/D 形式</td></tr>
                        <tr><td><code>ClassSlots::LineID</code></td>                   <td>詳細の紐付け（一時キー）</td>          <td>空の場合は LineID を使用</td></tr>
                        <tr><td><code>ClassSlots::StartDate</code></td>                <td>schedule_details.effective_start</td><td>YYYY/M/D 形式</td></tr>
                        <tr><td><code>ClassSlots::StartTime</code></td>                <td>schedule_details.start_time</td>     <td>H:i 形式</td></tr>
                        <tr><td><code>Days::DayName</code></td>                        <td>schedule_lines.dow</td>              <td>Sunday=0〜Saturday=6。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>Employees ＭＡＩＮ::EmployeeCode</code></td>    <td>users.employee_code → user_id</td>   <td>見つからない場合はエラー。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>Schools::SchoolNumName</code></td>               <td>schedule_lines.school_name</td>      <td>"code - name" 形式。code で schools テーブルを検索し正式 school_name を使用。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>district_name</code></td>                        <td>districts.name → district_id</td>    <td><span class="badge bg-danger">必須</span> name で districts テーブルを検索。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                        <tr><td><code>department_name</code></td>                      <td>departments.name → department_id</td><td><span class="badge bg-danger">必須</span> name で departments テーブルを検索。2行目以降は空白（前行の値を引き継ぐ）</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small">
                ※ 全行バリデーション後にまとめてトランザクション保存します。エラーがある場合は全件ロールバックされます。<br>
                ※ LineID は FileMaker 側の識別子として使用します。Laravel の schedule_lines.id とは別です。<br>
                ※ Days::DayName が空の場合は StartDate から曜日を自動算出します。
            </p>
        </div>

        {{-- サンプルCSV --}}
        <div class="mb-4">
            <h6 class="fw-bold">CSVサンプル（FileMaker 形式）</h6>
            <pre class="bg-light p-2 rounded small" style="overflow-x:auto;">EndDate,EndTime,LineID,StartDate,StartTime,ClassSlots::cClassName,ClassSlots::EndDate,ClassSlots::LineID,ClassSlots::StartDate,ClassSlots::StartTime,Days::DayName,Employees ＭＡＩＮ::EmployeeCode,Schools::SchoolNumName,district_name,department_name
2027/03/31,21:20,71446,2026/04/13,15:20,AN,2027/03/31,71446,2026/04/13,15:35,Monday,014064,103 - Kusatsu,Kinki,ECC
,,,,,BX,2027/03/31,71446,2026/04/13,16:40,,,,
,,,,,CP,2027/03/31,71446,2026/04/13,17:55,,,,
,,,,,Enjoy Elem B,2027/03/31,71446,2026/04/13,19:10,,,,</pre>
        </div>

        {{-- インポートフォーム --}}
        <form action="{{ route('csv.schedules.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="csv_file" class="form-label fw-bold">CSVファイルを選択</label>
                <input type="file" id="csv_file" name="csv_file"
                       class="form-control @error('csv_file') is-invalid @enderror"
                       accept=".csv,.txt" required>
                @error('csv_file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">UTF-8（BOM 付き可）、1 行目はヘッダー行。FileMaker から出力した CSV またはエクスポートした CSV を使用してください。</div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload me-1"></i>インポート実行
            </button>
        </form>
    </div>
</div>
@endsection
