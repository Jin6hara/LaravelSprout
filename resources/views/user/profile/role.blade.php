        
        {{-- 権限区分 --}}
        @php
        $roleLabels = ['admin' => '管理者', 'general' => '一般'];
        @endphp
        @if (Auth::user()->role === 'admin' && isset($user))
        <div class="card mt-4">
            <div class="card-header">
                <h4>権限区分</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered profile-table">
                    <colgroup>
                        <col style="width:140px">
                        <col>
                    </colgroup>
                    <tr>
                        <th>権限</th>
                        <td>{{ $roleLabels[$user->role] ?? $user->role }}</td>
                    </tr>
                </table>
                <div class="mt-3">
                    <a href="" class="btn btn-secondary btn-block">権限を編集</a>
                </div>
            </div>
        </div>
        @endif