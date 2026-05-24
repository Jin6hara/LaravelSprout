@role('admin|super_admin')

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

{{-- 4️⃣ Inbox & Create --}}
<li class="nav-item">
    <div class="btn-group btn-group-sm w-100">
        <a href="{{ route('post.sent') }}" class="btn btn-outline-secondary header-btn"> Sent </a>
        @php
        $inboxBadgeProps = [
        'endpoint' => route('api.inbox.unconfirmed_count'),
        'initialCount' => (int) ($inboxUnconfirmed ?? 0),
        'intervalMs' => 5000,
        ];
        @endphp
        <a href="{{ route('messages.index') }}"
            class="btn btn-outline-secondary header-btn position-relative">
            Inbox
            <span id="inboxBadge" data-props='@json($inboxBadgeProps)'></span>
        </a>
    </div>
</li>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('admin.dashboard') }}">
        Teacher
    </a>
</li>
<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('expenses.admin.report') }}">
        CER
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary header-btn position-relative">
        Notification
        <i class="bi bi-bell ms-1"></i>
        @php $unread = (int) ($unreadNotificationsCount ?? 0); @endphp
        @if($unread > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            {{ $unread }}
        </span>
        @endif
    </a>
</li>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('data.list') }}">
        Data
    </a>
</li>

@endrole
