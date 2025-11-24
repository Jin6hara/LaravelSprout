import './bootstrap';
import { createApp } from 'vue';

// Vue コンポーネントを import
import ExampleComponent from './components/ExampleComponent.vue';

// Vue アプリを作成
const app = createApp({});

// コンポーネント登録（<example-component> というタグで使えるようにする）
app.component('example-component', ExampleComponent);

// #app にマウント
app.mount('#app');
