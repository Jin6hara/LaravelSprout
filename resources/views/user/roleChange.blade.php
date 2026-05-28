@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Role Change Request</h2>

    <div class="card mt-3">
        <div class="card-body role-change-panel">
            {{-- 本人情報 --}}
            <table class="table table-bordered">
                <colgroup>
                    <col style="width:140px;">
                    <col>
                </colgroup>
                <tr>
                    <th>Employee Code</th>
                    <td>{{ $user->employee_code }}</td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td>{{ $user->name }}</td>
                </tr>
                <tr>
                    <th>Current Role</th>
                    <td>{{ $currentRole ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Current District</th>
                    <td>{{ $user->district?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Current Department</th>
                    <td>{{ $user->department?->name ?? '—' }}</td>
                </tr>
                @if($user->isAdmin())
                <tr>
                    <th>Current Management Scope</th>
                    <td>
                        @if($user->managementScopes->isEmpty())
                            <span class="text-muted">—</span>
                        @else
                            @foreach($user->managementScopes as $scope)
                                {{ $scope->district?->name ?? '—' }} ／ {{ $scope->department?->name ?? '—' }}<br>
                            @endforeach
                        @endif
                    </td>
                </tr>
                @endif
            </table>

            {{-- Request form --}}
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('roleChange.apply', $user) }}" method="POST">
                @csrf

                {{-- Requested role --}}
                <div class="mb-3">
                    <label for="role" class="form-label">Requested Role <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-select" required>
                        @foreach ($availableRoles as $r)
                        <option value="{{ $r }}" {{ old('role', $currentRole) === $r ? 'selected' : '' }}>
                            {{ $r }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- District (read-only) --}}
                <div class="mb-3">
                    <label class="form-label">District</label>
                    <input type="hidden" name="district_id" value="{{ $user->district_id }}">
                    <p class="form-control-plaintext border rounded px-3 py-2 bg-light mb-0">
                        {{ $user->district?->name ?? '—' }}
                    </p>
                </div>

                {{-- Department (read-only) --}}
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <input type="hidden" name="department_id" value="{{ $user->department_id }}">
                    <p class="form-control-plaintext border rounded px-3 py-2 bg-light mb-0">
                        {{ $user->department?->name ?? '—' }}
                    </p>
                </div>

                {{-- Management scopes (admin / super_admin only) --}}
                <div id="scopesSection" class="mb-3" style="display:none;">
                    <label class="form-label">Management Scope <span class="text-danger">*</span></label>
                    <div class="mb-1 text-muted small">Add at least one district and department combination.</div>
                    @error('scopes')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <div id="scopeRows">
                        {{-- 既存エラー時の再描画 --}}
                        @if(old('scopes'))
                            @foreach(old('scopes') as $i => $scope)
                            <div class="scope-row d-flex gap-2 mb-2">
                                <select name="scopes[{{ $i }}][district_id]" class="form-select @error('scopes.'.$i) is-invalid @enderror">
                                    <option value="">— District —</option>
                                    @foreach ($districts as $district)
                                        <option value="{{ $district->id }}" {{ ($scope['district_id'] ?? '') == $district->id ? 'selected' : '' }}>
                                            {{ $district->name }}
                                        </option>
                                        @foreach ($district->children as $child)
                                        <option value="{{ $child->id }}" {{ ($scope['district_id'] ?? '') == $child->id ? 'selected' : '' }}>
                                            　↳ {{ $child->name }}
                                        </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <select name="scopes[{{ $i }}][department_id]" class="form-select">
                                    <option value="">— Department —</option>
                                    @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ ($scope['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-danger btn-sm scope-remove" style="white-space:nowrap;">Remove</button>
                            </div>
                            @endforeach
                        @endif
                    </div>

                    <button type="button" id="addScope" class="btn btn-outline-secondary btn-sm mt-1">+ Add Management Scope</button>
                </div>

                {{-- Request reason --}}
                <div class="mb-3">
                    <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror"
                              rows="3" required>{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>
</div>

@php
$districtsData = $districts->map(function ($d) {
    return [
        'id'       => $d->id,
        'name'     => $d->name,
        'children' => $d->children->map(function ($c) {
            return ['id' => $c->id, 'name' => $c->name];
        })->values(),
    ];
})->values();
$departmentsData = $departments->map(function ($d) {
    return ['id' => $d->id, 'name' => $d->name];
})->values();
$existingScopesData = $user->managementScopes->map(function ($s) {
    return ['district_id' => $s->district_id, 'department_id' => $s->department_id];
})->values();
@endphp

<script>
(function () {
    const roleSelect    = document.getElementById('role');
    const scopesSection = document.getElementById('scopesSection');
    const scopeRows     = document.getElementById('scopeRows');
    const addScopeBtn   = document.getElementById('addScope');

    const districtsJson    = @json($districtsData);
    const departmentsJson  = @json($departmentsData);
    const existingScopes   = @json($existingScopesData);
    const hasOldInput      = {{ old('scopes') ? 'true' : 'false' }};

    let rowIndex = {{ old('scopes') ? count(old('scopes')) : 0 }};

    function buildDistrictOptions(selectedId) {
        let html = '<option value="">— District —</option>';
        districtsJson.forEach(d => {
            const sel = d.id == selectedId ? ' selected' : '';
            html += `<option value="${d.id}"${sel}>${d.name}</option>`;
            d.children.forEach(c => {
                const csel = c.id == selectedId ? ' selected' : '';
                html += `<option value="${c.id}"${csel}>　↳ ${c.name}</option>`;
            });
        });
        return html;
    }

    function buildDepartmentOptions(selectedId) {
        let html = '<option value="">— Department —</option>';
        departmentsJson.forEach(d => {
            const sel = d.id == selectedId ? ' selected' : '';
            html += `<option value="${d.id}"${sel}>${d.name}</option>`;
        });
        return html;
    }

    function addRow(districtId, departmentId) {
        const i   = rowIndex++;
        const row = document.createElement('div');
        row.className = 'scope-row d-flex gap-2 mb-2';
        row.innerHTML = `
            <select name="scopes[${i}][district_id]" class="form-select">
                ${buildDistrictOptions(districtId)}
            </select>
            <select name="scopes[${i}][department_id]" class="form-select">
                ${buildDepartmentOptions(departmentId)}
            </select>
            <button type="button" class="btn btn-outline-danger btn-sm scope-remove" style="white-space:nowrap;">Remove</button>
        `;
        scopeRows.appendChild(row);
    }

    function toggleScopes() {
        const isAdmin = ['admin', 'super_admin'].includes(roleSelect.value);
        scopesSection.style.display = isAdmin ? '' : 'none';
        if (!isAdmin) {
            scopeRows.innerHTML = '';
            rowIndex = 0;
        } else if (scopeRows.children.length === 0) {
            // バリデーションエラー後は old() の行が Blade で描画済みなので追加しない
            if (!hasOldInput && existingScopes.length > 0) {
                existingScopes.forEach(s => addRow(s.district_id, s.department_id));
            } else if (!hasOldInput) {
                addRow('', '');
            }
        }
    }

    addScopeBtn.addEventListener('click', () => addRow('', ''));

    scopeRows.addEventListener('click', function (e) {
        if (e.target.classList.contains('scope-remove')) {
            e.target.closest('.scope-row').remove();
        }
    });

    roleSelect.addEventListener('change', toggleScopes);

    // 初期表示
    toggleScopes();
    // old() でエラー時に行が既にある場合は追加しない（Bladeで描画済み）
})();
</script>

<style>
.role-change-panel {
    background-color: #eef6ff;
}

.role-change-panel .form-control,
.role-change-panel .form-select,
.role-change-panel .form-control-plaintext {
    background-color: #fff;
}
</style>
@endsection
