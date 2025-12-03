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