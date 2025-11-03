@role('general')

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

<li class="nav-item">
    <a class="btn btn-sm btn-outline-secondary header-btn" href="{{ route('user.profile') }}">
        Profile
    </a>
</li>

<span class="nav-divider">|</span>
@endrole