        {{-- 権限区分 --}}
        @role('admin|super_admin')
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
                        <td>{{ $user->role_label }}</td>
                    </tr>
                    <tr>
                        <th>所属地区</th>
                        <td>{{ $user->district?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>所属部署</th>
                        <td>{{ $user->department?->name ?? '—' }}</td>
                    </tr>
                    @if($user->isAdmin())
                    <tr>
                        <th>管理範囲</th>
                        <td>
                            @if($user->managementScopes->isEmpty())
                                <span class="text-muted">—</span>
                            @else
                                <ul class="mb-0 ps-3">
                                    @foreach($user->managementScopes as $scope)
                                    <li>
                                        {{ $scope->district?->name ?? '—' }}
                                        ／
                                        {{ $scope->department?->name ?? '—' }}
                                    </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                    @endif
                </table>
                <div class="mt-3">
                    <a href="{{ route('admin.user.roleChange', $user) }}" class="btn btn-secondary btn-block">権限を編集</a>
                </div>
            </div>
        </div>
        @endrole
