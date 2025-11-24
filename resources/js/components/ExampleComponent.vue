<template>
  <div class="alert alert-info">
    <div class="fw-bold mb-1">Hello from Vue.js 🎉</div>
    <div>現在のカウント: <strong>{{ count }}</strong></div>
    <button class="btn btn-sm btn-primary mt-2" @click="increment">
      +1 する
    </button>
  </div>
</template>

<script>
export default {
  name: 'ExampleComponent',
  data() {
    return {
      count: 0,
    };
  },
  methods: {
    increment() {
      this.count++;
    },
  },
};
</script>

<style scoped>
/* 見た目調整したければここに書く */
</style>
