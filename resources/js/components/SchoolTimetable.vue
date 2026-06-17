<!-- 学校名で絞り込み、授業コマ・担当講師の時間割を表示するコンポーネント -->
<template>
  <div class="st-root">
    <!-- ===== 検索フォーム ===== -->
    <div class="st-search-bar">
      <label class="st-label">School</label>
      <input
        v-model="schoolQuery"
        list="stSchoolDatalist"
        class="form-control form-control-sm st-school-input"
        placeholder="Partial match search"
        @keyup.enter="search"
      />
      <datalist id="stSchoolDatalist">
        <option
          v-for="s in schoolList"
          :key="s.school_code"
          :value="s.school_name"
        >{{ s.school_code }} / {{ s.school_name }}</option>
      </datalist>

      <label class="st-label">Day</label>
      <select v-model="dowFilter" class="form-select form-select-sm st-dow-select">
        <option :value="null">All days</option>
        <option v-for="(label, val) in dowOptions" :key="val" :value="Number(val)">{{ label }}</option>
      </select>

      <label class="st-label">Reference date</label>
      <input
        v-model="activeOn"
        type="date"
        class="form-control form-control-sm st-date-input"
      />
      <button class="btn btn-primary btn-sm" @click="search">Search</button>
      <button class="btn btn-success btn-sm" @click="openAddLineModal" :disabled="!searched">+ Add Line</button>
    </div>

    <!-- ===== Department チェックボックスフィルター ===== -->
    <div v-if="departmentOptions.length" class="st-dept-filter">
      <span class="st-label">Department:</span>
      <label
        v-for="dept in departmentOptions"
        :key="dept.id"
        class="st-dept-check-label"
      >
        <input
          type="checkbox"
          :value="dept.id"
          v-model="deptFilter"
          class="st-dept-checkbox"
        />
        {{ dept.name }}
      </label>
      <button
        v-if="deptFilter.length < departmentOptions.length"
        class="btn btn-outline-secondary btn-sm st-dept-all-btn"
        @click="deptFilter = departmentOptions.map(d => d.id)"
      >Select all</button>
      <button
        v-if="deptFilter.length > 0"
        class="btn btn-outline-secondary btn-sm st-dept-all-btn"
        @click="deptFilter = []"
      >Clear all</button>
    </div>

    <!-- ===== メッセージ ===== -->
    <div v-if="message" :class="['alert', 'alert-sm', 'py-1', 'px-2', 'mt-1', msgOk ? 'alert-success' : 'alert-danger']">
      {{ message }}
    </div>

    <!-- ===== 2ペイン分割レイアウト ===== -->
    <div v-if="lines.length" class="st-split">

      <!-- ===== 上ペイン: Timetable Grid ===== -->
      <div class="st-pane-grid" :style="{ height: gridPaneHeight + 'px' }">
        <div class="st-section-title st-section-title--pane">Time Grid (9:30–22:30)</div>
        <!-- st-grid-outer のみ overflow-x: auto。ラベル列は sticky で固定 -->
        <div class="st-grid-outer">
        <!-- ヘッダー行 -->
        <div class="st-grid-header">
          <div class="st-row-label st-row-label--header"></div>
          <div class="st-time-header">
            <div
              v-for="h in timeHeaderMarks"
              :key="h.label"
              class="st-time-mark"
              :style="{
                left:      h.leftPx + 'px',
                transform: h.anchor === 'left'  ? 'translateX(0)'
                         : h.anchor === 'right' ? 'translateX(-100%)'
                         : 'translateX(-50%)'
              }"
            >{{ h.label }}</div>
          </div>
        </div>
        <!-- 各 line 行 -->
        <div v-for="line in filteredLines" :key="line.id" class="st-grid-row">
          <div class="st-row-label">
            <div class="st-label-dow">{{ dowLabel(line.dow) }}</div>
            <div class="st-label-user">{{ line.user_name || 'No User' }}</div>
            <div class="st-label-id">#{{ line.id }}</div>
            <div class="st-label-time">{{ line.start_time }}–{{ line.end_time }}</div>
          </div>
          <div class="st-cells-wrap">
            <!-- grid 背景（CSS gradient で正確にピクセルを描画） -->
            <div class="st-cells-bg"></div>
            <!-- event blocks（範囲内のみ） -->
            <div class="st-events">
              <template v-for="d in line.details" :key="d.id">
                <div
                  v-if="!isOutOfRange(d)"
                  class="st-event"
                  :style="eventStyle(d)"
                  :title="`${d.lesson_code} ${d.lesson_name} ${d.start_time} (${d.lesson_minute || 5}min)`"
                  @click="activeDetail = d"
                >
                  <span v-if="d.detail_note" class="st-event-note-dot"></span>
                  <span class="st-event-code">{{ d.lesson_code }}</span>
                  <span class="st-event-name">{{ d.lesson_name }}</span>
                  <span class="st-event-time">{{ d.start_time }}</span>
                </div>
              </template>
            </div>
          </div>
        </div>
        <!-- 範囲外 warning -->
        <div v-if="hasOutOfRange" class="st-oor-warning">
          ⚠ Some details fall outside 9:30–22:30 and are hidden from the grid.
        </div>
        </div><!-- /st-grid-outer -->
      </div><!-- /st-pane-grid -->

      <!-- 中央リサイズハンドル（グリッド高さを変更） -->
      <div class="st-resize-handle" @mousedown="startResizePane($event, 'gridPaneHeight')">
        <span class="st-resize-grip">&#8942;&#8942;&#8942;</span>
      </div>

      <!-- ===== 下ペイン: Lines Editor ===== -->
      <div class="st-pane-lines" :style="{ height: linesPaneHeight + 'px' }">
        <div class="st-section-title st-section-title--pane">Schedule Lines</div>
      <div v-for="line in filteredLines" :key="'ed-' + line.id" class="st-line-block">

        <!-- Line header -->
        <div class="st-line-header">
          <span class="st-line-id">#{{ line.id }}</span>
          <span class="st-line-school">{{ line.school_name }}</span>
          <span v-if="line.department_name" class="st-line-dept">{{ line.department_name }}</span>
          <span v-if="line.user_name" class="st-line-user">{{ line.user_name }}</span>
          <span class="st-line-meta">{{ dowLabel(line.dow) }} {{ line.start_time }}–{{ line.end_time }}</span>
          <div class="ms-auto d-flex gap-1">
            <button class="btn btn-sm btn-primary" @click="saveLine(line)">Save Line</button>
            <button class="btn btn-sm btn-outline-danger" @click="deleteLine(line)">Delete Line</button>
          </div>
        </div>

        <!-- Line fields -->
        <div class="st-line-fields">
          <div class="st-field-group">
            <label class="st-label">School name</label>
            <input v-model="line.school_name" class="form-control form-control-sm" />
          </div>
          <div class="st-field-group">
            <label class="st-label">Instructor</label>
            <select v-model="line.user_id" class="form-select form-select-sm">
              <option :value="null">(Unassigned)</option>
              <option v-for="u in userOptions" :key="u.id" :value="u.id">{{ u.label }}</option>
            </select>
          </div>
          <div class="st-field-group">
            <label class="st-label">Day</label>
            <select v-model="line.dow" class="form-select form-select-sm">
              <option v-for="(label, val) in dowOptions" :key="val" :value="Number(val)">{{ label }}</option>
            </select>
          </div>
          <div class="st-field-group">
            <label class="st-label">Start</label>
            <input v-model="line.start_time" type="time" class="form-control form-control-sm" />
          </div>
          <div class="st-field-group">
            <label class="st-label">End</label>
            <input v-model="line.end_time" type="time" class="form-control form-control-sm" />
          </div>
          <div class="st-field-group">
            <label class="st-label">Total min</label>
            <span class="form-control-sm d-block">{{ line.total_minutes }}</span>
          </div>
          <div class="st-field-group">
            <label class="st-label">Effective from</label>
            <input v-model="line.effective_start" type="date" class="form-control form-control-sm" />
          </div>
          <div class="st-field-group">
            <label class="st-label">Effective to</label>
            <input v-model="line.effective_end" type="date" class="form-control form-control-sm" />
          </div>
        </div>

        <!-- Details -->
        <div class="st-details-area">
          <div class="st-details-header">
            <span>Details</span>
            <button class="btn btn-sm btn-outline-secondary" @click="addDetail(line)">+ Add Detail</button>
            <button class="btn btn-sm btn-primary" @click="saveDetails(line)">Save Details</button>
          </div>

          <div v-if="!line.details.length" class="text-muted small px-2 py-1">No details.</div>

          <!-- detail rows -->
          <div
            v-for="d in line.details"
            :key="'d-' + d.id"
            class="st-detail-row"
            :class="{ 'st-detail-oor': isOutOfRange(d) }"
          >
            <div class="st-detail-field">
              <label class="st-label">Start time</label>
              <input v-model="d.start_time" type="time" class="form-control form-control-sm" />
            </div>
            <div class="st-detail-field">
              <label class="st-label">End (calc)</label>
              <span class="form-control-sm d-block">{{ calcDetailEnd(d) }}</span>
            </div>
            <div class="st-detail-field st-detail-field--code">
              <label class="st-label">lesson_code</label>
              <input
                :value="d.lesson_code"
                list="st-lesson-code-datalist"
                class="form-control form-control-sm"
                autocomplete="off"
                @input="onLessonInput(d, $event)"
                @change="fetchLessonFromEvent(d, $event)"
                @keyup.enter="fetchLessonFromEvent(d, $event)"
              />
            </div>
            <div class="st-detail-field st-detail-field--name">
              <label class="st-label">lesson_name</label>
              <input v-model="d.lesson_name" class="form-control form-control-sm" readonly />
            </div>
            <div class="st-detail-field">
              <label class="st-label">Min</label>
              <input v-model.number="d.lesson_minute" type="number" class="form-control form-control-sm" readonly />
            </div>
            <div class="st-detail-field">
              <label class="st-label">Effective from</label>
              <input v-model="d.effective_start" type="date" class="form-control form-control-sm" />
            </div>
            <div class="st-detail-field">
              <label class="st-label">Effective to</label>
              <input v-model="d.effective_end" type="date" class="form-control form-control-sm" />
            </div>
            <div class="st-detail-field st-detail-field--dnote">
              <label class="st-label">Note</label>
              <input v-model="d.detail_note" class="form-control form-control-sm" />
            </div>
            <div class="st-detail-field st-detail-field--actions">
              <button class="btn btn-sm btn-outline-danger" @click="deleteDetail(line, d)" title="Delete">✕</button>
            </div>
            <div v-if="isOutOfRange(d)" class="st-oor-tag">Out of range</div>
          </div><!-- /st-detail-row -->
        </div><!-- /st-details-area -->
      </div><!-- /st-line-block (v-for) -->
      </div><!-- /st-pane-lines -->

      <!-- 下部リサイズハンドル（Lines Editor 高さを変更） -->
      <div class="st-resize-handle" @mousedown="startResizePane($event, 'linesPaneHeight')">
        <span class="st-resize-grip">&#8942;&#8942;&#8942;</span>
      </div>
    </div><!-- /st-split -->

    <!-- ===== 新規 Line 追加モーダル ===== -->
    <div v-if="showAddLineModal" class="st-popup-overlay" @click.self="showAddLineModal = false">
      <div class="st-popup st-add-line-popup">
        <button class="st-popup-close" @click="showAddLineModal = false">✕</button>
        <div class="st-popup-title">Add New Line</div>
        <div class="st-field-group st-add-line-field">
          <label class="st-label">Department</label>
          <select v-model="newLineDeptId" class="form-select form-select-sm">
            <option v-for="dept in allDepartmentOptions" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-3">
          <button class="btn btn-secondary btn-sm" @click="showAddLineModal = false">Cancel</button>
          <button class="btn btn-success btn-sm" @click="confirmAddLine" :disabled="!newLineDeptId">Add</button>
        </div>
      </div>
    </div>

    <!-- ===== Toast 通知 ===== -->
    <div v-if="toastMessage" :class="['st-toast', toastOk ? 'st-toast--ok' : 'st-toast--err']">{{ toastMessage }}</div>

    <!-- ===== 削除確認モーダル ===== -->
    <div v-if="deleteModal.show" class="st-popup-overlay" @click.self="deleteModal.show = false">
      <div class="st-popup st-delete-popup">
        <button class="st-popup-close" @click="deleteModal.show = false">✕</button>
        <div class="st-popup-title st-delete-title">{{ deleteModal.title }}</div>
        <div class="st-delete-body">{{ deleteModal.body }}</div>
        <div class="d-flex gap-2 justify-content-end mt-3">
          <button class="btn btn-secondary btn-sm" @click="deleteModal.show = false">Cancel</button>
          <button class="btn btn-danger btn-sm" @click="confirmDelete">Delete</button>
        </div>
      </div>
    </div>

    <!-- ===== Detail ポップアップ ===== -->
    <div v-if="activeDetail" class="st-popup-overlay" @click.self="activeDetail = null">
      <div class="st-popup">
        <button class="st-popup-close" @click="activeDetail = null">✕</button>
        <div class="st-popup-title">{{ activeDetail.lesson_code }} — {{ activeDetail.lesson_name }}</div>
        <div class="st-popup-row"><span>Start:</span> {{ activeDetail.start_time }}</div>
        <div class="st-popup-row"><span>Duration:</span> {{ activeDetail.lesson_minute || 5 }} min</div>
        <div class="st-popup-row"><span>Effective:</span> {{ activeDetail.effective_start }} – {{ activeDetail.effective_end || '—' }}</div>
        <div class="st-popup-row st-popup-note" v-if="activeDetail.detail_note">
          <span>Note:</span> {{ activeDetail.detail_note }}
        </div>
        <div v-else class="st-popup-row text-muted"><span>Note:</span> No note</div>
      </div>
    </div>

    <!-- lesson 補完用 datalist（全 detail 行で共有） -->
    <datalist id="st-lesson-code-datalist">
      <option
        v-for="opt in lessonList"
        :key="opt.id"
        :value="opt.lesson_code"
      >[{{ opt.lesson_code }}] {{ opt.ps_unique_lesson_code }}</option>
    </datalist>

    <div v-if="searched && !lines.length && !loading" class="text-muted mt-3">
      No schedule lines found.
    </div>
    <div v-if="searched && lines.length && !filteredLines.length && !loading" class="text-muted mt-3">
      No schedule lines match the selected department(s).
    </div>
  </div>
</template>

<script>
// Grid 定数（9:30〜22:30、5分刻み、156 slots）
const GRID_START_MIN = 9 * 60 + 30;  // 570
const GRID_END_MIN   = 22 * 60 + 30; // 1350
const GRID_TOTAL_MIN = GRID_END_MIN - GRID_START_MIN; // 780
const SLOT_MIN       = 5;
const SLOT_COUNT     = GRID_TOTAL_MIN / SLOT_MIN; // 156
const SLOT_PX        = 10; // 1 slot = 10px
const CELLS_WIDTH_PX = SLOT_COUNT * SLOT_PX; // 1560px

function timeToMin(hhmm) {
  if (!hhmm) return null;
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + m;
}

function minToHhmm(min) {
  const h = Math.floor(min / 60);
  const m = min % 60;
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

export default {
  name: 'SchoolTimetable',

  props: {
    initialSchool:       { type: String, default: '' },
    initialDow:          { default: null },
    userOptions:         { type: Array,  default: () => [] },
    dowOptions:          { type: Object, default: () => ({}) },
    departmentOptions:    { type: Array,  default: () => [] },
    allDepartmentOptions: { type: Array,  default: () => [] },
    csrfToken:           { type: String, default: '' },
    apiLinesUrl:         { type: String, default: '' },
    schoolsUrl:          { type: String, default: '' },
    lessonsUrl:          { type: String, default: '' },
    storeLinesUrl:       { type: String, default: '' },
    bulkUpdateLinesUrl:  { type: String, default: '' },
    lessonByCodeUrl:     { type: String, default: '' },
  },

  data() {
    return {
      schoolQuery: this.initialSchool,
      // initialDow が null でなければ数値化
      dowFilter:   this.initialDow !== null ? Number(this.initialDow) : null,
      activeOn:    new Date().toISOString().slice(0, 10),
      lines:       [],
      schoolList:  [],
      lessonList:  [],
      loading:     false,
      searched:    !!this.initialSchool,
      message:       '',
      msgOk:         true,
      // department チェックボックスフィルター（全選択が初期値）
      deptFilter:  this.departmentOptions.map(d => d.id),
      // 各ペインの高さ（px）。リサイズハンドルで変更
      gridPaneHeight:  280,
      linesPaneHeight: 400,
      // クリックされた detail（ポップアップ表示用）
      activeDetail: null,
      // 新規 Line 追加モーダル
      showAddLineModal: false,
      newLineDeptId:    null,
      // トースト通知
      toastMessage: '',
      toastOk:      true,
      toastTimer:   null,
      // 削除確認モーダル
      deleteModal: { show: false, title: '', body: '', onConfirm: null },
    };
  },

  computed: {
    // 9:30 〜 22:30 の時刻ラベルマーク（整時 + 9:30/22:30 端点）
    timeHeaderMarks() {
      const marks = [];
      // 左端: translateX(0) で左寄せ
      marks.push({ label: '9:30', leftPx: 0, anchor: 'left' });
      for (let h = 10; h <= 22; h++) {
        const minFromStart = h * 60 - GRID_START_MIN;
        marks.push({ label: `${h}:00`, leftPx: minFromStart / SLOT_MIN * SLOT_PX, anchor: 'center' });
      }
      // 右端: translateX(-100%) で右寄せ
      marks.push({ label: '22:30', leftPx: CELLS_WIDTH_PX, anchor: 'right' });
      return marks;
    },

    filteredLines() {
      if (!this.departmentOptions.length) return this.lines;
      if (!this.deptFilter.length) return [];
      return this.lines.filter(l => this.deptFilter.includes(l.department_id));
    },

    hasOutOfRange() {
      return this.filteredLines.some(l => l.details.some(d => this.isOutOfRange(d)));
    },
  },

  mounted() {
    this.fetchSchools();
    this.fetchLessons();
    if (this.schoolQuery) {
      this.searched = true;
      this.search();
    }
  },

  methods: {
    // ---- リサイズハンドルのドラッグ処理（prop: 変更するデータキー名） ----
    startResizePane(e, prop) {
      e.preventDefault();
      const startY = e.clientY;
      const startH = this[prop];
      const onMove = (ev) => {
        const newH = Math.max(120, Math.min(startH + ev.clientY - startY, window.innerHeight * 0.85));
        this[prop] = Math.round(newH);
      };
      const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup',   onUp);
      };
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup',   onUp);
    },

    dowLabel(dow) {
      const map = { 0: 'Sun', 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat' };
      return map[dow] ?? '?';
    },

    showMsg(ok, text) {
      this.msgOk   = ok;
      this.message = text;
    },

    // ---- School datalist ----

    async fetchSchools() {
      if (!this.schoolsUrl) return;
      try {
        const res  = await fetch(this.schoolsUrl, {
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok) this.schoolList = data.schools;
      } catch (e) {
        // silent: datalist は補助機能なので失敗しても問題なし
      }
    },

    async fetchLessons() {
      if (!this.lessonsUrl) return;
      try {
        const res  = await fetch(this.lessonsUrl, {
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok) this.lessonList = data.lessons;
      } catch (e) {
        // silent: datalist は補助機能なので失敗しても問題なし
      }
    },

    // ---- 検索（URL クエリパラメータも同期） ----

    async search() {
      if (!this.schoolQuery.trim()) {
        this.showMsg(false, 'Please enter a school name.');
        return;
      }
      this.loading  = true;
      this.message  = '';
      this.searched = true;

      // refresh 後も検索条件を保持するため URL を更新
      const params = new URLSearchParams();
      params.set('school', this.schoolQuery.trim());
      if (this.dowFilter !== null && this.dowFilter !== '') {
        params.set('dow', String(this.dowFilter));
      }
      window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);

      try {
        const dowParam = this.dowFilter !== null && this.dowFilter !== ''
          ? `&dow=${this.dowFilter}` : '';
        const url = `${this.apiLinesUrl}?school=${encodeURIComponent(this.schoolQuery)}&active_on=${this.activeOn}${dowParam}`;
        const res  = await fetch(url, { headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.ok) {
          this.lines = data.lines;
        } else {
          this.showMsg(false, data.message || 'Failed to load lines.');
        }
      } catch (e) {
        this.showMsg(false, 'Network error.');
      } finally {
        this.loading = false;
      }
    },

    // ---- Time Grid ----

    isOutOfRange(d) {
      const s = timeToMin(d.start_time);
      if (s === null) return false;
      return s < GRID_START_MIN || s >= GRID_END_MIN;
    },

    // ピクセル基準でイベントを配置（スロット背景と座標系を統一）
    eventStyle(d) {
      const startMin     = timeToMin(d.start_time);
      const duration     = Math.max(SLOT_MIN, d.lesson_minute || SLOT_MIN);
      // グリッド開始からの 5 分スロット数（0 始まり）
      const slotOffset   = (startMin - GRID_START_MIN) / SLOT_MIN;
      const slotDuration = Math.max(1, Math.ceil(duration / SLOT_MIN));
      return {
        left:            `${slotOffset * SLOT_PX}px`,
        width:           `${slotDuration * SLOT_PX}px`,
        backgroundColor: this.lessonColor(d.lesson_code),
      };
    },

    lessonColor(code) {
      if (!code) return '#adb5bd';
      let hash = 0;
      for (let i = 0; i < code.length; i++) {
        hash = code.charCodeAt(i) + ((hash << 5) - hash);
      }
      const hue = Math.abs(hash) % 360;
      return `hsl(${hue}, 55%, 68%)`;
    },

    calcDetailEnd(d) {
      const s = timeToMin(d.start_time);
      if (s === null) return '—';
      return minToHhmm(s + Math.max(SLOT_MIN, d.lesson_minute || SLOT_MIN));
    },

    // ---- Line CRUD ----

    openAddLineModal() {
      if (!this.allDepartmentOptions.length) {
        this.newLineDeptId = null;
        this.addLine();
        return;
      }
      this.newLineDeptId = this.allDepartmentOptions[0].id;
      this.showAddLineModal = true;
    },

    async confirmAddLine() {
      this.showAddLineModal = false;
      await this.addLine();
    },

    showToast(msg, ok = true) {
      this.toastMessage = msg;
      this.toastOk      = ok;
      clearTimeout(this.toastTimer);
      this.toastTimer = setTimeout(() => { this.toastMessage = ''; }, 3500);
    },

    async addLine() {
      try {
        const res  = await fetch(this.storeLinesUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id:       null,
            school_name:   this.schoolQuery.trim(),
            dow:           this.dowFilter !== null ? this.dowFilter : 0,
            department_id: this.newLineDeptId,
          }),
        });
        const data = await res.json();
        if (data.ok) {
          this.showToast(data.message);
          await this.search();
        } else {
          this.showToast(data.message || 'Failed to create line.', false);
        }
      } catch (e) {
        this.showToast('Network error.', false);
      }
    },

    async saveLine(line) {
      try {
        const payload = {
          items: [{
            id:              line.id,
            user_id:         line.user_id,
            dow:             line.dow,
            school_name:     line.school_name,
            start_time:      line.start_time,
            end_time:        line.end_time,
            effective_start: line.effective_start,
            effective_end:   line.effective_end,
            handover_memo:   line.handover_memo || null,
          }],
        };
        const res  = await fetch(`/schedule_lines/bulk-update`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.ok) {
          this.showToast(data.message || `Line #${line.id} saved.`);
          await this.search();
        } else {
          this.showToast(data.message || 'Failed to save line.', false);
        }
      } catch (e) {
        this.showToast('Network error.', false);
      }
    },

    deleteLine(line) {
      this.deleteModal = {
        show:      true,
        title:     `Delete Line #${line.id}?`,
        body:      `"${line.school_name}" Line #${line.id} and all associated details will be permanently deleted. This cannot be undone.`,
        onConfirm: async () => {
          try {
            const res  = await fetch(`/schedule_lines/${line.id}`, {
              method:  'DELETE',
              headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.ok) {
              this.showToast(data.message || `Line #${line.id} deleted.`);
              this.lines = this.lines.filter(l => l.id !== line.id);
            } else {
              this.showToast(data.message || 'Failed to delete line.', false);
            }
          } catch (e) {
            this.showToast('Network error.', false);
          }
        },
      };
    },

    // ---- Detail CRUD ----

    async addDetail(line) {
      try {
        const res  = await fetch(`/schedule_lines/${line.id}/details`, {
          method:  'POST',
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok) {
          this.showToast(data.message || `Detail added to Line #${line.id}.`);
          await this.search();
        } else {
          this.showToast(data.message || 'Failed to add detail.', false);
        }
      } catch (e) {
        this.showToast('Network error.', false);
      }
    },

    deleteDetail(line, d) {
      this.deleteModal = {
        show:      true,
        title:     'Delete Detail?',
        body:      `${d.lesson_code || '(no code)'} at ${d.start_time} will be permanently deleted. This cannot be undone.`,
        onConfirm: async () => {
          try {
            const res  = await fetch(`/schedule_details/${d.id}`, {
              method:  'DELETE',
              headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.ok) {
              this.showToast(data.message || 'Detail deleted.');
              line.details = line.details.filter(x => x.id !== d.id);
            } else {
              this.showToast(data.message || 'Failed to delete detail.', false);
            }
          } catch (e) {
            this.showToast('Network error.', false);
          }
        },
      };
    },

    confirmDelete() {
      this.deleteModal.show = false;
      if (this.deleteModal.onConfirm) this.deleteModal.onConfirm();
    },

    async saveDetails(line) {
      try {
        const items = line.details.map(d => ({
          id:              d.id,
          lesson_code:     d.lesson_code,
          note:            d.note || null,
          detail_note:     d.detail_note ?? null,
          start_time:      d.start_time,
          effective_start: d.effective_start,
          effective_end:   d.effective_end || null,
        }));
        const res  = await fetch(`/schedule_lines/${line.id}/details/bulk-update`, {
          method:  'POST',
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body:    JSON.stringify({ items }),
        });
        const data = await res.json();
        if (data.ok) {
          this.showToast(data.message || `Details for Line #${line.id} saved.`);
          await this.search();
        } else {
          this.showToast(data.message || 'Failed to save details.', false);
        }
      } catch (e) {
        this.showToast('Network error.', false);
      }
    },

    async fetchLesson(d) {
      const code = (d.lesson_code || '').trim();
      if (!code) return;
      try {
        const res  = await fetch(`${this.lessonByCodeUrl}/${encodeURIComponent(code)}`, {
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok && data.lesson) {
          d.lesson_name   = data.lesson.lesson_name;
          d.lesson_minute = data.lesson.lesson_minute;
          d.note          = data.lesson.note || '';
        }
      } catch (e) {
        // silent
      }
    },

    onLessonInput(d, event) {
      const code = event.target.value;
      d.lesson_code = code;
      // datalist 選択時は options と完全一致する → 即時フェッチ
      const matched = this.lessonList.some(opt => opt.lesson_code === code);
      if (matched) this.fetchLessonFromEvent(d, event);
    },

    async fetchLessonFromEvent(d, event) {
      const code = (event.target.value || '').trim();
      d.lesson_code = code;
      if (!code) return;
      try {
        const res  = await fetch(`${this.lessonByCodeUrl}/${encodeURIComponent(code)}`, {
          headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok && data.lesson) {
          d.lesson_name   = data.lesson.lesson_name;
          d.lesson_minute = data.lesson.lesson_minute;
          d.note          = data.lesson.note || '';
        }
      } catch (e) {
        // silent
      }
    },
  },
};
</script>

<style scoped>
/* ===== Root ===== */
.st-root {
  padding: 0.5rem;
  font-size: 0.85rem;
}

/* ===== Search bar ===== */
.st-search-bar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}
.st-school-input { width: 220px; }
.st-dow-select   { width: 100px; }
.st-date-input   { width: 150px; }
.st-label        { font-size: 0.78rem; white-space: nowrap; margin: 0; }

/* ===== Department filter ===== */
.st-dept-filter {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem 0.75rem;
  padding: 0.3rem 0.5rem;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  margin-bottom: 0.4rem;
}
.st-dept-check-label {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  cursor: pointer;
  white-space: nowrap;
  margin: 0;
}
.st-dept-checkbox {
  cursor: pointer;
}
.st-dept-all-btn {
  font-size: 0.72rem;
  padding: 1px 6px;
}

/* ===== Section title ===== */
.st-section-title {
  font-weight: 600;
  border-bottom: 2px solid #555;
  margin: 0.75rem 0 0.3rem;
  padding-bottom: 0.15rem;
  font-size: 0.88rem;
}
/* ペイン内のタイトルは上マージンを詰める */
.st-section-title--pane {
  margin-top: 0.3rem;
}

/* ===== 2ペイン分割レイアウト ===== */
.st-split {
  display: flex;
  flex-direction: column;
}

/* 上ペイン: グリッド（高さは :style でバインド） */
.st-pane-grid {
  display: flex;
  flex-direction: column;
  min-height: 120px;
  overflow: hidden;
}
/* st-grid-outer がペインの残り高さを満たす */
.st-pane-grid .st-grid-outer {
  flex: 1;
  min-height: 0;
  border-radius: 4px;
  overflow-x: auto;
  overflow-y: auto;
}

/* リサイズハンドル */
.st-resize-handle {
  flex-shrink: 0;
  height: 10px;
  background: #dee2e6;
  cursor: ns-resize;
  display: flex;
  align-items: center;
  justify-content: center;
  border-top: 1px solid #adb5bd;
  border-bottom: 1px solid #adb5bd;
  user-select: none;
}
.st-resize-grip {
  font-size: 0.6rem;
  color: #6c757d;
  letter-spacing: 1px;
  line-height: 1;
}

/* 下ペイン: Lines Editor（height は :style バインドで制御） */
.st-pane-lines {
  min-height: 150px;
  overflow-y: auto;
  padding: 0 0.1rem 0.5rem;
}

/* ===== Timetable grid ===== */

/* 外枠: 横スクロール専用コンテナ */
.st-grid-outer {
  overflow-x: auto;
  overflow-y: visible;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.st-grid-header,
.st-grid-row {
  display: flex;
  align-items: stretch;
  border-bottom: 1px solid #e0e0e0;
  min-width: max-content; /* background と border-bottom を全幅に伸ばす */
  /* overflow を設定しない（sticky が効くよう） */
}
.st-grid-header { background: #f8f9fa; }

/* 左固定ラベル列: position sticky で横スクロール時に固定 */
.st-row-label {
  flex-shrink: 0;
  width: 120px;
  padding: 4px 6px;
  border-right: 2px solid #555;
  background: #f0f0f0;
  font-size: 0.72rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 1px;
  /* sticky 固定 */
  position: sticky;
  left: 0;
  z-index: 2;
}
.st-row-label--header {
  background: #e9ecef;
  min-height: 22px;
}
.st-label-dow  { font-weight: 700; font-size: 0.82rem; }
.st-label-user { color: #333; }
.st-label-id   { color: #888; font-size: 0.68rem; }
.st-label-time { color: #555; }

/* 時刻ヘッダー: セル領域と同じ幅で固定 */
.st-time-header {
  flex-shrink: 0;
  width: 1560px; /* SLOT_COUNT(156) × SLOT_PX(10px) */
  position: relative;
  height: 22px;
}
.st-time-mark {
  position: absolute;
  transform: translateX(-50%);
  font-size: 0.68rem;
  color: #555;
  top: 3px;
  white-space: nowrap;
  /* 左端の 9:30 ラベルが切れないよう最小 left を保証 */
  min-width: max-content;
}

/* セル領域: スロット背景 + イベントブロックを内包 */
.st-cells-wrap {
  flex-shrink: 0;
  width: 1560px; /* SLOT_COUNT(156) × SLOT_PX(10px) */
  height: 52px;
  position: relative;
}

/* 背景グリッド（CSS gradient で正確に描画、flex 累積誤差なし）
   9:30 開始のため:
   ・5分線 (10px毎): x=9-10, 19-20, 29-30, ...
   ・30分線 (120px毎, offset=0): x=119-120, 239-240, ...  (10:30, 11:30, ...)
   ・60分線 (120px毎, offset=60): x=59-60, 179-180, ...   (10:00, 11:00, ...) */
.st-cells-bg {
  position: absolute;
  inset: 0;
  background-image:
    repeating-linear-gradient(to right, transparent 0, transparent 119px, #bbb 119px, #bbb 120px),
    repeating-linear-gradient(to right, transparent 0, transparent 119px, #ddd 119px, #ddd 120px),
    repeating-linear-gradient(to right, transparent 0, transparent 9px,   #f0f0f0 9px, #f0f0f0 10px);
  background-position: 60px 0, 0px 0, 0px 0;
  background-size: 120px 100%, 120px 100%, 10px 100%;
}

/* event blocks */
.st-events {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.st-event {
  position: absolute;
  top: 4px;
  bottom: 4px;
  border-radius: 3px;
  overflow: hidden;
  padding: 1px 3px;
  font-size: 0.65rem;
  line-height: 1.2;
  cursor: default;
  pointer-events: auto;
  border: 1px solid rgba(0,0,0,0.15);
  display: flex;
  flex-direction: column;
  justify-content: center;
  white-space: nowrap;
}
.st-event-code  { font-weight: 700; }
.st-event-name  { overflow: hidden; text-overflow: ellipsis; }
.st-event-time  { font-size: 0.6rem; color: rgba(0,0,0,0.6); }

.st-oor-warning {
  padding: 4px 8px;
  font-size: 0.75rem;
  color: #856404;
  background: #fff3cd;
}

/* ===== Lines Editor ===== */
.st-line-block {
  border: 1px solid #aaa;
  border-radius: 6px;
  margin-bottom: 0.5rem;
  overflow: hidden;
}

.st-line-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #343a40;
  color: #fff;
  padding: 4px 10px;
  font-size: 0.82rem;
}
.st-line-id     { font-weight: 700; }
.st-line-school { color: #adb5bd; }
.st-line-dept   { color: #6ea8fe; font-size: 0.78rem; }
.st-line-user   { color: #a8d8a8; font-size: 0.78rem; }
.st-line-meta   { color: #ced4da; font-size: 0.78rem; }

.st-line-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #fdf7f8;
  border-bottom: 1px solid #ddd;
}
.st-field-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* ===== Details area ===== */
.st-details-area {
  padding: 0.4rem 0.75rem 0.5rem;
}

.st-details-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
  font-weight: 600;
  font-size: 0.82rem;
}

.st-detail-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.35rem;
  padding: 0.3rem 0.4rem;
  border-bottom: 1px solid #eee;
  position: relative;
}
.st-detail-row:last-child { border-bottom: none; }
.st-detail-row:hover      { background: #f9f9f9; }

.st-detail-oor { background: #fff8e1; }

.st-detail-field {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.st-detail-field--code { min-width: 110px; }
.st-detail-field--name { min-width: 150px; }
.st-detail-field--actions {
  display: flex;
  align-items: flex-end;
}

.st-oor-tag {
  position: absolute;
  right: 4px;
  top: 4px;
  font-size: 0.65rem;
  background: #ffc107;
  color: #333;
  border-radius: 3px;
  padding: 0 4px;
}

/* detail_note 赤点（event block 右上に絶対配置） */
.st-event-note-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #dc3545;
  flex-shrink: 0;
}

/* detail メモ入力フィールド */
.st-detail-field--dnote { min-width: 130px; }

/* Detail ポップアップ */
.st-popup-overlay {
  position: fixed;
  inset: 0;
  z-index: 1050;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}
.st-popup {
  background: #fff;
  border-radius: 8px;
  padding: 1rem 1.25rem;
  min-width: 260px;
  max-width: 420px;
  position: relative;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
}
.st-popup-close {
  position: absolute;
  top: 8px;
  right: 10px;
  background: none;
  border: none;
  font-size: 1rem;
  cursor: pointer;
  color: #666;
  line-height: 1;
}
.st-popup-title {
  font-weight: 700;
  font-size: 0.95rem;
  margin-bottom: 0.6rem;
  padding-right: 1.5rem;
}
.st-popup-row {
  font-size: 0.82rem;
  margin-bottom: 0.2rem;
  white-space: pre-wrap;
  word-break: break-word;
}
.st-popup-row span {
  font-weight: 600;
  margin-right: 4px;
}
.st-popup-note {
  margin-top: 0.4rem;
  padding-top: 0.4rem;
  border-top: 1px solid #eee;
}

/* ===== 削除確認モーダル ===== */
.st-delete-popup {
  min-width: 320px;
  max-width: 460px;
}
.st-delete-title {
  color: #dc3545;
}
.st-delete-body {
  font-size: 0.85rem;
  color: #555;
  line-height: 1.5;
  margin-top: 0.25rem;
}

/* ===== 新規 Line 追加モーダル ===== */
.st-add-line-popup {
  min-width: 300px;
}
.st-add-line-field {
  margin-top: 0.5rem;
}

/* ===== Toast 通知 ===== */
.st-toast {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 2000;
  color: #fff;
  padding: 0.55rem 1.1rem;
  border-radius: 6px;
  font-size: 0.88rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.22);
  animation: st-toast-in 0.2s ease;
}
.st-toast--ok  { background: #198754; }
.st-toast--err { background: #dc3545; }
@keyframes st-toast-in {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
