@role('admin|super_admin')
<style>
    .header-btn {
        /* btn-sm の雰囲気を維持しつつ高さを統一 */
        display: inline-flex;
        color: #cfcfcfff;
        align-items: center;
        justify-content: center;
        height: 36px;
        /* ← 統一高さ（必要なら 32〜40px で微調整OK） */
        padding: 1px 5px !important;
        /* ←ここを修正 */
        /* ← 横パディングを 1px に固定 */
        line-height: 1.1;
        /* 複数行の詰め調整 */
        white-space: normal;
        /* 改行を有効化 */
        text-align: center;
        margin: 0 1px;
    }

    /* li の間隔を少しだけ詰める（任意） */
    .navbar .nav-item {
        margin-right: .25rem;
    }

    .navbar .nav-item:last-child {
        margin-right: 0;
    }
</style>

{{-- 1️⃣ Shift Assigner & Forecast --}}
<li class="nav-item">
    <div class="btn-group btn-group-sm w-100">
        <a href="{{ route('calendar.edit') }}" class="btn btn-outline-secondary header-btn">Shift<br>Assigner</a>
        <a href="{{ route('calendar.forecast') }}" class="btn btn-outline-secondary header-btn">Forecast</a>
    </div>
</li>

{{-- 2️⃣ Absence Manager & Absence --}}
<li class="nav-item">
    <div class="btn-group btn-group-sm w-100">
        <a href="{{ route('leaves.edit') }}" class="btn btn-outline-secondary header-btn">Absence<br>Manager</a>
        <a href="{{ route('calendar.leaves') }}" class="btn btn-outline-secondary header-btn">Absence</a>
    </div>
</li>

{{-- 3️⃣ Schedule Editor & Schedule --}}
<li class="nav-item">
    <div class="btn-group btn-group-sm w-100">
        <a href="{{ route('schedules.edit') }}" class="btn btn-outline-secondary header-btn">Schedule<br>Editor</a>
        <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary header-btn">Schedule</a>
    </div>
</li>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('admin.dashboard') }}">
        Teacher
    </a>
</li>
<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('register.showForm') }}">
        Register
    </a>
</li>
<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('expenses.admin.report') }}">
        CER
    </a>
</li>
<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('expenses.admin.report') }}">
        Absence<br>Report
    </a>
</li>

<li class="nav-item">
    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
    <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary header-btn position-relative">
        Notification
        <i class="bi bi-bell ms-1"></i>
        @if($unread > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            {{ $unread }}
        </span>
        @endif
    </a>
</li>

@endrole