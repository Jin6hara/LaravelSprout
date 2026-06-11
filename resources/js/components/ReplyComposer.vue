<!-- メッセージへの返信フォームをテレポートで任意の位置に描画するコンポーネント -->
<template>
    <teleport :to="teleportTo">
        <form :action="actionUrl" method="POST" class="mb-3">
            <input type="hidden" name="_token" :value="csrf" />
            <input type="hidden" name="parent_id" :value="parentId" />

            <div class="mb-2">
                <label class="form-label small">Add a reply</label>
                <textarea name="body" class="form-control" rows="3" required v-model="body" ref="ta"></textarea>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary btn-sm" type="submit">Reply</button>

                <button v-if="parentId" class="btn btn-outline-secondary btn-sm" type="button" @click="cancelReply">
                    Cancel
                </button>

                <span class="small text-muted">{{ hint }}</span>
            </div>
        </form>
    </teleport>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';

const props = defineProps({
    actionUrl: { type: String, required: true },
    csrf: { type: String, required: true },
    parkingSelector: { type: String, default: '#replyFormParking' },

    // バリデーション失敗で戻ってきたとき用
    initialBody: { type: String, default: '' },
    initialParentId: { type: [String, Number], default: '' },
});

const teleportTo = ref(props.parkingSelector);
const parentId = ref(props.initialParentId ? String(props.initialParentId) : '');
const hint = ref(parentId.value ? 'Replying…' : '');
const body = ref(props.initialBody || '');
const ta = ref(null);

function mountUnder(commentId, authorName) {
    const sel = `[data-reply-slot="${commentId}"]`;
    const slot = document.querySelector(sel);
    teleportTo.value = slot ? sel : props.parkingSelector;

    parentId.value = String(commentId);
    hint.value = `Replying to: ${authorName || ''}`;

    nextTick(() => ta.value?.focus());
}

function cancelReply() {
    teleportTo.value = props.parkingSelector;
    parentId.value = '';
    hint.value = '';
    // 入力も消したいなら次行を有効化
    // body.value = '';
    nextTick(() => ta.value?.focus());
}

onMounted(() => {
    // 既に parent がある状態で戻ってきた場合（任意）
    if (parentId.value) teleportTo.value = props.parkingSelector;

    // Replyボタンは Blade 側にあるので、ページ全体で拾う（イベントDelegation）
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-reply');
        if (!btn) return;
        const cid = btn.dataset.commentId;
        if (!cid) return;
        mountUnder(cid, btn.dataset.authorName || '');
    });
});
</script>
