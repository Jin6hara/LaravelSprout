{{-- 管理者向け：宛先と確認状況 --}}
@isset($recipients)
<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">Recipients and Confirmation Status (Admin)</h2>
</div>
<div class="card mb-2">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Employee Code</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Confirmed At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recipients as $u)
                @php($cAt = $u->pivot?->confirmed_at)
                <tr>
                    <td>{{ $u->family_name }} {{ $u->first_name }}</td>
                    <td>{{ $u->employee_code }}</td>
                    <td>{{ $u->email }}</td>
                    <td>
                        @if($cAt)
                        <span class="badge text-bg-success d-inline-block text-center" style="min-width:90px;">Confirmed</span>
                        @else
                        <span class="badge text-bg-warning d-inline-block text-center" style="min-width:90px;">Unconfirmed</span>
                        @endif
                    </td>
                    <td>{{ $cAt ? \Carbon\Carbon::parse($cAt)->format('Y-m-d H:i') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endisset