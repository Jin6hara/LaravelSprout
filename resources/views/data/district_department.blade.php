{{-- 地区・部署のCRUD操作を行うVueコンポーネント埋め込みビュー --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">District &amp; Department</h2>
  <a href="{{ route('data.list') }}" class="btn btn-sm btn-outline-secondary">&larr; Data</a>
</div>

@php
$props = [
    'districtUrl'   => route('api.district.index'),
    'departmentUrl' => route('api.department.index'),
];
@endphp
<div id="districtDepartmentEditor" data-props='@json($props)'></div>
@endsection
