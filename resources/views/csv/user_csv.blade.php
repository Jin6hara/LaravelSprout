@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-4 gap-3">
    <a href="{{ route('csv.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>CSV一覧に戻る
    </a>
    <h2 class="mb-0">User CSV</h2>
</div>

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

{{-- ========== Export ========== --}}
<div class="card mb-4 shadow-sm">
    <div class="card-header fw-bold">
        <i class="fas fa-file-export me-2 text-success"></i>CSV エクスポート
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            users テーブルの全件を CSV でダウンロードします。<br>
            ファイル名：<code>users_YYYYmmdd_His.csv</code>　文字コード：UTF-8<br>
            <span class="text-danger"><i class="fas fa-lock me-1"></i>password はエクスポートされません。</span>
        </p>
        <a href="{{ route('csv.users.export') }}" class="btn btn-success">
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
            CSV ファイルをアップロードして users テーブルに登録・更新します。<br>
            <strong>id</strong> が一致するレコードを更新します。id が空、または一致しない場合は
            <code>employee_code</code>、<code>email</code> の順で既存レコードを探し、
            見つかれば更新、見つからなければ新規作成します。<br>
            <span class="text-danger"><i class="fas fa-lock me-1"></i>password はインポート対象外です。新規作成時は安全な初期パスワードが自動設定されます。</span>
        </p>

        {{-- カラム仕様 --}}
        <div class="mb-4">
            <h6 class="fw-bold">対象カラム・バリデーション仕様</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>カラム名</th>
                            <th>必須</th>
                            <th>型・制約</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>id</code></td>                      <td>任意</td><td>整数</td>                    <td>一致すれば更新。未一致の場合は employee_code / email で update 判定</td></tr>
                        <tr><td><code>family_name</code></td>              <td><span class="badge bg-danger">必須</span></td><td>文字列 max:255</td>    <td></td></tr>
                        <tr><td><code>first_name</code></td>               <td><span class="badge bg-danger">必須</span></td><td>文字列 max:255</td>    <td></td></tr>
                        <tr><td><code>middle_name</code></td>              <td>任意</td><td>文字列 max:255</td>    <td>空白は null 保存</td></tr>
                        <tr><td><code>name_in_kana</code></td>             <td>任意</td><td>文字列 max:255</td>    <td>空白は null 保存</td></tr>
                        <tr><td><code>email</code></td>                    <td><span class="badge bg-danger">必須</span></td><td>メールアドレス</td>   <td>他ユーザーと重複不可</td></tr>
                        <tr><td><code>gender</code></td>                   <td>任意</td><td>male / female / other / unknown</td><td>空白は unknown として保存</td></tr>
                        <tr><td><code>note</code></td>                     <td>任意</td><td>文字列</td>             <td>空白は null 保存</td></tr>
                        <tr><td><code>employee_code</code></td>            <td><span class="badge bg-danger">必須</span></td><td>文字列 6文字固定</td> <td>他ユーザーと重複不可</td></tr>
                        <tr><td><code>address</code></td>                  <td>任意</td><td>文字列 max:255</td>    <td>空白は null 保存</td></tr>
                        <tr><td><code>phone_number</code></td>             <td>任意</td><td>文字列 max:255</td>    <td>空白は null 保存</td></tr>
                        <tr><td><code>district_name</code></td>            <td><span class="badge bg-danger">必須</span></td><td>文字列</td>           <td>districts.name から district_id を解決。存在しない場合はエラー</td></tr>
                        <tr><td><code>department_name</code></td>          <td><span class="badge bg-danger">必須</span></td><td>文字列</td>           <td>departments.name から department_id を解決。存在しない場合はエラー</td></tr>
                        <tr><td><code>role</code></td>                     <td><span class="badge bg-danger">必須</span></td><td>文字列</td>           <td>roles.name に存在すること。空なら general として扱う</td></tr>
                        <tr><td><code>employment_start_date</code></td>    <td>任意</td><td>日付 (YYYY-MM-DD)</td> <td>空なら employment_terms を作成しない</td></tr>
                        <tr><td><code>employment_end_date</code></td>      <td>任意</td><td>日付 (YYYY-MM-DD)</td> <td>空白は null 保存</td></tr>
                        <tr><td><code>employment_type_name</code></td>     <td><span class="badge bg-warning text-dark">start_date 入力時必須</span></td><td>文字列 max:255</td><td>雇用形態名。DB NOT NULL のため start_date がある場合は必須</td></tr>
                        <tr><td><code>employment_type_code</code></td>     <td><span class="badge bg-warning text-dark">start_date 入力時必須</span></td><td>文字列 max:255</td><td>雇用形態コード。DB NOT NULL のため start_date がある場合は必須</td></tr>
                        <tr><td><code>employment_note</code></td>          <td>任意</td><td>文字列 max:255</td>    <td>空白は null 保存</td></tr>
                        <tr><td><code>created_at</code></td>               <td>任意</td><td>—</td>                 <td>インポート時は無視</td></tr>
                        <tr><td><code>updated_at</code></td>               <td>任意</td><td>—</td>                 <td>インポート時は無視</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small">
                ※ ヘッダー行の 1 行目はカラム名と完全一致（上記のカラム名）が必要です。<br>
                ※ 空白セル・空文字・trim 後に空になる値は <code>null</code> として保存されます。<br>
                ※ 全行バリデーション後にまとめてトランザクション保存します。エラーがある場合は全件ロールバックされます。
            </p>
        </div>

        {{-- サンプルCSV --}}
        <div class="mb-4">
            <h6 class="fw-bold">CSVサンプル</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>id</th>
                            <th>family_name</th>
                            <th>first_name</th>
                            <th>middle_name</th>
                            <th>name_in_kana</th>
                            <th>email</th>
                            <th>gender</th>
                            <th>note</th>
                            <th>employee_code</th>
                            <th>address</th>
                            <th>phone_number</th>
                            <th>district_name</th>
                            <th>department_name</th>
                            <th>role</th>
                            <th>employment_start_date</th>
                            <th>employment_end_date</th>
                            <th>employment_type_name</th>
                            <th>employment_type_code</th>
                            <th>employment_note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Yamada</td>
                            <td>Taro</td>
                            <td class="text-muted">空欄</td>
                            <td>やまだたろう</td>
                            <td>taro@example.com</td>
                            <td>male</td>
                            <td class="text-muted">空欄</td>
                            <td>000001</td>
                            <td>東京都渋谷区1-1</td>
                            <td>090-1234-5678</td>
                            <td>Tokyo</td>
                            <td>Sales</td>
                            <td>general</td>
                            <td>2024-04-01</td>
                            <td class="text-muted">空欄</td>
                            <td>Full-time</td>
                            <td>FT</td>
                            <td class="text-muted">空欄</td>
                        </tr>
                        <tr>
                            <td class="text-muted">空欄</td>
                            <td>Suzuki</td>
                            <td>Hanako</td>
                            <td class="text-muted">空欄</td>
                            <td>すずきはなこ</td>
                            <td>hanako@example.com</td>
                            <td>female</td>
                            <td class="text-muted">空欄</td>
                            <td>000002</td>
                            <td class="text-muted">空欄</td>
                            <td class="text-muted">空欄</td>
                            <td>Osaka</td>
                            <td>Admin</td>
                            <td>admin</td>
                            <td>2024-04-01</td>
                            <td class="text-muted">空欄</td>
                            <td class="text-muted">空欄</td>
                            <td class="text-muted">空欄</td>
                            <td class="text-muted">空欄</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mb-0">
                ※ <code>password</code> はインポート・エクスポート対象外です。新規作成時は初期パスワードが自動設定されます。
            </p>
        </div>

        {{-- インポートフォーム --}}
        <form action="{{ route('csv.users.import') }}" method="POST" enctype="multipart/form-data">
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
