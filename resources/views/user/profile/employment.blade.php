        {{-- 雇用情報 --}}
        <div class="card mt-4">
            <div class="card-header">
                <h4>雇用情報</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered profile-table">
                    <colgroup>
                        <col style="width:140px">
                        <col>
                    </colgroup>
                    <tr>
                        <th>雇用状態</th>
                        <td>{{ $user->employment_state }}</td>
                    </tr>
                    @if($activeLeaves->isNotEmpty())
                    @foreach($activeLeaves as $leave)
                    <tr>
                        <th>休職期間</th>
                        <td>{{ $leave->start_date?->format('Y/m/d') ?? '—' }} 〜 {{ $leave->end_date?->format('Y/m/d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>休職理由</th>
                        <td>{{ $leave->reason ?? '—' }}</td>
                    </tr>
                    @endforeach
                    @endif
                    <tr>
                        <th>雇用形態</th>
                        <td>{{ $employment?->type_name ?? '—' }}（{{ $employment?->type_code ?? '—' }}）</td>
                    </tr>
                    <tr>
                        <th>入社日</th>
                        <td>{{ $employment?->start_date?->format('Y/m/d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>退職日</th>
                        <td>{{ $employment?->end_date?->format('Y/m/d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>{{ $employment?->note ?? '—' }}</td>
                    </tr>
                </table>
                @role('admin|super_admin')
                <div class="mt-3">
                    <a href="{{ route('employment_terms.details', $user) }}" class="btn btn-secondary btn-block">雇用履歴・詳細</a>
                </div>
                @endrole
            </div>
        </div>