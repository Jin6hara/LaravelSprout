@role('general')
<li class="nav-item"><a class="nav-link" href="{{ route('calendar.index') }}">Schedule</a></li>
<span class="nav-divider">|</span>
<li class="nav-item"><a class="nav-link" href="{{ route('expenses.edit') }}">CER</a></li>
<span class="nav-divider">|</span>
<li class="nav-item"><a class="nav-link" href="{{ route('schools.search') }}">School Map</a></li>
<span class="nav-divider">|</span>
<li class="nav-item"><a class="nav-link" href="{{ route('absence.edit', ['user' => auth()->user()->employee_code]) }}">Absense</a></li>
<span class="nav-divider">|</span>
<li class="nav-item"><a class="nav-link" href="{{ route('leave.apply.create') }}">ALP</a></li>
<span class="nav-divider">|</span>
<li class="nav-item"><a class="nav-link" href="{{ route('user.profile') }}">Profile</a></li>
<span class="nav-divider">|</span>
@endrole