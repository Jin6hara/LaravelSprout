        {{-- 雇用情報 --}}
        @php
        // 雇用情報が複数ある場合に備えて最新開始日の1件を採用
        $employment = optional($user->employmentTerms)->sortByDesc('start_date')->first();
        @endphp
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
                @if (Auth::user()->role === 'admin' && isset($employment))
                <div class="mt-3">
                    <a href="" class="btn btn-secondary btn-block">雇用情報を編集</a>
                </div>
                @endif
            </div>
        </div>