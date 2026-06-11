{{-- 学校ごとの時間割をVueコンポーネントで表示するビュー --}}
{{-- resources/views/schedule/schoolTimetable.blade.php --}}
@extends('layouts.app')

@section('content')
<div id="schoolTimetable" data-props='@json($props)'></div>
@endsection
