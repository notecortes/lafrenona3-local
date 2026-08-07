<template>
  <div class="superadmin-dashboard">
    <header class="superadmin-dashboard__header">
      <h1 class="superadmin-dashboard__title">{{ t('superadmin.title', locale) }}</h1>
      <div class="superadmin-dashboard__header-actions">
        <ConnectionStatus :locale="locale" />
        <button
          class="superadmin-dashboard__logout-btn"
          @click="handleLogout"
          :aria-label="t('app.logout', locale)"
        >
          {{ t('app.logout', locale) }}
        </button>
      </div>
    </header>

    <nav class="superadmin-dashboard__nav" aria-label="Navegación principal">
      <button
        v-for="section in sections"
        :key="section.key"
        :class="['superadmin-dashboard__nav-item', { 'superadmin-dashboard__nav-item--active': activeSection === section.key }]"
        @click="activeSection = section.key"
        :aria-current="activeSection === section.key ? 'page' : undefined"
      >
        <span class="superadmin-dashboard__nav-icon" aria-hidden="true">{{ section.icon }}</span>
        <span>{{ section.label }}</span>
      </button>
    </nav>

    <main class="superadmin-dashboard__main">
      <!-- Dashboard Overview -->
      <div v-if="activeSection === 'dashboard'" class="superadmin-dashboard__content">
        <div class="superadmin-dashboard__stats">
          <div class="superadmin-dashboard__stat-card">
            <h3 class="superadmin-dashboard__stat-label">{{ t('superadmin.stats.restaurants', locale) }}</h3>
            <p class="superadmin-dashboard__stat-value">{{ stats.restaurants || 0 }}</p>
          </div>
          <div class="superadmin-dashboard__stat-card">
            <h3 class="superadmin-dashboard__stat-label">{{ t('superadmin.stats.users', locale) }}</h3>
            <p class="superadmin-dashboard__stat-value">{{ stats.users || 0 }}</p>
          </div>
          <div class="superadmin-dashboard__stat-card">
            <h3 class="superadmin-dashboard__stat-label">{{ t('superadmin.stats.active', locale) }}</h3>
            <p class="superadmin-dashboard__stat-value">{{ stats.active || 0 }}</p>
          </div>
          <div class="superadmin-dashboard__stat-card">
            <h3 class="superadmin-dashboard__stat-label">{{ t('superadmin.stats.suspended', locale) }}</h3>
            <p class="superadmin-dashboard__stat-value">{{ stats.suspended || 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Restaurants CRUD -->
      <div v-else-if="activeSection === 'restaurants'" class="superadmin-dashboard__content">
        <div class="superadmin-dashboard__crud-header">
          <h2 class="superadmin-dashboard__section-title">{{ t('superadmin.restaurants', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showRestaurantModal = true">
            + {{ t('superadmin.add', locale) }}
          </Button>
        </div>
        <div v-if="restaurants.length === 0" class="superadmin-dashboard__empty">
          <EmptyState
            :icon="'🏪'"
            :title="t('superadmin.noRestaurants', locale)"
            :description="t('superadmin.noRestaurantsDesc', locale)"
          />
        </div>
        <div v-else class="superadmin-dashboard__list">
          <div
            v-for="restaurant in restaurants"
            :key="restaurant.id"
            class="superadmin-dashboard__list-item"
          >
            <div class="superadmin-dashboard__restaurant-info">
              <span class="superadmin-dashboard__restaurant-name">{{ restaurant.name }}</span>
              <span class="superadmin-dashboard__restaurant-plan">{{ restaurant.plan }}</span>
            </div>
            <StatusBadge :variant="restaurantStatusBadge(restaurant)">
              {{ restaurantStatusText(restaurant) }}
            </StatusBadge>
            <div class="superadmin-dashboard__list-actions">
              <Button
                v-if="!restaurant.suspended"
                variant="ghost"
                size="sm"
                @click="suspendRestaurant(restaurant.id)"
              >
                {{ t('superadmin.suspend', locale) }}
              </Button>
              <Button
                v-else
                variant="ghost"
                size="sm"
                @click="activateRestaurant(restaurant.id)"
              >
                {{ t('superadmin.activate', locale) }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Users CRUD -->
      <div v-else-if="activeSection === 'users'" class="superadmin-dashboard__content">
        <div class="superadmin-dashboard__crud-header">
          <h2 class="superadmin-dashboard__section-title">{{ t('superadmin.users', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showUserModal = true">
            + {{ t('superadmin.add', locale) }}
          </Button>
        </div>
        <div v-if="users.length === 0" class="superadmin-dashboard__empty">
          <EmptyState
            :icon="'👤'"
            :title="t('superadmin.noUsers', locale)"
            :description="t('superadmin.noUsersDesc', locale)"
          />
        </div>
        <div v-else class="superadmin-dashboard__list">
          <div
            v-for="user in users"
            :key="user.id"
            class="superadmin-dashboard__list-item"
          >
            <span class="superadmin-dashboard__list-name">{{ user.email }}</span>
            <StatusBadge variant="info">{{ user.role }}</StatusBadge>
            <div class="superadmin-dashboard__list-actions">
              <Button
                v-if="!user.suspended"
                variant="ghost"
                size="sm"
                @click="suspendUser(user.id)"
              >
                {{ t('superadmin.suspend', locale) }}
              </Button>
              <Button
                v-else
                variant="ghost"
                size="sm"
                @click="activateUser(user.id)"
              >
                {{ t('superadmin.activate', locale) }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'
import ConnectionStatus from '@/components/ui/ConnectionStatus.vue'
import Button from '@/components/ui/Button.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const router = useRouter()
const authStore = useAuthStore()
const locale = ref('es')
const activeSection = ref('dashboard')

const restaurants = ref([])
const users = ref([])
const stats = ref({})
const showRestaurantModal = ref(false)
const showUserModal = ref(false)

const sections = [
  { key: 'dashboard', label: 'Dashboard', icon: '📊' },
  { key: 'restaurants', label: 'Restaurantes', icon: '🏪' },
  { key: 'users', label: 'Usuarios', icon: '👤' },
]

async function fetchDashboard() {
  try {
    const [restRes, usersRes] = await Promise.all([
      api.get('/v1/superadmin/restaurants'),
      api.get('/v1/superadmin/users'),
    ])

    restaurants.value = restRes.data.data || []
    users.value = usersRes.data.data || []

    stats.value = {
      restaurants: restaurants.value.length,
      users: users.value.length,
      active: restaurants.value.filter((r) => !r.suspended).length,
      suspended: restaurants.value.filter((r) => r.suspended).length,
    }
  } catch (err) {
    console.error('Failed to fetch dashboard:', err)
  }
}

function restaurantStatusText(restaurant) {
  return restaurant.suspended ? t('superadmin.suspended', locale.value) : t('superadmin.active', locale.value)
}

function restaurantStatusBadge(restaurant) {
  return restaurant.suspended ? 'error' : 'success'
}

async function suspendRestaurant(id) {
  if (!confirm(t('superadmin.confirmSuspend', locale.value))) return
  try {
    await api.put(`/v1/superadmin/restaurants/${id}/suspend`)
    const restaurant = restaurants.value.find((r) => r.id === id)
    if (restaurant) restaurant.suspended = true
  } catch (err) {
    console.error('Failed to suspend restaurant:', err)
  }
}

async function activateRestaurant(id) {
  try {
    await api.put(`/v1/superadmin/restaurants/${id}/activate`)
    const restaurant = restaurants.value.find((r) => r.id === id)
    if (restaurant) restaurant.suspended = false
  } catch (err) {
    console.error('Failed to activate restaurant:', err)
  }
}

async function suspendUser(id) {
  if (!confirm(t('superadmin.confirmSuspend', locale.value))) return
  try {
    await api.put(`/v1/superadmin/users/${id}/suspend`)
    const user = users.value.find((u) => u.id === id)
    if (user) user.suspended = true
  } catch (err) {
    console.error('Failed to suspend user:', err)
  }
}

async function activateUser(id) {
  try {
    await api.put(`/v1/superadmin/users/${id}/activate`)
    const user = users.value.find((u) => u.id === id)
    if (user) user.suspended = false
  } catch (err) {
    console.error('Failed to activate user:', err)
  }
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  fetchDashboard()
})
</script>

<style scoped>
.superadmin-dashboard {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.superadmin-dashboard__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.superadmin-dashboard__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.superadmin-dashboard__header-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.superadmin-dashboard__logout-btn {
  padding: var(--spacing-sm) var(--spacing-md);
  background-color: var(--color-error);
  color: var(--color-text-inverse);
  border-radius: var(--radius-md);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  min-height: var(--touch-target-md);
}

.superadmin-dashboard__nav {
  display: flex;
  gap: var(--spacing-xs);
  padding: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
  scrollbar-width: none;
}

.superadmin-dashboard__nav::-webkit-scrollbar {
  display: none;
}

.superadmin-dashboard__nav-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-sm) var(--spacing-md);
  background-color: var(--color-bg);
  color: var(--color-text-muted);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  cursor: pointer;
  white-space: nowrap;
  min-height: var(--touch-target-md);
}

.superadmin-dashboard__nav-item:hover {
  background-color: var(--color-bg-tertiary);
}

.superadmin-dashboard__nav-item--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.superadmin-dashboard__nav-icon {
  font-size: var(--font-size-lg);
}

.superadmin-dashboard__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.superadmin-dashboard__content {
  max-width: 96rem;
  margin: 0 auto;
}

.superadmin-dashboard__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-lg);
  margin-bottom: var(--spacing-xl);
}

.superadmin-dashboard__stat-card {
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
  text-align: center;
}

.superadmin-dashboard__stat-label {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: 0 0 var(--spacing-sm);
}

.superadmin-dashboard__stat-value {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-primary);
  margin: 0;
}

.superadmin-dashboard__section-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0 0 var(--spacing-lg);
}

.superadmin-dashboard__crud-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-lg);
}

.superadmin-dashboard__list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.superadmin-dashboard__list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.superadmin-dashboard__restaurant-info {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
  flex: 1;
}

.superadmin-dashboard__restaurant-name {
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

.superadmin-dashboard__restaurant-plan {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}

.superadmin-dashboard__list-name {
  flex: 1;
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.superadmin-dashboard__list-actions {
  display: flex;
  gap: var(--spacing-sm);
}

.superadmin-dashboard__empty {
  padding: var(--spacing-3xl);
}

@media (max-width: 480px) {
  .superadmin-dashboard__header {
    padding: var(--spacing-md);
  }

  .superadmin-dashboard__main {
    padding: var(--spacing-md);
  }

  .superadmin-dashboard__stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
