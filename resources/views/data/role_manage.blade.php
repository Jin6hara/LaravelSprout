{{-- ロールと権限の管理を行うVueコンポーネント埋め込みビュー --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Role Management</h2>
  <a href="{{ route('data.list') }}" class="btn btn-sm btn-outline-secondary">&larr; Data</a>
</div>

@php
$props = [
    'snapshotUrl'        => route('api.role_manage.snapshot'),
    'roleUrl'            => url('/api/role-manage/roles'),
    'rolePermissionUrl'  => url('/api/role-manage/role-permissions'),
    'modelRoleUrl'       => url('/api/role-manage/model-roles'),
    'modelPermissionUrl' => url('/api/role-manage/model-permissions'),
    'userUrl'            => url('/api/role-manage/users'),
];
@endphp

<div id="roleManageEditor" data-props='@json($props)'></div>
@endsection
