<template>
  <div>
    <!-- フラッシュ -->
    <div v-if="flash.text" :class="['alert','py-2','px-3','mb-3', flash.ok ? 'alert-success' : 'alert-danger']">
      {{ flash.text }}
    </div>

    <!-- ── 上部フォーム ── -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="d-flex align-items-end gap-4 flex-nowrap">
          <!-- セレクタ -->
          <div class="d-flex gap-2 align-items-end flex-nowrap">
            <div>
              <label class="form-label small mb-1">Rest Pattern</label>
              <select v-model="selectedPatternId" class="form-select form-select-sm" style="min-width:220px">
                <option value="">— All / Pattern-independent —</option>
                <option v-for="p in patterns" :key="p.id" :value="p.id">
                  {{ p.name }}（{{ p.code }}）
                </option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">Fiscal Year</label>
              <select v-model="fiscalYear" class="form-select form-select-sm">
                <option v-for="y in fiscalYearOptions" :key="y" :value="y">{{ y }}年度</option>
              </select>
            </div>
          </div>
          <!-- 集計（右側） -->
          <div class="summary-area ms-auto">
            <div v-if="!selectedPatternId" class="text-muted small pt-1">Rest Pattern を選択すると集計されます。</div>
            <div v-else class="rest-summary">
              <div class="summary-item summary-total">
                <span class="summary-label">合計</span>
                <span class="summary-value">{{ restDaySummary.total }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">祝日</span>
                <span class="summary-value">{{ restDaySummary.holiday }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">振替</span>
                <span class="summary-value">{{ restDaySummary.furikae }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">LRD</span>
                <span class="summary-value">{{ restDaySummary.lrd }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">ORD</span>
                <span class="summary-value">{{ restDaySummary.ord }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">調休</span>
                <span class="summary-value">{{ restDaySummary.addOff }}日</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Closure</span>
                <span class="summary-value">{{ restDaySummary.closure }}日</span>
              </div>
              <div class="summary-item summary-reference">
                <span class="summary-label">参考 RWD</span>
                <span class="summary-value">{{ restDaySummary.rwd }}日</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── 2カラム ── -->
    <div class="row g-3">

      <!-- 左側：編集エリア (1/3) -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header py-2 d-flex align-items-center gap-2">
            <span class="fw-semibold small">編集対象</span>
            <select v-model="editTarget" class="form-select form-select-sm" style="width:auto">
              <option value="adjustment">Adjustment</option>
              <option value="closure">Company Closure</option>
              <option value="holiday">Holidays</option>
              <option value="lrd_ord">LRD / ORD</option>
            </select>
          </div>
          <div class="card-body p-2" style="overflow-y:auto;max-height:80vh">

            <!-- Adjustment フォーム -->
            <template v-if="editTarget === 'adjustment'">
              <AdjustingPanel
                :fiscal-year="fiscalYear"
                :rest-pattern-id="selectedPatternId"
                :base-url="adjustingUrl"
                @saved="refreshCalendar"
                @deleted="refreshCalendar"
                @flash="showFlash"
              />
            </template>

            <!-- Company Closure フォーム -->
            <template v-else-if="editTarget === 'closure'">
              <ClosurePanel
                :fiscal-year="fiscalYear"
                :base-url="closureUrl"
                @saved="refreshCalendar"
                @deleted="refreshCalendar"
                @flash="showFlash"
              />
            </template>

            <!-- Holidays フォーム -->
            <template v-else-if="editTarget === 'holiday'">
              <HolidayPanel
                :fiscal-year="fiscalYear"
                :base-url="holidayUrl"
                @saved="refreshCalendar"
                @deleted="refreshCalendar"
                @flash="showFlash"
              />
            </template>

            <!-- LRD / ORD フォーム -->
            <template v-else-if="editTarget === 'lrd_ord'">
              <RestPatternRulePanel
                :rest-pattern-id="selectedPatternId"
                :base-url="patternRuleUrl"
                @saved="refreshCalendar"
                @flash="showFlash"
              />
            </template>

          </div>
        </div>
      </div>

      <!-- 右側：ミニカレンダー (2/3) -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header py-2 fw-semibold small">
            {{ fiscalYear }}年度カレンダー（{{ fiscalYear }}/04 〜 {{ fiscalYear + 1 }}/03）
          </div>
          <div class="card-body p-2">
            <div v-if="calLoading" class="text-center py-4">
              <div class="spinner-border text-secondary spinner-border-sm"></div>
            </div>
            <div v-else class="row g-2">
              <div
                v-for="month in calendarMonths"
                :key="month.key"
                class="col-6 col-md-4 col-xl-3"
              >
                <MiniCalendar :month="month" :events="calEvents" />
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import AdjustingPanel      from './cpe/AdjustingPanel.vue';
import ClosurePanel        from './cpe/ClosurePanel.vue';
import HolidayPanel        from './cpe/HolidayPanel.vue';
import RestPatternRulePanel from './cpe/RestPatternRulePanel.vue';
import MiniCalendar        from './cpe/MiniCalendar.vue';

const props = defineProps({
  patterns:       { type: Array,  default: () => [] },
  eventsUrl:      { type: String, required: true },
  holidayUrl:     { type: String, required: true },
  adjustingUrl:   { type: String, required: true },
  closureUrl:     { type: String, required: true },
  patternRuleUrl: { type: String, required: true },
});

// ── State ──────────────────────────────────────────────────────────────────
const currentFy = (() => {
  const m = new Date().getMonth() + 1;
  const y = new Date().getFullYear();
  return m >= 4 ? y : y - 1;
})();

const fiscalYear       = ref(currentFy);
const selectedPatternId = ref('');
const editTarget       = ref('adjustment');
const calLoading       = ref(false);
const calEvents        = ref([]);
const flash            = ref({ text: '', ok: true });

// ── Fiscal year select options (current ± 3) ───────────────────────────────
const fiscalYearOptions = computed(() => {
  const base = currentFy;
  return Array.from({ length: 7 }, (_, i) => base - 3 + i);
});

// ── 12ヶ月リスト（4月〜翌3月）──────────────────────────────────────────────
const calendarMonths = computed(() => {
  const months = [];
  for (let i = 0; i < 12; i++) {
    const m = ((3 + i) % 12) + 1; // 4,5,...,12,1,2,3
    const y = m >= 4 ? fiscalYear.value : fiscalYear.value + 1;
    months.push({ key: `${y}-${String(m).padStart(2, '0')}`, year: y, month: m });
  }
  return months;
});

// ── Flash helper ───────────────────────────────────────────────────────────
let flashTimer = null;
function showFlash({ text, ok = true }) {
  flash.value = { text, ok };
  clearTimeout(flashTimer);
  flashTimer = setTimeout(() => { flash.value = { text: '', ok: true }; }, 3000);
}

// ── Calendar events fetch ─────────────────────────────────────────────────
async function refreshCalendar() {
  calLoading.value = true;
  try {
    const params = { fiscal_year: fiscalYear.value };
    if (selectedPatternId.value) params.rest_pattern_id = selectedPatternId.value;
    const res = await axios.get(props.eventsUrl, { params });
    calEvents.value = res.data;
  } catch {
    showFlash({ text: 'カレンダーの読み込みに失敗しました。', ok: false });
  } finally {
    calLoading.value = false;
  }
}

// ── Rest Day 集計（1日1カテゴリ解決） ────────────────────────────────────
const restDaySummary = computed(() => {
  // 日付ごとにイベントをグループ化
  const byDate = {};
  for (const e of calEvents.value) {
    const d = e.start;
    if (!byDate[d]) byDate[d] = [];
    byDate[d].push(e);
  }

  const counts = { holiday: 0, furikae: 0, lrd: 0, ord: 0, addOff: 0, closure: 0, rwd: 0 };

  for (const evs of Object.values(byDate)) {
    const isWorkInstead = evs.some(e => e._type === 'adjusting' && e.kind === 'work_instead');
    if (isWorkInstead) counts.rwd++;

    // 優先度: 振替日 > 祝日 > add_off > LRD > ORD > Closure
    // RWD は独立した休日数ではなく、LRD/ORD を休日から外す調整として扱う。
    if (evs.some(e => e._type === 'holiday' && e.is_observed)) {
      counts.furikae++;
    } else if (evs.some(e => e._type === 'holiday' && !e.is_observed)) {
      counts.holiday++;
    } else if (evs.some(e => e._type === 'adjusting' && e.kind === 'add_off')) {
      counts.addOff++;
    } else if (evs.some(e => e._type === 'offday' && e.kind === 'statutory_off')) {
      if (!isWorkInstead) counts.lrd++;
    } else if (evs.some(e => e._type === 'offday' && e.kind === 'prescribed_off')) {
      if (!isWorkInstead) counts.ord++;
    } else if (evs.some(e => e._type === 'closure')) {
      counts.closure++;
    }
  }

  const total = counts.holiday + counts.furikae + counts.lrd + counts.ord + counts.addOff + counts.closure;
  return { ...counts, total };
});

watch([fiscalYear, selectedPatternId], refreshCalendar);
onMounted(refreshCalendar);
</script>

<style scoped>
.summary-area {
  min-width: 0;
  overflow-x: auto;
}

.rest-summary {
  display: flex;
  flex-wrap: nowrap;
  justify-content: flex-end;
  gap: 4px 6px;
  min-width: max-content;
}

.summary-item {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
  min-height: 24px;
  padding: 2px 7px;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background: #fff;
  line-height: 1.2;
  white-space: nowrap;
}

.summary-total {
  border-color: #adb5bd;
  background: #f8f9fa;
}

.summary-reference {
  margin-left: 8px;
  border-style: dashed;
  background: #f8f9fa;
}

.summary-label {
  color: #6c757d;
  font-size: 0.72rem;
}

.summary-value {
  font-size: 0.78rem;
  font-weight: 600;
}
</style>
