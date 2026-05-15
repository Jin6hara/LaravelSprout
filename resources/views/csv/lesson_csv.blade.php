@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-4 gap-3">
    <a href="{{ route('csv.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>CSV一覧に戻る
    </a>
    <h2 class="mb-0">Lesson CSV</h2>
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
            lessons テーブルの全件を CSV でダウンロードします。<br>
            ファイル名：<code>lessons_YYYYmmdd_His.csv</code>　文字コード：UTF-8
        </p>
        <a href="{{ route('csv.lessons.export') }}" class="btn btn-success">
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
            CSV ファイルをアップロードして lessons テーブルに登録・更新します。<br>
            <strong>id</strong> が入力されていればその id のレコードを更新（存在しなければ新規作成）、空の場合は新規作成です。
        </p>

        {{-- カラム仕様 --}}
        <div class="mb-4">
            <h6 class="fw-bold">対象カラム・バリデーション仕様</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered text-sm">
                    <thead class="table-light">
                        <tr>
                            <th>カラム名</th>
                            <th>必須</th>
                            <th>型・制約</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>id</code></td>              <td>任意</td><td>整数</td>          <td>空なら新規作成。指定した場合は update 判定に使用</td></tr>
                        <tr><td><code>lesson_name</code></td>      <td>任意</td><td>文字列 max:255</td><td>空白は null 保存</td></tr>
                        <tr><td><code>lesson_code</code></td>      <td>任意</td><td>文字列 max:255</td><td>空白は null 保存</td></tr>
                        <tr><td><code>lesson_minute</code></td>    <td>任意</td><td>整数 min:0</td>   <td>30 / 40 / 45 など。空白は null 保存</td></tr>
                        <tr><td><code>note</code></td>             <td>任意</td><td>文字列 max:1000</td><td>空白は null 保存</td></tr>
                        <tr><td><code>lesson_type</code></td>      <td>任意</td><td>文字列 max:255</td><td>空白は null 保存</td></tr>
                        <tr><td><code>ps_unique_lesson_code</code></td><td>任意</td><td>文字列 max:255</td><td>CSV import 参照用。空白は null 保存</td></tr>
                        <tr><td><code>fm_lesson_code</code></td>   <td>任意</td><td>文字列 max:255</td><td>CSV import 参照用。空白は null 保存</td></tr>
                        <tr><td><code>created_at</code></td>       <td>任意</td><td>—</td>             <td>インポート時は無視</td></tr>
                        <tr><td><code>updated_at</code></td>       <td>任意</td><td>—</td>             <td>インポート時は無視</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small">
                ※ ヘッダー行の 1 行目はカラム名と完全一致（上記のカラム名）が必要です。<br>
                ※ 空白セル・空文字・trim 後に空になる値は <code>null</code> として保存されます。<br>
                ※ 全行バリデーション後にまとめてトランザクション保存します。
            </p>
        </div>

        {{-- ヘッダー例 --}}
        <div class="mb-4">
            <h6 class="fw-bold">CSVヘッダー例</h6>
            <pre class="bg-light p-2 rounded small">id,lesson_name,lesson_code,note,lesson_minute,lesson_type,ps_unique_lesson_code,fm_lesson_code,created_at,updated_at
1,Global,BW,,45,kids,,,
,NewLesson,NL,,30,Adults,PS001,FM001,</pre>
        </div>

        {{-- インポートフォーム --}}
        <form action="{{ route('csv.lessons.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="csv_file" class="form-label fw-bold">CSVファイルを選択</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control @error('csv_file') is-invalid @enderror" accept=".csv,.txt" required>
                @error('csv_file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">UTF-8（BOM 付き可）、1 行目はヘッダー行。</div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload me-1"></i>インポート実行
            </button>
        </form>
    </div>
</div>
@endsection
