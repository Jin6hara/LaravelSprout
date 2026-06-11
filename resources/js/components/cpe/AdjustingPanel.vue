<!-- カレンダーパターン内の調整日をCRUD操作するパネルコンポーネント -->
<template>
  <div>
    <div v-if="!restPatternId" class="text-muted small py-3 text-center">
      上部で Rest Pattern を選択してください。
    </div>
    <template v-else>
      <div v-if="loading" class="text-center py-3"><div class="spinner-border spinner-border-sm text-secondary"></div></div>
      <template v-else>
        <!-- 一覧 -->
        <table class="table table-sm table-bordered mb-2">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Kind</th>
              <th>Title</th>
              <th class="text-center" style="width:70px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="4" class="text-center text-muted py-2">No records</td>
            </tr>
            <template v-for="row in rows" :key="row.id">
              <!-- 表示行 -->
              <tr v-if="editId !== row.id">
                <td>{{ row.date }}</td>
                <td><span :class="kindBadge(row.kind)">{{ kindLabel(row.kind) }}</span></td>
                <td>{{ row.title ?? '—' }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-primary" @click="startEdit(row)">Edit</button>
                    <button class="btn btn-xs btn-outline-danger"  @click="del(row)">Del</button>
                  </div>
                </td>
              </tr>
              <!-- 編集行 -->
              <tr v-else class="table-warning">
                <td><input type="date" v-model="form.date" class="form-control form-control-sm" /></td>
                <td>
                  <select v-model="form.kind" class="form-select form-select-sm">
                    <option value="add_off">add_off（ORD）</option>
                    <option value="work_instead">work_instead（RWD）</option>
                  </select>
                </td>
                <td>
                  <input type="text" v-model="form.title" class="form-control form-control-sm" placeholder="Title（任意）" />
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-primary" :disabled="saving" @click="update(row)">Save</button>
                    <button class="btn btn-xs btn-secondary" @click="cancelEdit">×</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <!-- 新規フォーム -->
        <div class="border rounded p-2 bg-light">
          <div class="small fw-semibold mb-1">+ New Adjusting</div>
          <input type="date" v-model="newForm.date" class="form-control form-control-sm mb-1" />
          <select v-model="newForm.kind" class="form-select form-select-sm mb-1">
            <option value="add_off">add_off（ORD: 所定追加休）</option>
            <option value="work_instead">work_instead（RWD: 調整出勤）</option>
          </select>
          <input type="text" v-model="newForm.title" class="form-control form-control-sm mb-1" placeholder="Title（任意）" />
          <input type="text" v-model="newForm.note" class="form-control form-control-sm mb-1" placeholder="Note（任意）" />
          <button class="btn btn-sm btn-success" :disabled="saving" @click="create">Add</button>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  fiscalYear:    { type: Number, required: true },
  restPatternId: { type: [Number, String], default: '' },
  baseUrl:       { type: String, required: true },
});
const emit = defineEmits(['saved', 'deleted', 'flash']);

const rows    = ref([]);
const loading = ref(false);
const saving  = ref(false);
const editId  = ref(null);
const form    = ref({});
const newForm = ref({ date: '', kind: 'add_off', title: '', note: '' });

async function fetch() {
  if (!props.restPatternId) { rows.value = []; return; }
  loading.value = true;
  try {
    const res = await axios.get(props.baseUrl, {
      params: { fiscal_year: props.fiscalYear, rest_pattern_id: props.restPatternId },
    });
    rows.value = res.data;
  } catch {
    emit('flash', { text: 'Adjusting の取得に失敗しました。', ok: false });
  } finally { loading.value = false; }
}

function kindLabel(kind) { return kind === 'add_off' ? 'ORD' : 'RWD'; }
function kindBadge(kind) { return kind === 'add_off' ? 'badge text-bg-warning' : 'badge text-bg-info'; }

function startEdit(row) {
  editId.value = row.id;
  form.value = { date: row.date, kind: row.kind, title: row.title ?? '', note: row.note ?? '' };
}
function cancelEdit() { editId.value = null; }

async function update(row) {
  saving.value = true;
  try {
    const res = await axios.put(`${props.baseUrl}/${row.id}`, {
      ...form.value,
      rest_pattern_id: props.restPatternId,
    });
    const idx = rows.value.findIndex(r => r.id === row.id);
    if (idx !== -1) rows.value[idx] = res.data;
    editId.value = null;
    emit('saved');
    emit('flash', { text: '更新しました。', ok: true });
  } catch (e) {
    emit('flash', { text: e.response?.data?.message ?? '更新に失敗しました。', ok: false });
  } finally { saving.value = false; }
}

async function del(row) {
  if (!confirm(`${row.date} の ${kindLabel(row.kind)} を削除しますか？`)) return;
  try {
    await axios.delete(`${props.baseUrl}/${row.id}`);
    rows.value = rows.value.filter(r => r.id !== row.id);
    emit('deleted');
    emit('flash', { text: '削除しました。', ok: true });
  } catch {
    emit('flash', { text: '削除に失敗しました。', ok: false });
  }
}

async function create() {
  if (!newForm.value.date) {
    emit('flash', { text: 'Date は必須です。', ok: false }); return;
  }
  saving.value = true;
  try {
    const res = await axios.post(props.baseUrl, {
      ...newForm.value,
      rest_pattern_id: props.restPatternId,
    });
    rows.value.push(res.data);
    rows.value.sort((a, b) => a.date.localeCompare(b.date));
    newForm.value = { date: '', kind: 'add_off', title: '', note: '' };
    emit('saved');
    emit('flash', { text: '追加しました。', ok: true });
  } catch (e) {
    emit('flash', { text: e.response?.data?.message ?? '追加に失敗しました。', ok: false });
  } finally { saving.value = false; }
}

watch([() => props.fiscalYear, () => props.restPatternId], fetch);
onMounted(fetch);
</script>

<style scoped>
.btn-xs { padding: 0.1rem 0.35rem; font-size: 0.7rem; }
</style>
