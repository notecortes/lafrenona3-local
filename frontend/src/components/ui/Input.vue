<template>
  <div class="form-field">
    <label
      v-if="label"
      :for="id"
      class="form-label"
    >
      {{ label }}
      <span v-if="required" class="form-required" aria-hidden="true">*</span>
    </label>
    
    <div class="form-input-wrapper">
      <input
        :id="id"
        v-model="localValue"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :aria-invalid="!!error"
        :aria-describedby="errorId"
        :class="[
          'form-input',
          { 'form-input--error': error },
        ]"
        v-bind="$attrs"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      
      <button
        v-if="type === 'password' && showPassword"
        type="button"
        class="form-input__toggle"
        @click="showPassword = false"
        :aria-label="'Ocultar contraseña'"
      >
        👁️
      </button>
      
      <button
        v-if="type === 'password' && !showPassword"
        type="button"
        class="form-input__toggle"
        @click="showPassword = true"
        :aria-label="'Mostrar contraseña'"
      >
        👁️‍🗨️
      </button>
    </div>
    
    <p v-if="error" :id="errorId" class="form-error" role="alert">
      {{ error }}
    </p>
    
    <p v-if="hint && !error" class="form-hint">
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  id: {
    type: String,
    default: () => `input-${Math.random().toString(36).substr(2, 9)}`,
  },
  label: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  placeholder: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
  hint: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const localValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const showPassword = ref(false)
const errorId = computed(() => `${props.id}-error`)
</script>

<style scoped>
.form-field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
  width: 100%;
}

.form-label {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

.form-required {
  color: var(--color-error);
  margin-left: var(--spacing-xs);
}

.form-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input {
  width: 100%;
  padding: var(--spacing-sm) var(--spacing-md);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  background-color: var(--color-bg-secondary);
  color: var(--color-text);
  transition: border-color var(--transition-fast);
  min-height: var(--touch-target-md);
}

.form-input:focus {
  outline: none;
  border-color: var(--color-focus);
}

.form-input--error {
  border-color: var(--color-error);
}

.form-input::placeholder {
  color: var(--color-text-muted);
}

.form-input__toggle {
  position: absolute;
  right: var(--spacing-sm);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: var(--spacing-xs);
  font-size: var(--font-size-lg);
}

.form-error {
  font-size: var(--font-size-xs);
  color: var(--color-error);
  margin-top: var(--spacing-xs);
}

.form-hint {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}
</style>
