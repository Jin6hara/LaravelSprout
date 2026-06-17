{{-- 生成されたシフトの確認・操作（切り離し・削除）を行うモーダルコンポーネント --}}
@props([
    'id' => 'generatedShiftConfirmModal',
])

<div class="modal fade generated-shift-confirm-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-warning py-2">
                <h6 class="modal-title" id="{{ $id }}Label">Generated Shift Confirmation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-generated-shift-text>This action affects generated shift(s). Please choose what to do.</p>
                <div class="small text-muted mb-2" data-generated-shift-summary></div>
                <div class="generated-shift-list" data-generated-shift-list style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-generated-shift-keep>Keep Shift</button>
                <button type="button" class="btn btn-danger btn-sm" data-generated-shift-delete>Delete Shift</button>
            </div>
        </div>
    </div>
</div>
