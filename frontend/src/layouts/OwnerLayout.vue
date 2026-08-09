<template>
  <div class="owner-layout">
    <RouterView />
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
</script>

<style scoped>
.owner-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
</style>
