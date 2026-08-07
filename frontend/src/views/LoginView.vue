<template>
  <div class="login-container" role="main" aria-label="Login">
    <div class="login-card">
      <h1 class="login-title">La Frenona 3</h1>
      <p class="login-subtitle">Iniciar sesión</p>

      <form @submit.prevent="handleLogin" class="login-form" novalidate>
        <div class="form-group">
          <label for="email" class="form-label">Correo electrónico</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-input"
            :class="{ 'input-error': errors.email }"
            placeholder="tu@email.com"
            autocomplete="email"
            :aria-invalid="!!errors.email"
            :aria-describedby="errors.email ? 'email-error' : undefined"
            required
          />
          <span v-if="errors.email" id="email-error" class="error-message" role="alert">{{ errors.email }}</span>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Contraseña</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="form-input"
            :class="{ 'input-error': errors.password }"
            placeholder="••••••••"
            autocomplete="current-password"
            :aria-invalid="!!errors.password"
            :aria-describedby="errors.password ? 'password-error' : undefined"
            required
          />
          <span v-if="errors.password" id="password-error" class="error-message" role="alert">{{ errors.password }}</span>
        </div>

        <div v-if="globalError" class="error-message global-error" role="alert">
          {{ globalError }}
        </div>

        <button
          type="submit"
          class="login-button"
          :disabled="loading"
          :aria-busy="loading"
        >
          {{ loading ? 'Iniciando sesión...' : 'Iniciar sesión' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const globalError = ref('')

const errors = reactive({
  email: '',
  password: ''
})

const isFormValid = computed(() => {
  return email.value && email.value.includes('@') && password.value && password.value.length >= 8
})

function validateForm() {
  errors.email = ''
  errors.password = ''
  globalError.value = ''

  if (!email.value) {
    errors.email = 'El correo electrónico es obligatorio'
    return false
  }
  if (!email.value.includes('@')) {
    errors.email = 'Introduce un correo electrónico válido'
    return false
  }
  if (!password.value) {
    errors.password = 'La contraseña es obligatoria'
    return false
  }
  if (password.value.length < 8) {
    errors.password = 'La contraseña debe tener al menos 8 caracteres'
    return false
  }
  return true
}

async function handleLogin() {
  if (!validateForm()) return

  loading.value = true
  const result = await authStore.login(email.value, password.value)
  loading.value = false

  if (result.success) {
    if (authStore.isSuperAdmin) {
      router.push('/admin/dashboard')
    } else {
      router.push('/owner/staff')
    }
  } else {
    globalError.value = result.error || 'Credenciales incorrectas'
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-secondary);
  padding: var(--spacing-lg);
}

.login-card {
  background: var(--color-bg);
  padding: var(--spacing-xl);
  border-radius: var(--radius);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 28rem;
}

.login-title {
  font-size: var(--font-size-2xl);
  color: var(--color-accent);
  margin-bottom: var(--spacing-xs);
  text-align: center;
}

.login-subtitle {
  font-size: var(--font-size-lg);
  color: var(--color-text-muted);
  text-align: center;
  margin-bottom: var(--spacing-xl);
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.form-label {
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--color-text);
}

.form-input {
  padding: var(--spacing-sm) var(--spacing-md);
  border: 2px solid var(--color-bg-tertiary);
  border-radius: var(--radius);
  font-size: var(--font-size-base);
  background: var(--color-bg);
  color: var(--color-text);
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--color-focus);
}

.form-input.input-error {
  border-color: var(--color-error);
}

.error-message {
  font-size: var(--font-size-xs);
  color: var(--color-error);
}

.global-error {
  background: #fde8e8;
  padding: var(--spacing-sm);
  border-radius: var(--radius);
  text-align: center;
}

.login-button {
  padding: var(--spacing-sm) var(--spacing-lg);
  background: var(--color-accent);
  color: white;
  border: none;
  border-radius: var(--radius);
  font-size: var(--font-size-base);
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}

.login-button:hover:not(:disabled) {
  opacity: 0.9;
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
