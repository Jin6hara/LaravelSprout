@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">管理者ダッシュボード</h1>
        <a href="{{ route('register.showForm') }}" class="btn btn-sm btn-outline-secondary">
            Register
        </a>
    </div>
    <p class="text-center text-muted mb-4">ここでは管理者向けの情報を表示します。</p>

    {{-- 検索フォーム --}}
    <form method="GET" action="{{ route('admin.search') }}" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="search_word" class="form-label">検索ワード</label>
                <input type="text"
                    id="search_word"
                    name="search_word"
                    value="{{ $word ?? '' }}"
                    class="form-control"
                    placeholder="社員コード／氏名／電話番号の一部で検索">
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">検索対象（1〜3件）</label>
                @php
                $selected = collect($fields ?? []);
                @endphp
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input field-check" type="checkbox" id="f-emp"
                            name="fields[]" value="employee_code"
                            {{ $selected->contains('employee_code') ? 'checked' : '' }}>
                        <label class="form-check-label" for="f-emp">employee_code</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input field-check" type="checkbox" id="f-name"
                            name="fields[]" value="name"
                            {{ $selected->contains('name') ? 'checked' : '' }}>
                        <label class="form-check-label" for="f-name">name</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input field-check" type="checkbox" id="f-phone"
                            name="fields[]" value="phone_number"
                            {{ $selected->contains('phone_number') ? 'checked' : '' }}>
                        <label class="form-check-label" for="f-phone">phone_number</label>
                    </div>
                </div>
                <small class="text-muted">何も選択しない場合は3項目すべてを検索対象にします。</small>
            </div>

            <div class="col-md-6 ustify-content-end mb-3 gap-2">
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">条件をクリア</a>
            </div>

            {{-- 並び替え --}}
            <div class="col-md-6 justify-content-end mb-3 gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        並び替え
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item"
                                href="{{ request()->fullUrlWithQuery(['sort'=>'updated_at','dir'=>'desc','page'=>1]) }}">
                                更新日（新→旧）
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="{{ request()->fullUrlWithQuery(['sort'=>'employee_code','dir'=>'asc','page'=>1]) }}">
                                社員コード（昇順）
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="{{ request()->fullUrlWithQuery(['sort'=>'employee_code','dir'=>'desc','page'=>1]) }}">
                                社員コード（降順）
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </form>

    {{-- ユーザー一覧 --}}
    @include('user.userList', ['users' => $users])
</div>

{{-- 最大3つまでチェックできるようにする（将来項目が増えても安全） --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const max = 3;
        const boxes = document.querySelectorAll('.field-check');
        boxes.forEach(box => {
            box.addEventListener('change', () => {
                const checked = Array.from(boxes).filter(b => b.checked);
                if (checked.length === 0) {
                    // 最低1件は維持（UX保護）。外したら直ちに戻す
                    box.checked = true;
                } else if (checked.length > max) {
                    alert('検索対象は最大3件までです。');
                    box.checked = false;
                }
            });
        });
    });
</script>
@endsection