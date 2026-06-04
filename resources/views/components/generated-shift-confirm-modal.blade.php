@props([
    'id',
    'title',
    'headerClass' => 'bg-warning',
    'message' => 'This action affects generated shift(s). Please choose what to do.',
    'keepLabel' => 'Keep Shift',
    'deleteLabel' => 'Delete Shift',
])

<div class="modal fade generated-shift-confirm-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header {{ $headerClass }} py-2">
                <h6 class="modal-title" id="{{ $id }}Label">{{ $title }}</h6>
                <button type="button" class="btn-close @if(str_contains($headerClass, 'text-white')) btn-close-white @endif" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-generated-shift-text>{{ $message }}</p>
                <div class="small text-muted mb-2" data-generated-shift-summary></div>
                <div class="generated-shift-list" data-generated-shift-list style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-generated-shift-keep>{{ $keepLabel }}</button>
                <button type="button" class="btn btn-danger btn-sm" data-generated-shift-delete>{{ $deleteLabel }}</button>
            </div>
        </div>
    </div>
</div>
