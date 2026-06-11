<!-- 管理者メッセージ作成画面の送信先・タイプ・オプションを制御するコンポーネント -->
<template>
    <!-- Type -->
    <div class="mb-3">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select name="type" class="form-select" v-model="type" required>
            <option v-for="o in typeOptions" :key="o.value" :value="o.value">
                {{ o.label }}
            </option>
        </select>
        <div class="form-text">type decides default values such as allow replies.</div>
    </div>

    <!-- Recipients -->
    <div class="mb-2">
        <label class="form-label">Recepients <span class="text-danger">*</span></label>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" @click="openModal">
                Select
            </button>
            <span class="text-muted small align-self-center">Search → Check → Add</span>
        </div>

        <!-- hidden inputs for submit -->
        <input v-for="u in selectedUsers" :key="u.id" type="hidden" name="recipients[]" :value="u.id" />

        <div class="mt-2 d-flex flex-wrap gap-2">
            <span v-for="u in selectedUsers" :key="u.id"
                class="badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 p-2">
                <span>
                    {{ (u.family_name ?? '') }} {{ (u.first_name ?? '') }}
                    <span class="opacity-75">[{{ u.employee_code ?? '' }}]</span>
                </span>
                <button type="button" class="btn-close btn-close-white btn-sm ms-1" aria-label="Remove"
                    @click="removeUser(u.id)"></button>
            </span>
        </div>

        <div class="form-text">Choose at least one recipient.</div>
    </div>

    <!-- Allow replies -->
    <div class="mb-3 form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" :checked="allowReplies" @change="toggleAllow" />
        <label class="form-check-label">Allow Replies</label>

        <!-- submit value (only this is posted) -->
        <input type="hidden" name="allow_replies" :value="allowReplies ? 1 : 0" />
    </div>

    <!-- Modal -->
    <div class="modal fade" ref="modalEl" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Choose Recipient(s)</h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="closeModal"></button>
                </div>

                <div class="modal-body">
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" placeholder="Name, Employee Code, Email" v-model="q"
                            @keydown.enter.prevent="doSearch">
                        <button class="btn btn-outline-secondary" type="button" @click="doSearch">Search</button>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Name</th>
                                    <th>Employee Code</th>
                                    <th>Email</th>
                                </tr>
                            </thead>

                            <tbody v-if="loading">
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-3">検索中...</td>
                                </tr>
                            </tbody>

                            <tbody v-else-if="results.length === 0">
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-3">Search</td>
                                </tr>
                            </tbody>

                            <tbody v-else>
                                <tr v-for="u in results" :key="u.id">
                                    <td>
                                        <input class="form-check-input" type="checkbox" v-model="checked[u.id]" />
                                    </td>
                                    <td>{{ (u.family_name ?? '') }} {{ (u.first_name ?? '') }}</td>
                                    <td>{{ u.employee_code ?? '' }}</td>
                                    <td>{{ u.email ?? '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <span class="me-auto small text-muted">{{ checkedCount }}件選択中</span>
                    <button type="button" class="btn btn-outline-secondary" @click="closeModal">Close</button>
                    <button type="button" class="btn btn-primary" @click="addRecipients">Add</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';

const props = defineProps({
    searchUrl: { type: String, required: true },
    typeOptions: { type: Array, required: true }, // [{value,label,defaultReply}]
    initialType: { type: String, default: 'announcement' },
    initialAllowReplies: { type: [Number, String, null], default: null }, // null => use default by type
    initialRecipientIds: { type: Array, default: () => [] },
});

const type = ref(props.initialType);

// allow replies: null means "not decided yet" -> apply default
const allowRepliesTouched = ref(false);
const allowReplies = ref(
    props.initialAllowReplies === null || props.initialAllowReplies === ''
        ? null
        : Number(props.initialAllowReplies) === 1
);

function applyDefaultAllowReplies() {
    const opt = props.typeOptions.find(o => o.value === type.value);
    const def = opt ? Number(opt.defaultReply) === 1 : true;
    allowReplies.value = def;
}

watch(type, () => {
    if (!allowRepliesTouched.value) applyDefaultAllowReplies();
});

function toggleAllow(e) {
    allowRepliesTouched.value = true;
    allowReplies.value = !!e.target.checked;
}

// recipients
const selectedUsers = ref([]); // [{id, first_name,family_name,employee_code,email}]
const results = ref([]);
const loading = ref(false);
const q = ref('');
const checked = reactive({}); // map: id -> boolean

const checkedCount = computed(() => Object.values(checked).filter(Boolean).length);
const selectedIdSet = computed(() => new Set(selectedUsers.value.map(u => String(u.id))));

function removeUser(id) {
    selectedUsers.value = selectedUsers.value.filter(u => String(u.id) !== String(id));
    checked[id] = false;
}

async function doSearch() {
    loading.value = true;
    try {
        const url = new URL(props.searchUrl, window.location.origin);
        const qq = q.value.trim();
        if (qq) url.searchParams.set('q', qq);

        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Search failed');
        const users = await res.json();

        results.value = Array.isArray(users) ? users : [];
        // 既に選択済みはチェックを付ける
        for (const u of results.value) {
            checked[u.id] = selectedIdSet.value.has(String(u.id));
        }
    } finally {
        loading.value = false;
    }
}

function addRecipients() {
    for (const u of results.value) {
        if (checked[u.id] && !selectedIdSet.value.has(String(u.id))) {
            selectedUsers.value.push(u);
        }
    }
    closeModal();
}

// bootstrap modal (global bootstrap from CDN)
const modalEl = ref(null);
let modal = null;

function openModal() {
  // 既選択を checked に反映（モーダル開くたびに整える）
  for (const u of results.value) checked[u.id] = selectedIdSet.value.has(String(u.id));
  modal?.show();
}
function closeModal() { modal?.hide(); }

onMounted(async () => {
    // decide allowReplies default on first load
    if (allowReplies.value === null) applyDefaultAllowReplies();

    // init modal
    if (modalEl.value && window.bootstrap?.Modal) {
        modal = new window.bootstrap.Modal(modalEl.value);
    }

    // load initial recipients details (needs ids support in API; see below)
    const ids = (props.initialRecipientIds || []).map(String).filter(Boolean);
    if (ids.length) {
        try {
            const url = new URL(props.searchUrl, window.location.origin);
            url.searchParams.set('ids', ids.join(','));
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('Search failed');
            const users = await res.json();
            selectedUsers.value = Array.isArray(users) ? users : [];
        } catch {
            selectedUsers.value = ids.map(id => ({ id, family_name: '', first_name: '', employee_code: '' }));
        }
    }
});
</script>
