<template>
  <nav
    class="category-nav"
    aria-label="Categorías del menú"
  >
    <button
      :class="[
        'category-nav__btn',
        { 'category-nav__btn--active': activeCategory === null },
      ]"
      @click="$emit('update:activeCategory', null)"
      :aria-pressed="activeCategory === null"
    >
      {{ t('menu.all', locale) }}
    </button>

    <button
      v-for="category in categories"
      :key="category.id"
      :class="[
        'category-nav__btn',
        { 'category-nav__btn--active': activeCategory === category.id },
      ]"
      @click="$emit('update:activeCategory', category.id)"
      :aria-pressed="activeCategory === category.id"
    >
      {{ getCategoryName(category) }}
    </button>
  </nav>
</template>

<script setup>
import { t } from '@/config/i18n'

defineProps({
  categories: {
    type: Array,
    required: true,
  },
  activeCategory: {
    type: [Number, String, null],
    default: null,
  },
  locale: {
    type: String,
    default: 'es',
  },
})

defineEmits(['update:activeCategory'])

function getCategoryName(category) {
  if (typeof category.name === 'object') {
    return category.name[props.locale] || category.name.en || Object.values(category.name)[0] || ''
  }
  return category.name || ''
}
</script>

<style scoped>
.category-nav {
  display: flex;
  gap: var(--spacing-sm);
  overflow-x: auto;
  padding-bottom: var(--spacing-sm);
  margin-bottom: var(--spacing-lg);
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.category-nav::-webkit-scrollbar {
  display: none;
}

.category-nav__btn {
  flex-shrink: 0;
  padding: var(--spacing-sm) var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  color: var(--color-text);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-full);
  cursor: pointer;
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
  transition: all var(--transition-fast);
  min-height: var(--touch-target-md);
}

.category-nav__btn:hover {
  background-color: var(--color-bg-tertiary);
  border-color: var(--color-border-strong);
}

.category-nav__btn--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.category-nav__btn--active:hover {
  background-color: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
  color: var(--color-text-inverse);
}
</style>
