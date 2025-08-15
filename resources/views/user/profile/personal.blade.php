        {{-- 本人情報 --}}
        <div class="card mt-4">
            <div class="card-header">
                <h4>本人情報</h4>
            </div>
            <div class="card-body">
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
                        <th>名前</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th>性別</th>
                        <td>{{ $user->gender_label }}</td>
                    </tr>
                </table>
                @if (Auth::user()->role === 'admin' && isset($user))
                <div class="mt-3">
                    <a href="" class="btn btn-secondary btn-block">本人情報を編集</a>
                </div>
                @endif
            </div>
        </div>