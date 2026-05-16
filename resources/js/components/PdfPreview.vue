<template>
  <div>
    <!-- 印刷ボタン (印刷時は非表示) -->
    <div class="print-toolbar">
      <button class="print-btn" @click="print" :disabled="loading">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
        </svg>
        印刷 / PDF で保存
      </button>
    </div>

    <!-- ローディング -->
    <div v-if="loading" class="status-msg">データを読み込んでいます...</div>

    <!-- エラー -->
    <div v-else-if="error" class="status-msg error">{{ error }}</div>

    <!-- PDF 本体 -->
    <div v-else-if="data" class="pdf-page">

      <!-- ページヘッダー -->
      <div class="page-header">
        <div class="page-header-title">{{ data.meta.title }}</div>
        <div class="page-header-date">{{ today }}</div>
      </div>
      <div class="page-meta">
        Date:
        <span v-if="data.meta.range_from">{{ data.meta.range_from }}</span>
        <span v-if="data.meta.range_to"> ~ {{ data.meta.range_to }}</span>
      </div>

      <!-- 日付グループ -->
      <div v-for="group in data.groups" :key="group.date" class="date-group">
        <div class="date-heading">{{ formatDateHeading(group.date) }}</div>

        <!-- Sublist テーブル -->
        <template v-if="type === 'sublist'">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>School</th>
                <th>Regular Teacher</th>
                <th>Substitute Teacher</th>
                <th>Start</th>
                <th>End</th>
                <th class="col-wide">Classes</th>
                <th>Leave Type</th>
                <th>Shift Type</th>
                <th v-if="data.meta.mode === 'master'">Status</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, idx) in group.rows" :key="idx">
                <tr :class="idx % 2 === 0 ? 'row-even' : 'row-odd'">
                  <td>{{ row.title }}</td>
                  <td>{{ row.school_name }}</td>
                  <td>{{ row.original_user }}</td>
                  <td>
                    {{ row.assigned_user }}
                    <span v-if="row.assigned_user && data.meta.mode === 'final'" class="emp-code">[{{ row.employee_code }}]</span>
                  </td>
                  <td class="col-time">{{ row.start_time }}</td>
                  <td class="col-time">{{ row.end_time }}</td>
                  <td class="col-wide">{{ row.lesson }}</td>
                  <td>{{ row.leave_type }}</td>
                  <td>{{ row.type_label }}</td>
                  <td v-if="data.meta.mode === 'master'">{{ row.status }}</td>
                </tr>
                <tr v-if="data.meta.mode === 'master'" class="note-row">
                  <td :colspan="data.meta.mode === 'master' ? 10 : 9">
                    <span class="note-label">Note:</span>
                    {{ row.notes || '—' }}
                  </td>
                </tr>
              </template>
              <tr v-if="group.rows.length === 0">
                <td :colspan="data.meta.mode === 'master' ? 10 : 9" class="no-records">No records.</td>
              </tr>
            </tbody>
          </table>

          <!-- Sub サマリ -->
          <div v-if="group.sub_summary" class="sub-summary">
            <template v-if="data.meta.mode === 'master'">
              <div><span class="sub-label">Sub (Present {{ group.sub_summary.present_count }}):</span> {{ group.sub_summary.present_text }}</div>
              <div v-if="group.sub_summary.absent_count > 0"><span class="sub-label">Sub (Absent {{ group.sub_summary.absent_count }}):</span> {{ group.sub_summary.absent_text }}</div>
            </template>
            <template v-else>
              <div v-if="group.sub_summary.absent_count > 0">
                <span class="sub-label">Sub (Absent {{ group.sub_summary.absent_count }}):</span> {{ group.sub_summary.absent_text }}
              </div>
            </template>
          </div>
        </template>

        <!-- Confirmations テーブル -->
        <template v-else-if="type === 'confirmations'">
          <table>
            <thead>
              <tr>
                <th>School</th>
                <th v-if="data.meta.mode === 'ot'">Original Teacher</th>
                <th>Start</th>
                <th>End</th>
                <th class="col-wide">Classes</th>
                <th v-if="data.meta.mode === 'alp'">Leave Type</th>
                <th v-if="data.meta.mode === 'ot'">Shift Type</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in group.rows" :key="idx" :class="idx % 2 === 0 ? 'row-even' : 'row-odd'">
                <td>{{ row.school_name }}</td>
                <td v-if="data.meta.mode === 'ot'">{{ row.original_user }}</td>
                <td class="col-time">{{ row.start_time }}</td>
                <td class="col-time">{{ row.end_time }}</td>
                <td class="col-wide">{{ row.lesson }}</td>
                <td v-if="data.meta.mode === 'alp'">{{ row.leave_type }}</td>
                <td v-if="data.meta.mode === 'ot'">{{ row.type_label }}</td>
              </tr>
              <tr v-if="group.rows.length === 0">
                <td :colspan="data.meta.mode === 'ot' ? 6 : 5" class="no-records">No records.</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <p v-if="data.groups.length === 0" class="no-records">No records.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  type:        { type: String, required: true },
  queryString: { type: String, default: '' },
});

const data    = ref(null);
const loading = ref(true);
const error   = ref(null);

const today = computed(() => new Date().toISOString().slice(0, 10));

const apiUrl = computed(() => {
  const base = props.type === 'sublist'
    ? '/calendar/edit/pdf-data'
    : '/calendar/confirmations/pdf-data';
  return props.queryString ? `${base}?${props.queryString}` : base;
});

const DAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];
function formatDateHeading(ymd) {
  const d = new Date(ymd);
  if (isNaN(d)) return ymd;
  const day = DAYS_JA[d.getDay()];
  return `${ymd} (${day})`;
}

function print() {
  window.print();
}

onMounted(async () => {
  try {
    const res = await axios.get(apiUrl.value);
    data.value = res.data;
  } catch (e) {
    error.value = 'データの取得に失敗しました。' + (e.response?.data?.message ?? e.message);
  } finally {
    loading.value = false;
  }
});
</script>

<style>
/* ===== 印刷ボタンエリア ===== */
.print-toolbar {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 100;
}
.print-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #1f2937;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
  transition: background 0.15s;
}
.print-btn:hover { background: #374151; }
.print-btn:disabled { opacity: 0.5; cursor: default; }
.print-btn svg { width: 16px; height: 16px; }

/* ===== ステータスメッセージ ===== */
.status-msg {
  padding: 40px;
  text-align: center;
  color: #6b7280;
  font-family: 'Noto Sans JP', sans-serif;
}
.status-msg.error { color: #dc2626; }

/* ===== PDF 本体 ===== */
.pdf-page {
  background: #fff;
  width: 297mm;         /* A4 横 */
  min-height: 210mm;
  margin: 20px auto;
  padding: 12mm;
  box-shadow: 0 2px 16px rgba(0,0,0,0.12);
  font-family: 'Noto Sans JP', sans-serif;
  font-size: 9pt;
  color: #111827;
  box-sizing: border-box;
}

/* ===== ページヘッダー ===== */
.page-header {
  background: #1f2937;
  color: #fff;
  padding: 8px 12px;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 4px;
}
.page-header-title { font-size: 14pt; font-weight: 700; }
.page-header-date  { font-size: 9pt; opacity: 0.75; }
.page-meta {
  font-size: 9pt;
  color: #6b7280;
  margin-bottom: 6mm;
}

/* ===== 日付セクション ===== */
.date-group { margin-bottom: 8mm; }
.date-heading {
  background: #374151;
  color: #fff;
  padding: 4px 8px;
  font-size: 10pt;
  font-weight: 600;
  margin-bottom: 2px;
  page-break-before: auto;
}

/* ===== テーブル ===== */
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 3px;
}
th, td {
  border: 1px solid #e5e7eb;
  padding: 3px 4px;
  vertical-align: top;
  font-size: 8.5pt;
}
th {
  background: #6b7280;
  color: #fff;
  font-weight: 600;
  text-align: left;
}
.row-even { background: #fff; }
.row-odd  { background: #f9fafb; }

.col-time { white-space: nowrap; width: 36px; }
.col-wide { min-width: 50mm; overflow-wrap: anywhere; word-break: break-word; }

.emp-code { color: #9ca3af; font-size: 7.5pt; }
.no-records { text-align: center; color: #9ca3af; }

/* ===== Note 行 ===== */
.note-row td {
  border-top: 0;
  padding-top: 0.5em;
  font-size: 8pt;
  color: #374151;
}
.note-label { font-weight: 600; }

/* ===== Sub サマリ ===== */
.sub-summary {
  font-size: 8pt;
  color: #6b7280;
  margin: 2px 0 4mm 0;
}
.sub-label { font-weight: 600; color: #374151; }

/* ===== 印刷メディア ===== */
@media print {
  body { background: #fff !important; }

  .print-toolbar { display: none !important; }

  .pdf-page {
    width: 100%;
    margin: 0;
    padding: 0;
    box-shadow: none;
  }

  @page {
    size: A4 landscape;
    margin: 12mm;
  }

  .date-group { page-break-inside: avoid; }
  .date-heading { page-break-before: auto; }
}
</style>
