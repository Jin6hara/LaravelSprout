<!-- 月単位で日付を表示しパターンの色分けを反映するミニカレンダーコンポーネント -->
<template>
  <div class="mini-cal border rounded p-1">
    <div class="mini-cal__header text-center fw-semibold small mb-1">
      {{ month.year }}/{{ String(month.month).padStart(2, '0') }}
    </div>
    <table class="mini-cal__table w-100">
      <thead>
        <tr>
          <th v-for="d in dayNames" :key="d" class="mini-cal__th text-center">{{ d }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(week, wi) in weeks" :key="wi">
          <td
            v-for="(day, di) in week"
            :key="di"
            class="mini-cal__td text-center"
            :class="cellClass(day)"
            :title="day ? dayTitle(day) : ''"
          >
            <span v-if="day">{{ day.d }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  month:  { type: Object, required: true }, // { year, month, key }
  events: { type: Array,  default: () => [] },
});

const dayNames = ['日', '月', '火', '水', '木', '金', '土'];

// その月の日付ごとのイベントマップ
const eventMap = computed(() => {
  const map = {};
  const ym = `${props.month.year}-${String(props.month.month).padStart(2, '0')}`;
  for (const e of props.events) {
    const dateStr = e.start ?? e.date;
    if (!dateStr || !dateStr.startsWith(ym)) continue;
    if (!map[dateStr]) map[dateStr] = [];
    map[dateStr].push(e);
  }
  return map;
});

// 週ごとの日付配列（空セルはnull）
const weeks = computed(() => {
  const { year, month } = props.month;
  const firstDay = new Date(year, month - 1, 1).getDay(); // 0=日
  const daysInMonth = new Date(year, month, 0).getDate();

  const cells = Array(firstDay).fill(null);
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    cells.push({ d, dateStr, dow: (firstDay + d - 1) % 7 });
  }
  while (cells.length % 7 !== 0) cells.push(null);

  const ws = [];
  for (let i = 0; i < cells.length; i += 7) ws.push(cells.slice(i, i + 7));
  return ws;
});

function eventsOn(day) {
  return day ? (eventMap.value[day.dateStr] ?? []) : [];
}

function cellClass(day) {
  if (!day) return 'mini-cal__empty';
  const evs = eventsOn(day);
  const classes = [];
  if (day.dow === 0) classes.push('mini-cal__sun');
  if (day.dow === 6) classes.push('mini-cal__sat');
  // 背景色は優先度順に1クラスのみ適用
  if (evs.some(e => e._type === 'holiday')) {
    classes.push('mini-cal__holiday');
  } else if (evs.some(e => e._type === 'adjusting')) {
    classes.push('mini-cal__adjusting');
  } else if (evs.some(e => e._type === 'closure')) {
    classes.push('mini-cal__closure');
  } else if (evs.some(e => e._type === 'offday' && e.kind === 'statutory_off')) {
    classes.push('mini-cal__offday-lrd');
  } else if (evs.some(e => e._type === 'offday' && e.kind === 'prescribed_off')) {
    classes.push('mini-cal__offday-ord');
  }
  return classes;
}

function dayTitle(day) {
  return eventsOn(day).map(e => e.title).filter(Boolean).join(', ');
}
</script>

<style scoped>
.mini-cal { font-size: 0.7rem; }
.mini-cal__header { font-size: 0.75rem; }
.mini-cal__th { font-size: 0.65rem; color: #fff; background: #6c757d; padding: 1px; }
.mini-cal__th:first-child { background: #c0392b; }
.mini-cal__th:last-child  { background: #2471a3; }
.mini-cal__td { padding: 1px 0; width: 14.28%; line-height: 1.4; border-radius: 2px; }
.mini-cal__empty { }
.mini-cal__sun { color: #c0392b; }
.mini-cal__sat { color: #2471a3; }
.mini-cal__holiday  { background: #ffe6e6; font-weight: bold; }
.mini-cal__closure  { background: #d5e8d4; }
.mini-cal__offday-lrd { background: #f1f3f8; }
.mini-cal__offday-ord { background: #d7dbea; font-weight: 600; }
.mini-cal__adjusting { background: #fff3cd; }
</style>
