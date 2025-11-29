import './bootstrap';
import './toast';
import { createApp } from 'vue';

// Vue コンポーネントを import
import ExampleComponent from './components/ExampleComponent.vue';

import AdminCreateControls from './components/AdminCreateControls.vue';

const exampleEl = document.getElementById('ExampleComponent');
if (exampleEl) {
    createApp(ExampleComponent).mount(exampleEl);
}

const el = document.getElementById('adminCreateControls');
if (el) {
    const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
    createApp(AdminCreateControls, props).mount(el);
}