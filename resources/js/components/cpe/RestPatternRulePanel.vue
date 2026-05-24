<template>
  <div>
    <div v-if="!restPatternId" class="text-muted small p-2">
      Rest Pattern を選択してください。
    </div>
    <template v-else>
      <div v-if="loading" class="text-center py-3">
        <div class="spinner-border spinner-border-sm text-secondary"></div>
      </div>
      <template v-else>
        <table class="table table-sm mb-2">
          <thead class="table-light">
            <tr>
              <th class="small">曜日</th>
              <th class="small">種別</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(dow, i) in dowNames" :key="i">
              <td class="small align-middle" :class="i === 0 ? 'text-danger' : i === 6 ? 'text-primary' : ''">
                {{ dow }}
              </td>
              <td>
                <select v-model="rules[i]" class="form-select form-select-sm">
                  <option value="statutory_off">LRD（法定休日）</option>
                  <option value="prescribed_off">ORD（所定休日）</option>
                  <option value="work">Work（出勤）</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
        <button class="btn btn-sm btn-primary w-100" :disabled="saving" @click="save">
          {{ saving ? '保存中…' : '保存' }}
        </button>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  restPatternId: { type: [Number, String], default: '' },
  baseUrl:       { type: String, required: true },
});
const emit = defineEmits(['saved', 'flash']);

const dowNames = ['日', '月', '火', '水', '木', '金', '土'];
const loading  = ref(false);
const saving   = ref(false);
// weekday 0–6 の kind を保持
const rules    = ref(['work', 'work', 'work', 'work', 'work', 'work', 'work']);

async function load() {
  if (!props.restPatternId) return;
  loading.value = true;
  try {
    const res = await axios.get(props.baseUrl, { params: { rest_pattern_id: props.restPatternId } });
    // res.data: { "0": "statutory_off", "1": "work", ... }
    const map = res.data;
    rules.value = Array.from({ length: 7 }, (_, i) => map[i] ?? 'work');
  } catch {
    emit('flash', { text: 'LRD/ORD ルールの読み込みに失敗しました。', ok: false });
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const payload = { rest_pattern_id: props.restPatternId, rules: {} };
    rules.value.forEach((kind, i) => { payload.rules[i] = kind; });
    await axios.put(props.baseUrl, payload);
    emit('flash', { text: 'LRD/ORD ルールを保存しました。', ok: true });
    emit('saved');
  } catch {
    emit('flash', { text: '保存に失敗しました。', ok: false });
  } finally {
    saving.value = false;
  }
}

watch(() => props.restPatternId, load, { immediate: true });
</script>
