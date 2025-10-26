@role('admin|super_admin')
<style>
    .nav-divider {
        display: inline-flex;
        align-items: center;
        /* 垂直方向の中央揃え */
        color: #9ca3af;
        /* 落ち着いた灰色（Bootstrap準拠） */
        margin: 1px;
        /* 左右の余白 */
        font-weight: 600;
        /* 少し太字でバランス良く */
        font-size: 1rem;
        /* 文字サイズ調整 */
        line-height: 1;
        /* 中央揃えをきれいに */
    }
</style>
<li class="nav-item"><a class="nav-link" href="{{ route('calendar.edit') }}">Shift Assigner</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('calendar.forecast') }}">Forecast</a></li>

<span class="nav-divider">|</span>

<li class="nav-item"><a class="nav-link" href="{{ route('leaves.edit') }}">Absence Manager</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('calendar.leaves') }}">Absence</a></li>

<span class="nav-divider">|</span>

<li class="nav-item"><a class="nav-link" href="{{ route('schedules.edit') }}">Schedule Editor</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('calendar.index') }}">Schedule</a></li>

<span class="nav-divider">|</span>

<li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">Teacher</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('register.showForm') }}">Register</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('expenses.admin.report') }}">CER</a></li>
<li class="nav-item"><a class="nav-link" href="{{ route('expenses.admin.report') }}">Absence Report</a></li>


<li class="nav-item">
    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
    <a href="{{ route('notifications.index') }}" class="nav-link position-relative">
        Notification
        <i class="bi bi-bell"></i>
        @if($unread > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            {{ $unread }}
        </span>
        @endif
    </a>
</li>

<span class="nav-divider">|</span>
@endrole