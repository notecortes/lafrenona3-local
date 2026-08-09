<template>
  <div class="superadmin-layout">
    <RouterView />
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
</script>

<style scoped>
.superadmin-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
</style>
