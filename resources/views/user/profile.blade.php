@extends('layouts.app')

@section('content')
<div class="row">
    <aside class="col-sm-4 mb-5">
        <div class="card bg-info">
            <div class="card-header">
                <h3 class="card-title text-light">{{ $user->name }}</h3>
            </div>
            <div class="card-body text-center">
                <img class="rounded-circle img-fluid"
                     src="{{ asset('image/' . $user->profile_picture) }}"
                     alt="{{ $user->name }}のプロフィール画像">

                <div class="mt-3">
                    <a href="" class="btn btn-primary btn-block">ユーザ情報の編集</a>
                </div>
            </div>
        </div>
    </aside>

    <div class="col-sm-8">
        <ul class="nav nav-tabs nav-justified mb-3">
            <li class="nav-item">
                <a href="#" class="nav-link {{ Request::is('profile') ? 'active' : '' }}">基本情報</a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">スケジュール</a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">各種申請</a>
            </li>
        </ul>

        <div class="card">
            <div class="card-header">
                <h4>基本情報</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>社員コード</th>
                        <td>{{ $user->employee_code }}</td>
                    </tr>
                    <tr>
                        <th>名前</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th>性別</th>
                        <td>{{ ucfirst($user->gender) }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <th>電話番号</th>
                        <td>{{ $user->phone_number }}</td>
                    </tr>
                    <tr>
                        <th>住所</th>
                        <td>{{ $user->address }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
