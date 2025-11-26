import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            // ★ これを追加：テンプレートコンパイラ付きビルドを使う
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    server: {
        host: '0.0.0.0',  // どこからでも受ける
        port: 5173,
        strictPort: true,     // ★ これ重要：勝手に 5174 に逃げない
        hmr: {
            host: 'localhost', // ブラウザから見たホスト名
            port: 5173,
            clientPort: 5173,
        },
    },
});