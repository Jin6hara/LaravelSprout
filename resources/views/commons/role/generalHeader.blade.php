@unlessrole('admin|super_admin')

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('calendar.index') }}">
        Schedule
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('expenses.edit') }}">
        CER
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a href="{{ route('overtime.index') }}" class="btn btn-sm btn-outline-secondary header-btn">
        OT Chance
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('schools.search') }}">
        School<br>Map
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('absence.edit', ['user' => auth()->user()->employee_code]) }}">
        Absence
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('leave.apply.create') }}">
        ALP
    </a>
</li>

<span class="nav-divider">|</span>

@can('teacher.viewAll')
<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('user.master_list') }}">
        Teacher
    </a>
</li>

<span class="nav-divider">|</span>
@endcan

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('user.profile') }}">
        Profile
    </a>
</li>

<span class="nav-divider">|</span>

<li class="nav-item">
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
</li>

<span class="nav-divider">|</span>
@endrole
