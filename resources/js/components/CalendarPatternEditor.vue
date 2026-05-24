<template>
  <div>
    <!-- フラッシュメッセージ -->
    <div
      v-if="message"
      :class="['alert', 'py-2', 'px-3', 'mb-3', msgOk ? 'alert-success' : 'alert-danger']"
    >
      {{ message }}
    </div>

    <!-- ローディング -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-secondary"></div>
    </div>

    <template v-else>
      <!-- テーブル -->
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Pattern</th>
              <th>Code</th>
              <th>Start</th>
              <th>End</th>
              <th class="text-center" style="width:230px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- レコードなし -->
            <tr v-if="rows.length === 0 && !showNewForm">
              <td colspan="5" class="text-center text-muted py-4">No records</td>
            </tr>

            <template v-for="(row, idx) in rows" :key="row.id">
              <!-- ── 表示行 ── -->
              <tr v-if="editingId !== row.id">
                <td>{{ row.pattern.name }}</td>
                <td><code>{{ row.pattern.code }}</code></td>
                <td>{{ row.start_date }}</td>
                <td>{{ row.end_date ?? '—' }}</td>
                <td>
                  <div class="d-flex gap-1 align-items-center flex-wrap">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      @click="startEdit(row)"
                    >Edit</button>
                    <button
                      class="btn btn-sm btn-outline-danger"
                      @click="deleteRow(row)"
                    >Delete</button>
                    <!-- 最新行だけ New ボタンを表示 -->
                    <button
                      v-if="idx === rows.length - 1 && !showNewForm && editingId === null"
                      class="btn btn-sm btn-success ms-auto"
                      @click="openNew"
                    >+ New</button>
                  </div>
                </td>
              </tr>

              <!-- ── 編集行 ── -->
              <tr v-else class="table-warning">
                <td>
                  <select v-model="editForm.rest_pattern_id" class="form-select form-select-sm">
                    <option v-for="p in patterns" :key="p.id" :value="p.id">
                      {{ p.name }}
                    </option>
                  </select>
                </td>
                <td>
                  <code>{{ codeOf(editForm.rest_pattern_id) }}</code>
                </td>
                <td>
                  <input
                    type="date"
                    v-model="editForm.start_date"
                    class="form-control form-control-sm"
                  />
                </td>
                <td>
                  <input
                    type="date"
                    v-model="editForm.end_date"
                    class="form-control form-control-sm"
                  />
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <button
                      class="btn btn-sm btn-primary"
                      :disabled="saving"
                      @click="saveEdit(row)"
                    >Update</button>
                    <button
                      class="btn btn-sm btn-secondary"
                      @click="cancelEdit"
                    >Cancel</button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- ── 新規入力行 ── -->
            <tr v-if="showNewForm" class="table-success">
              <td>
                <select v-model="newForm.rest_pattern_id" class="form-select form-select-sm">
                  <option value="">— Select —</option>
                  <option v-for="p in patterns" :key="p.id" :value="p.id">
                    {{ p.name }}
                  </option>
                </select>
              </td>
              <td>
                <code>{{ codeOf(newForm.rest_pattern_id) }}</code>
              </td>
              <td>
                <input
                  type="date"
                  v-model="newForm.start_date"
                  class="form-control form-control-sm"
                />
              </td>
              <td>
                <input
                  type="date"
                  v-model="newForm.end_date"
                  class="form-control form-control-sm"
                />
              </td>
              <td>
                <div class="d-flex gap-1">
                  <button
                    class="btn btn-sm btn-success"
                    :disabled="saving"
                    @click="createNew"
                  >保存</button>
                  <button
                    class="btn btn-sm btn-secondary"
                    @click="cancelNew"
                  >Cancel</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- レコードゼロ時の New ボタン -->
      <div v-if="rows.length === 0 && !showNewForm" class="text-center mt-3">
        <button class="btn btn-sm btn-success" @click="openNew">+ New</button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  baseApiUrl: { type: String, required: true },
  patterns:   { type: Array,  default: () => [] },
});

const rows        = ref([]);
const loading     = ref(true);
const saving      = ref(false);
const message     = ref('');
const msgOk       = ref(true);
const editingId   = ref(null);
const showNewForm = ref(false);

const editForm = ref({ rest_pattern_id: null, start_date: '', end_date: '' });
const newForm  = ref({ rest_pattern_id: '',   start_date: '', end_date: '' });

function codeOf(patternId) {
  return props.patterns.find(p => p.id == patternId)?.code ?? '';
}

function flash(text, ok = true, reload = false) {
  message.value = text;
  msgOk.value   = ok;
  if (reload) {
    setTimeout(() => location.reload(), 2000);
  } else {
    setTimeout(() => (message.value = ''), 3000);
  }
}

async function fetchHistory() {
  loading.value = true;
  try {
    const res = await axios.get(props.baseApiUrl);
    rows.value  = res.data;
  } catch {
    flash('履歴の取得に失敗しました。', false);
  } finally {
    loading.value = false;
  }
}

function startEdit(row) {
  editingId.value = row.id;
  editForm.value  = {
    rest_pattern_id: row.rest_pattern_id,
    start_date:      row.start_date ?? '',
    end_date:        row.end_date   ?? '',
  };
}

function cancelEdit() {
  editingId.value = null;
}

async function saveEdit(row) {
  saving.value = true;
  try {
    const res = await axios.put(`${props.baseApiUrl}/${row.id}`, {
      ...editForm.value,
      end_date: editForm.value.end_date || null,
    });
    const idx = rows.value.findIndex(r => r.id === row.id);
    if (idx !== -1) rows.value[idx] = res.data;
    editingId.value = null;
    flash('更新しました。', true, true);
  } catch (e) {
    flash(e.response?.data?.message ?? '更新に失敗しました。', false);
  } finally {
    saving.value = false;
  }
}

async function deleteRow(row) {
  if (!confirm('このレコードを削除しますか？')) return;
  try {
    await axios.delete(`${props.baseApiUrl}/${row.id}`);
    rows.value = rows.value.filter(r => r.id !== row.id);
    flash('削除しました。', true, true);
  } catch {
    flash('削除に失敗しました。', false);
  }
}

function fiscalYearDates() {
  const today = new Date();
  const fyStart = today.getMonth() + 1 >= 4 ? today.getFullYear() : today.getFullYear() - 1;
  return {
    start: `${fyStart}-04-01`,
    end:   `${fyStart + 1}-03-31`,
  };
}

function openNew() {
  const { start, end } = fiscalYearDates();
  showNewForm.value = true;
  newForm.value = { rest_pattern_id: '', start_date: start, end_date: end };
}

function cancelNew() {
  showNewForm.value = false;
}

async function createNew() {
  if (!newForm.value.rest_pattern_id || !newForm.value.start_date) {
    flash('Pattern と Start date は必須です。', false);
    return;
  }
  saving.value = true;
  try {
    const res = await axios.post(props.baseApiUrl, {
      ...newForm.value,
      end_date: newForm.value.end_date || null,
    });
    rows.value.push(res.data);
    showNewForm.value = false;
    flash('作成しました。', true, true);
  } catch (e) {
    flash(e.response?.data?.message ?? '作成に失敗しました。', false);
  } finally {
    saving.value = false;
  }
}

onMounted(fetchHistory);
</script>
