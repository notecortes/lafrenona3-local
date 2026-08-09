<template>
  <div class="staff-layout">
    <RouterView />
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
</script>

<style scoped>
.staff-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
</style>
