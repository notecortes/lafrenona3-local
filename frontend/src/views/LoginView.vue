<template>
  <div class="login-container" role="main" aria-label="Login">
    <div class="login-card">
      <h1 class="login-title">La Frenona 3</h1>
      <p class="login-subtitle">Acceso de Personal</p>

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
          id="login-submit-btn"
          type="submit"
          class="login-button"
          :disabled="loading"
          :aria-busy="loading"
        >
          <span v-if="loading" class="spinner" aria-hidden="true"></span>
          <span>{{ loading ? 'Iniciando sesión...' : 'Entrar / Iniciar sesión' }}</span>
        </button>
      </form>

      <!-- Quick Demo Login Helpers -->
      <div class="demo-section">
        <p class="demo-title">Acceso rápido de prueba (Demo):</p>
        <div class="demo-buttons">
          <button type="button" @click="quickLogin('waiter')" class="demo-btn demo-btn--waiter">
            🤵 Camarero
          </button>
          <button type="button" @click="quickLogin('kitchen')" class="demo-btn demo-btn--kitchen">
            🍳 Cocina
          </button>
          <button type="button" @click="quickLogin('owner')" class="demo-btn demo-btn--owner">
            👑 Propietario
          </button>
          <button type="button" @click="quickLogin('superadmin')" class="demo-btn demo-btn--admin">
            ⚡ SuperAdmin
          </button>
        </div>
      </div>
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
    } else if (authStore.isOwner) {
      router.push('/owner/dashboard')
    } else {
      router.push('/staff/room')
    }
  } else {
    globalError.value = result.error || 'Credenciales incorrectas'
  }
}

async function quickLogin(role) {
  if (role === 'waiter') {
    email.value = 'waiter0@staging.lafrenona3.test'
    password.value = 'Staff123!'
  } else if (role === 'kitchen') {
    email.value = 'kitchen0@staging.lafrenona3.test'
    password.value = 'Staff123!'
  } else if (role === 'owner') {
    email.value = 'owner0@staging.lafrenona3.test'
    password.value = 'Owner123!'
  } else if (role === 'superadmin') {
    email.value = 'superadmin@staging.lafrenona3.test'
    password.value = 'SuperAdmin123!'
  }
  await handleLogin()
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-secondary, #f5f5f5);
  padding: var(--spacing-lg, 1.5rem);
}

.login-card {
  background: var(--color-bg, #ffffff);
  padding: var(--spacing-xl, 2rem);
  border-radius: var(--radius, 0.75rem);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 28rem;
}

.login-title {
  font-size: var(--font-size-2xl, 1.75rem);
  color: var(--color-accent, #d4600a);
  margin-bottom: var(--spacing-xs, 0.25rem);
  text-align: center;
  font-weight: 700;
}

.login-subtitle {
  font-size: var(--font-size-lg, 1.1rem);
  color: var(--color-text-muted, #666666);
  text-align: center;
  margin-bottom: var(--spacing-xl, 1.5rem);
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg, 1.25rem);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs, 0.25rem);
}

.form-label {
  font-size: var(--font-size-sm, 0.875rem);
  font-weight: 600;
  color: var(--color-text, #1a1a1a);
}

.form-input {
  padding: var(--spacing-sm, 0.75rem) var(--spacing-md, 1rem);
  border: 2px solid var(--color-bg-tertiary, #e0e0e0);
  border-radius: var(--radius, 0.5rem);
  font-size: var(--font-size-base, 1rem);
  background: var(--color-bg, #ffffff);
  color: var(--color-text, #1a1a1a);
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--color-focus, #0056b3);
}

.form-input.input-error {
  border-color: var(--color-error, #c62828);
}

.error-message {
  font-size: var(--font-size-xs, 0.75rem);
  color: var(--color-error, #c62828);
}

.global-error {
  background: #fde8e8;
  padding: var(--spacing-sm, 0.5rem);
  border-radius: var(--radius, 0.5rem);
  text-align: center;
  font-weight: 500;
}

.login-button {
  width: 100%;
  padding: var(--spacing-md, 0.875rem) var(--spacing-lg, 1.5rem);
  background: var(--color-accent, #d4600a);
  color: #ffffff;
  border: none;
  border-radius: var(--radius, 0.5rem);
  font-size: var(--font-size-base, 1rem);
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background-color 0.2s, opacity 0.2s;
  box-shadow: 0 2px 4px rgba(212, 96, 10, 0.3);
}

.login-button:hover:not(:disabled) {
  background: #b55008;
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner {
  width: 1.2rem;
  height: 1.2rem;
  border: 2px solid #ffffff;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.demo-section {
  margin-top: var(--spacing-xl, 1.5rem);
  padding-top: var(--spacing-md, 1rem);
  border-top: 1px dashed var(--color-bg-tertiary, #e0e0e0);
}

.demo-title {
  font-size: var(--font-size-xs, 0.8rem);
  font-weight: 600;
  color: var(--color-text-muted, #666666);
  margin-bottom: var(--spacing-sm, 0.5rem);
  text-align: center;
}

.demo-buttons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
}

.demo-btn {
  padding: 0.5rem 0.75rem;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 0.375rem;
  border: 1px solid #d0d0d0;
  background: #f9f9f9;
  color: #333;
  cursor: pointer;
  transition: all 0.15s;
}

.demo-btn:hover {
  background: #eaeaea;
  border-color: #bbbbbb;
  transform: translateY(-1px);
}
</style>
