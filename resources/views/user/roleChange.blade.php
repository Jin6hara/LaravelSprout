@extends('layouts.app')

@section('content')
<div class="container">
    <h2>権限変更申請</h2>

    <div class="card mt-3">
        <div class="card-body">
            {{-- 本人情報 --}}
            <table class="table table-bordered">
                <colgroup>
                    <col style="width:140px;">
                    <col>
                </colgroup>
                <tr>
                    <th>社員コード</th>
                    <td>{{ $user->employee_code }}</td>
                </tr>
                <tr>
                    <th>氏名</th>
                    <td>{{ $user->name }}</td>
                </tr>
                <tr>
                    <th>現在の権限</th>
                    <td>{{ $user->role_label }}</td>
                </tr>
                <tr>
                    <th>現在の所属地区</th>
                    <td>{{ $user->district?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <th>現在の所属部署</th>
                    <td>{{ $user->department?->name ?? '—' }}</td>
                </tr>
                @if($user->isAdmin())
                <tr>
                    <th>現在の管理範囲</th>
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

            {{-- 申請フォーム --}}
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

                {{-- 変更後の権限 --}}
                <div class="mb-3">
                    <label for="role" class="form-label">変更後の権限 <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-select" required>
                        @foreach ($availableRoles as $r)
                        @php
                            $labels = ['general' => '一般', 'admin' => '管理者', 'super_admin' => 'スーパー管理者'];
                        @endphp
                        <option value="{{ $r }}" {{ old('role', $currentRole) === $r ? 'selected' : '' }}>
                            {{ $labels[$r] ?? $r }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- 所属地区（read-only） --}}
                <div class="mb-3">
                    <label class="form-label">所属地区</label>
                    <input type="hidden" name="district_id" value="{{ $user->district_id }}">
                    <p class="form-control-plaintext border rounded px-3 py-2 bg-light mb-0">
                        {{ $user->district?->name ?? '—' }}
                    </p>
                </div>

                {{-- 所属部署（read-only） --}}
                <div class="mb-3">
                    <label class="form-label">所属部署</label>
                    <input type="hidden" name="department_id" value="{{ $user->department_id }}">
                    <p class="form-control-plaintext border rounded px-3 py-2 bg-light mb-0">
                        {{ $user->department?->name ?? '—' }}
                    </p>
                </div>

                {{-- 管理範囲（admin / super_admin のみ） --}}
                <div id="scopesSection" class="mb-3" style="display:none;">
                    <label class="form-label">管理範囲 <span class="text-danger">*</span></label>
                    <div class="mb-1 text-muted small">地区と部署の組み合わせを1件以上追加してください。</div>
                    @error('scopes')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <div id="scopeRows">
                        {{-- 既存エラー時の再描画 --}}
                        @if(old('scopes'))
                            @foreach(old('scopes') as $i => $scope)
                            <div class="scope-row d-flex gap-2 mb-2">
                                <select name="scopes[{{ $i }}][district_id]" class="form-select @error('scopes.'.$i) is-invalid @enderror">
                                    <option value="">— 地区 —</option>
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
                                    <option value="">— 部署 —</option>
                                    @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ ($scope['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-danger btn-sm scope-remove" style="white-space:nowrap;">削除</button>
                            </div>
                            @endforeach
                        @endif
                    </div>

                    <button type="button" id="addScope" class="btn btn-outline-secondary btn-sm mt-1">+ 管理範囲を追加</button>
                </div>

                {{-- 申請理由 --}}
                <div class="mb-3">
                    <label for="reason" class="form-label">申請理由 <span class="text-danger">*</span></label>
                    <textarea name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror"
                              rows="3" required>{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">申請する</button>
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
        let html = '<option value="">— 地区 —</option>';
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
        let html = '<option value="">— 部署 —</option>';
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
            <button type="button" class="btn btn-outline-danger btn-sm scope-remove" style="white-space:nowrap;">削除</button>
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
@endsection
