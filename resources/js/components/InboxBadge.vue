<!-- 未読メッセージ数をポーリングでリアルタイムに表示するバッジコンポーネント -->
<template>
    <span v-if="count > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
        {{ count }}
    </span>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    endpoint: { type: String, required: true },
    initialCount: { type: Number, default: 0 },
    intervalMs: { type: Number, default: 5000 }, // 5秒おき（好みで調整）
});

const count = ref(props.initialCount);
let timer = null;
let handleVisibilityChange = null;

async function fetchCount() {
    if (document.hidden) return;

    try {
        const res = await fetch(props.endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (typeof data?.count === 'number') count.value = data.count;
    } catch {
        // ネットワークエラー時は黙って維持（必要ならtoast等）
    }
}

onMounted(() => {
    timer = window.setInterval(fetchCount, props.intervalMs);

    // タブ復帰・フォーカス復帰で即更新
    window.addEventListener('focus', fetchCount);
    handleVisibilityChange = () => {
        if (!document.hidden) fetchCount();
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // 任意：他画面から強制更新イベント
    window.addEventListener('inbox:refresh', fetchCount);
});

onBeforeUnmount(() => {
    if (timer) window.clearInterval(timer);
    window.removeEventListener('focus', fetchCount);
    if (handleVisibilityChange) {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }
    window.removeEventListener('inbox:refresh', fetchCount);
});
</script>
