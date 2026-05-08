{{--
    Scope selector dropdown for admin / super_admin.
    $scopeSelectorData は AppServiceProvider の view composer から注入される。
    - adminScopes: ログインユーザーに紐づく UserManagementScope コレクション（district / department eager loaded）
    - selectedScopeId: 現在選択中の scope id（null の場合は未選択）
--}}
@php
    $scopes          = $scopeSelectorData['adminScopes'];
    $selectedScopeId = $scopeSelectorData['selectedScopeId'];
    $selected        = $scopes->firstWhere('id', $selectedScopeId);
@endphp

<div class="dropdown">
    <button class="btn btn-sm btn-outline-warning dropdown-toggle header-btn"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
        @if($selected)
            {{ $selected->district->name }} / {{ $selected->department->name }}
        @else
            スコープ未選択
        @endif
    </button>
    <ul class="dropdown-menu dropdown-menu-dark">
        @forelse($scopes as $scope)
        <li>
            <form method="POST" action="{{ route('current-scope.store') }}">
                @csrf
                <input type="hidden" name="scope_id" value="{{ $scope->id }}">
                <button type="submit"
                        class="dropdown-item {{ $scope->id === $selectedScopeId ? 'active' : '' }}">
                    {{ $scope->district->name }} / {{ $scope->department->name }}
                </button>
            </form>
        </li>
        @empty
        <li><span class="dropdown-item text-muted">割り当てなし</span></li>
        @endforelse
    </ul>
</div>
