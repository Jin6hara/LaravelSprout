@extends('layouts.app')

@section('content')
<div class="container">
    <h2>権限変更申請</h2>

    <div class="card mt-3">
        <div class="card-body">
            {{-- 本人情報 --}}
            <table class="table table-bordered">
                <colgroup>
                    <col style="width:140px;"> <!-- ← 好きな幅に -->
                    <col>
                </colgroup>
                <tr>
                    <th>社員コード</th>
                    <td>{{ $user->employee_code }}</td>
                </tr>
                <tr>
                    <th>氏名</th>
                    <td>{{ $user->name }}</td>
                </tr>
                <tr>
                    <th>現在の権限</th>
                    <td>{{ $user->role_label }}</td>
                </tr>
            </table>

            {{-- 申請フォーム --}}
            <form action="{{ route('roleChange.apply', $user) }}" method="POST"> {{--  --}}
                @csrf

                <div class="mb-3">
                    <label for="role" class="form-label">変更後の権限</label>
                    <select name="role" id="role" class="form-select" required>
                        @foreach ($availableRoles as $role)
                        <option value="{{ $role }}" {{ $currentRole === $role ? 'selected' : '' }}>
                            {{ $user->map[$role] ?? $role }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="reason" class="form-label">申請理由</label>
                    <input type="text" name="reason" id="reason" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">申請する</button>
            </form>
        </div>
    </div>
</div>
@endsection