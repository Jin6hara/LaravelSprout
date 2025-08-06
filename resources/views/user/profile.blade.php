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
                    <a href="" class="btn btn-primary btn-block">写真の編集</a>
                </div>
            </div>
        </div>
    </aside>

    <div class="col-sm-8">
        <ul class="nav nav-tabs nav-justified mb-3">
            <li class="nav-item">
                <a href="#" class="nav-link {{ Request::is('profile') || Request::is('profile/*') ? 'active' : '' }}">
                    基本情報
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link {{ Request::is('schedule') || Request::is('schedule/*') ? 'active' : '' }}">
                    スケジュール
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    各種申請
                </a>
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
                        <td>{{ $user->employee_code }}</td><td></td>
                    </tr>
                    <tr>
                        <th>名前</th>
                        <td>{{ $user->name }}</td><td></td>
                    </tr>
                    <tr>
                        <th>性別</th>
                        <td>{{ ucfirst($user->gender) }}</td><td></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>
                            <span id="email_display">{{ $user->email }}</span>
                            <input type="text" id="email_input" class="form-control d-none" value="{{ $user->email }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="email_edit" onclick="editField('email')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="email_save" onclick="saveField('email')">✅</button>  
                            <button class="btn btn-sm btn-danger d-none" id="email_cancel" onclick="cancelField('email')">❌</button>                         
                        </td>
                    </tr>
                    <tr>
                        <th>電話番号</th>
                        <td>
                            <span id="phone_number_display">{{ $user->phone_number }}</span>
                            <input type="text" id="phone_number_input" class="form-control d-none" value="{{ $user->phone_number }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="phone_number_edit" onclick="editField('phone_number')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="phone_number_save" onclick="saveField('phone_number')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="phone_number_cancel" onclick="cancelField('phone_number')">❌</button>
                        </td>
                    </tr>
                    <tr>
                        <th>住所</th>
                            <td>
                            <span id="address_display">{{ $user->address }}</span>
                            <input type="text" id="address_input" class="form-control d-none" value="{{ $user->address }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="address_edit" onclick="editField('address')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="address_save" onclick="saveField('address')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="address_cancel" onclick="cancelField('address')">❌</button>
                        </td>
                    </tr>
                    <tr>
                        <th>自己紹介</th>
                            <td>
                            <span id="self_introduction_display">{{ $user->self_introduction }}</span>
                            <input type="text" id="self_introduction_input" class="form-control d-none" value="{{ $user->self_introduction }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="self_introduction_edit" onclick="editField('self_introduction')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="self_introduction_save" onclick="saveField('self_introduction')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="self_introduction_cancel" onclick="cancelField('self_introduction')">❌</button>
                        </td>
                    </tr>
                </table>
                <div class="mt-3">
                    <a href="" class="btn btn-primary btn-block">情報の編集</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editField(field) {
    document.getElementById(`${field}_display`).classList.add('d-none');
    document.getElementById(`${field}_input`).classList.remove('d-none');
    document.getElementById(`${field}_edit`).classList.add('d-none');
    document.getElementById(`${field}_save`).classList.remove('d-none');
    document.getElementById(`${field}_cancel`).classList.remove('d-none');

    // 編集前の値を保持（キャンセル時に使う）
    const input = document.getElementById(`${field}_input`);
    input.setAttribute('data-original', input.value);
}

function saveField(field) {
    const value = document.getElementById(`${field}_input`).value;

    fetch("{{ route('user.updateField') }}", {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ field, value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.message) {
            document.getElementById(`${field}_display`).textContent = value;
            document.getElementById(`${field}_display`).classList.remove('d-none');
            document.getElementById(`${field}_input`).classList.add('d-none');
            document.getElementById(`${field}_edit`).classList.remove('d-none');
            document.getElementById(`${field}_save`).classList.add('d-none');
            document.getElementById(`${field}_cancel`).classList.add('d-none');
        } else {
            alert(data.error || '更新に失敗しました');
        }
    });
}

function cancelField(field) {
    const display = document.getElementById(`${field}_display`);
    const input = document.getElementById(`${field}_input`);
    const editBtn = document.getElementById(`${field}_edit`);
    const saveBtn = document.getElementById(`${field}_save`);
    const cancelBtn = document.getElementById(`${field}_cancel`);

    // 入力値を元に戻す
    const originalValue = input.getAttribute('data-original');
    input.value = originalValue;

    // 表示切り替え
    display.classList.remove('d-none');
    input.classList.add('d-none');
    saveBtn.classList.add('d-none');
    cancelBtn.classList.add('d-none');
    editBtn.classList.remove('d-none');
}
</script>

@endsection