import './bootstrap';
import './toast';
import { createApp } from 'vue';

// Vue コンポーネントを import
import ExampleComponent from './components/ExampleComponent.vue';

import AdminCreateControls from './components/AdminCreateControls.vue';

import ReplyComposer from './components/ReplyComposer.vue';

const exampleEl = document.getElementById('ExampleComponent');
if (exampleEl) {
    createApp(ExampleComponent).mount(exampleEl);
}

const el = document.getElementById('adminCreateControls');
if (el) {
    const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
    createApp(AdminCreateControls, props).mount(el);
}

const rc = document.getElementById('replyComposer');
if (rc) {
    const props = rc.dataset.props ? JSON.parse(rc.dataset.props) : {};
    createApp(ReplyComposer, props).mount(rc);
}

import InboxBadge from './components/InboxBadge.vue';

const inboxEl = document.getElementById('inboxBadge');
if (inboxEl) {
    const props = inboxEl.dataset.props ? JSON.parse(inboxEl.dataset.props) : {};
    createApp(InboxBadge, props).mount(inboxEl);
}

import SchoolTimetable from './components/SchoolTimetable.vue';

const stEl = document.getElementById('schoolTimetable');
if (stEl) {
    const props = stEl.dataset.props ? JSON.parse(stEl.dataset.props) : {};
    createApp(SchoolTimetable, props).mount(stEl);
}

import PdfPreview from './components/PdfPreview.vue';

const pdfEl = document.getElementById('pdfPreview');
if (pdfEl) {
    createApp(PdfPreview, {
        type:        pdfEl.dataset.type,
        queryString: pdfEl.dataset.query,
    }).mount(pdfEl);
}

import CalendarPatternEditor from './components/CalendarPatternEditor.vue';

const cpeEl = document.getElementById('calendarPatternEditor');
if (cpeEl) {
    const props = cpeEl.dataset.props ? JSON.parse(cpeEl.dataset.props) : {};
    createApp(CalendarPatternEditor, props).mount(cpeEl);
}

import CalendarPatternEditorPage from './components/CalendarPatternEditorPage.vue';

const cpepEl = document.getElementById('calendarPatternEditorPage');
if (cpepEl) {
    const props = cpepEl.dataset.props ? JSON.parse(cpepEl.dataset.props) : {};
    createApp(CalendarPatternEditorPage, props).mount(cpepEl);
}