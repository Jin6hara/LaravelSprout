<template>
  <div>
    <div v-if="loading" class="text-center py-3"><div class="spinner-border spinner-border-sm text-secondary"></div></div>
    <template v-else>
      <!-- 一覧 -->
      <table class="table table-sm table-bordered mb-2">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Start</th>
            <th>End</th>
            <th class="text-center" style="width:70px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="5" class="text-center text-muted py-2">No records</td>
          </tr>
          <template v-for="row in rows" :key="row.id">
            <!-- 表示行 -->
            <tr v-if="editId !== row.id">
              <td>{{ row.name }}<span v-if="!row.is_active" class="badge text-bg-secondary ms-1 small">inactive</span></td>
              <td><code>{{ row.code }}</code></td>
              <td>{{ row.start_date }}</td>
              <td>{{ row.end_date }}</td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-xs btn-outline-primary" @click="startEdit(row)">Edit</button>
                  <button class="btn btn-xs btn-outline-danger"  @click="del(row)">Del</button>
                </div>
              </td>
            </tr>
            <!-- 編集行 -->
            <tr v-else class="table-warning">
              <td>
                <input type="text" v-model="form.name" class="form-control form-control-sm mb-1" placeholder="Name" />
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.is_active" id="ea" />
                  <label class="form-check-label small" for="ea">Active</label>
                </div>
              </td>
              <td><input type="text" v-model="form.code" class="form-control form-control-sm" placeholder="GW/SB/WB" /></td>
              <td><input type="date" v-model="form.start_date" class="form-control form-control-sm" /></td>
              <td><input type="date" v-model="form.end_date" class="form-control form-control-sm" /></td>
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
        <div class="small fw-semibold mb-1">+ New Closure</div>
        <input type="text" v-model="newForm.name" class="form-control form-control-sm mb-1" placeholder="Name（例: 夏休み）" />
        <input type="text" v-model="newForm.code" class="form-control form-control-sm mb-1" placeholder="Code（例: SB）" />
        <div class="row g-1 mb-1">
          <div class="col">
            <input type="date" v-model="newForm.start_date" class="form-control form-control-sm" />
          </div>
          <div class="col">
            <input type="date" v-model="newForm.end_date" class="form-control form-control-sm" />
          </div>
        </div>
        <input type="text" v-model="newForm.note" class="form-control form-control-sm mb-1" placeholder="Note（任意）" />
        <button class="btn btn-sm btn-success" :disabled="saving" @click="create">Add</button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  fiscalYear: { type: Number, required: true },
  baseUrl:    { type: String, required: true },
});
const emit = defineEmits(['saved', 'deleted', 'flash']);

const rows    = ref([]);
const loading = ref(false);
const saving  = ref(false);
const editId  = ref(null);
const form    = ref({});
const newForm = ref({ name: '', code: '', start_date: '', end_date: '', note: '' });

async function fetch() {
  loading.value = true;
  try {
    const res = await axios.get(props.baseUrl, { params: { fiscal_year: props.fiscalYear } });
    rows.value = res.data;
  } catch {
    emit('flash', { text: 'Closure の取得に失敗しました。', ok: false });
  } finally { loading.value = false; }
}

function startEdit(row) {
  editId.value = row.id;
  form.value = {
    name:       row.name,
    code:       row.code,
    start_date: row.start_date,
    end_date:   row.end_date,
    is_active:  row.is_active,
    note:       row.note ?? '',
  };
}
function cancelEdit() { editId.value = null; }

async function update(row) {
  saving.value = true;
  try {
    const res = await axios.put(`${props.baseUrl}/${row.id}`, form.value);
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
  if (!confirm(`「${row.name}」を削除しますか？`)) return;
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
  if (!newForm.value.name || !newForm.value.code || !newForm.value.start_date || !newForm.value.end_date) {
    emit('flash', { text: 'Name, Code, Start, End は必須です。', ok: false }); return;
  }
  saving.value = true;
  try {
    const res = await axios.post(props.baseUrl, { ...newForm.value, is_active: true });
    rows.value.push(res.data);
    rows.value.sort((a, b) => a.start_date.localeCompare(b.start_date));
    newForm.value = { name: '', code: '', start_date: '', end_date: '', note: '' };
    emit('saved');
    emit('flash', { text: '追加しました。', ok: true });
  } catch (e) {
    emit('flash', { text: e.response?.data?.message ?? '追加に失敗しました。', ok: false });
  } finally { saving.value = false; }
}

watch(() => props.fiscalYear, fetch);
onMounted(fetch);
</script>

<style scoped>
.btn-xs { padding: 0.1rem 0.35rem; font-size: 0.7rem; }
</style>
