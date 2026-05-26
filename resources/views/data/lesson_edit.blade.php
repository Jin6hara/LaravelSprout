@extends('layouts.app')

@section('content')
@php($oldForm = old('_lesson_form'))

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-0">Lesson</h2>
        <div class="text-muted small">{{ number_format($lessons->total()) }} lessons</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('csv.lessons.show') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-file-csv me-1"></i>CSV
        </a>
        <a href="{{ route('data.list') }}" class="btn btn-sm btn-outline-secondary">&larr; Data</a>
    </div>
</div>

<form method="GET" action="{{ route('data.lessons.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-7 col-lg-6">
        <label for="lesson-search" class="form-label small mb-1">Search</label>
        <input
            id="lesson-search"
            type="search"
            name="q"
            value="{{ $search }}"
            class="form-control"
            placeholder="ID / name / code / note / PS / FM">
    </div>
    <div class="col-6 col-md-2">
        <label for="lesson-per-page" class="form-label small mb-1">Per page</label>
        <select id="lesson-per-page" name="per_page" class="form-select">
            @foreach($perPageOptions as $option)
                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-auto">
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-magnifying-glass me-1"></i>Search
        </button>
    </div>
    @if($search !== '')
        <div class="col-12 col-md-auto">
            <a href="{{ route('data.lessons.index', ['per_page' => $perPage]) }}" class="btn btn-outline-secondary">
                Clear
            </a>
        </div>
    @endif
</form>

<div class="card mb-4">
    <div class="card-header bg-white">
        <strong>New Lesson</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5rem;">ID</th>
                        <th style="min-width: 13.2rem;">Lesson name</th>
                        <th style="min-width: 8rem;">Code</th>
                        <th style="min-width: 5rem;">Minute</th>
                        <th style="min-width: 28.8rem;">PS unique code</th>
                        <th style="min-width: 9.3rem;">FM code</th>
                        <th style="min-width: 9.7rem;">Note</th>
                        <th style="width: 10rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" value="New" class="form-control form-control-sm bg-light text-muted" readonly>
                        </td>
                        <td>
                            <input form="lesson-create-form" type="text" name="lesson_name" value="{{ $oldForm === 'create' ? old('lesson_name') : '' }}" class="form-control form-control-sm" maxlength="255">
                        </td>
                        <td>
                            <input form="lesson-create-form" type="text" name="lesson_code" value="{{ $oldForm === 'create' ? old('lesson_code') : '' }}" class="form-control form-control-sm" maxlength="255">
                        </td>
                        <td>
                            <input form="lesson-create-form" type="number" name="lesson_minute" value="{{ $oldForm === 'create' ? old('lesson_minute') : '' }}" class="form-control form-control-sm" min="0" max="65535">
                        </td>
                        <td>
                            <input form="lesson-create-form" type="text" name="ps_unique_lesson_code" value="{{ $oldForm === 'create' ? old('ps_unique_lesson_code') : '' }}" class="form-control form-control-sm" maxlength="255">
                        </td>
                        <td>
                            <input form="lesson-create-form" type="text" name="fm_lesson_code" value="{{ $oldForm === 'create' ? old('fm_lesson_code') : '' }}" class="form-control form-control-sm" maxlength="255">
                        </td>
                        <td>
                            <input form="lesson-create-form" type="text" name="note" value="{{ $oldForm === 'create' ? old('note') : '' }}" class="form-control form-control-sm" maxlength="255">
                        </td>
                        <td>
                            <form id="lesson-create-form" method="POST" action="{{ route('data.lessons.store') }}">
                                @csrf
                                <input type="hidden" name="_lesson_form" value="create">
                            </form>
                            <button form="lesson-create-form" type="submit" class="btn btn-sm btn-success" title="Create">
                                <i class="fas fa-plus"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 5rem;">ID</th>
                <th style="min-width: 13.2rem;">Lesson name</th>
                <th style="min-width: 8rem;">Code</th>
                <th style="min-width: 5rem;">Minute</th>
                <th style="min-width: 28.8rem;">PS unique code</th>
                <th style="min-width: 9.3rem;">FM code</th>
                <th style="min-width: 9.7rem;">Note</th>
                <th style="width: 10rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lessons as $lesson)
                @php($formId = 'lesson-update-' . $lesson->id)
                @php($isOldRow = $oldForm === $formId)
                <tr>
                    <td class="text-muted">#{{ $lesson->id }}</td>
                    <td>
                        <input form="{{ $formId }}" type="text" name="lesson_name" value="{{ $isOldRow ? old('lesson_name') : $lesson->lesson_name }}" class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <input form="{{ $formId }}" type="text" name="lesson_code" value="{{ $isOldRow ? old('lesson_code') : $lesson->lesson_code }}" class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <input form="{{ $formId }}" type="number" name="lesson_minute" value="{{ $isOldRow ? old('lesson_minute') : $lesson->lesson_minute }}" class="form-control form-control-sm" min="0" max="65535">
                    </td>
                    <td>
                        <input form="{{ $formId }}" type="text" name="ps_unique_lesson_code" value="{{ $isOldRow ? old('ps_unique_lesson_code') : $lesson->ps_unique_lesson_code }}" class="form-control form-control-sm bg-light" maxlength="255" readonly>
                    </td>
                    <td>
                        <input form="{{ $formId }}" type="text" name="fm_lesson_code" value="{{ $isOldRow ? old('fm_lesson_code') : $lesson->fm_lesson_code }}" class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <input form="{{ $formId }}" type="text" name="note" value="{{ $isOldRow ? old('note') : $lesson->note }}" class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <form id="{{ $formId }}" method="POST" action="{{ route('data.lessons.update', $lesson) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_lesson_form" value="{{ $formId }}">
                            </form>
                            <button form="{{ $formId }}" type="submit" class="btn btn-sm btn-primary" title="Update">
                                <i class="fas fa-floppy-disk"></i>
                            </button>
                            <form method="POST" action="{{ route('data.lessons.destroy', $lesson) }}" onsubmit="return confirm('Delete lesson #{{ $lesson->id }}? Used lessons will be blocked.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No lessons found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="text-muted small">
        Showing {{ $lessons->firstItem() ?? 0 }}-{{ $lessons->lastItem() ?? 0 }} of {{ $lessons->total() }}
    </div>
    {{ $lessons->links() }}
</div>
@endsection
