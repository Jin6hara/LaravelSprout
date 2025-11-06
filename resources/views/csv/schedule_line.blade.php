@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">Schedule CSV Import</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form action="{{ route('cvs.schedule_line.import') }}" method="POST" enctype="multipart/form-data" class="card card-body">
        @csrf

        <div class="mb-3">
            <label class="form-label">対象 Schedule ID（任意）</label>
            <input type="number" name="schedule_id" class="form-control" placeholder="2 など">
        </div>

        <div class="mb-3">
            <label class="form-label">CSV ファイル</label>
            <input type="file" name="csv_file" class="form-control" required>
            <small class="text-muted">※ UTF-8 / 1行目ヘッダ必須</small>
        </div>

        <button class="btn btn-primary">Import</button>
    </form>
</div>
@endsection