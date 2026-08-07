<template>
  <div
    :class="[
      'skeleton',
      `skeleton--${type}`,
    ]"
    role="status"
    aria-label="Cargando..."
  >
    <div class="skeleton__shimmer"></div>
  </div>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    default: 'text',
    validator: (value) => ['text', 'line', 'circle', 'rect', 'image'].includes(value),
  },
})
</script>

<style scoped>
.skeleton {
  background-color: var(--color-skeleton);
  border-radius: var(--radius-md);
  position: relative;
  overflow: hidden;
}

.skeleton__shimmer {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent,
    var(--color-skeleton-shine),
    transparent
  );
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.skeleton--text {
  height: 1rem;
  width: 100%;
}

.skeleton--line {
  height: 1.25rem;
  width: 100%;
  margin-bottom: var(--spacing-sm);
}

.skeleton--circle {
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-full);
}

.skeleton--rect {
  height: 8rem;
  width: 100%;
  border-radius: var(--radius-lg);
}

.skeleton--image {
  height: 12rem;
  width: 100%;
  border-radius: var(--radius-lg);
}

@media (prefers-reduced-motion: reduce) {
  .skeleton__shimmer {
    animation-duration: 0.01ms;
  }
}
</style>
