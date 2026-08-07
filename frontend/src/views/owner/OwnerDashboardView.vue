<template>
  <div class="owner-dashboard">
    <header class="owner-dashboard__header">
      <h1 class="owner-dashboard__title">{{ t('owner.title', locale) }}</h1>
      <div class="owner-dashboard__header-actions">
        <ConnectionStatus :locale="locale" />
        <button
          class="owner-dashboard__logout-btn"
          @click="handleLogout"
          :aria-label="t('app.logout', locale)"
        >
          {{ t('app.logout', locale) }}
        </button>
      </div>
    </header>

    <nav class="owner-dashboard__nav" aria-label="Navegación principal">
      <button
        v-for="section in sections"
        :key="section.key"
        :class="['owner-dashboard__nav-item', { 'owner-dashboard__nav-item--active': activeSection === section.key }]"
        @click="activeSection = section.key"
        :aria-current="activeSection === section.key ? 'page' : undefined"
      >
        <span class="owner-dashboard__nav-icon" aria-hidden="true">{{ section.icon }}</span>
        <span>{{ section.label }}</span>
      </button>
    </nav>

    <main class="owner-dashboard__main">
      <!-- Dashboard Overview -->
      <div v-if="activeSection === 'dashboard'" class="owner-dashboard__content">
        <div class="owner-dashboard__stats">
          <div class="owner-dashboard__stat-card">
            <h3 class="owner-dashboard__stat-label">{{ t('owner.stats.orders', locale) }}</h3>
            <p class="owner-dashboard__stat-value">{{ stats.orders || 0 }}</p>
          </div>
          <div class="owner-dashboard__stat-card">
            <h3 class="owner-dashboard__stat-label">{{ t('owner.stats.revenue', locale) }}</h3>
            <p class="owner-dashboard__stat-value">{{ stats.revenue || '0.00' }}€</p>
          </div>
          <div class="owner-dashboard__stat-card">
            <h3 class="owner-dashboard__stat-label">{{ t('owner.stats.tables', locale) }}</h3>
            <p class="owner-dashboard__stat-value">{{ stats.tables || 0 }}</p>
          </div>
          <div class="owner-dashboard__stat-card">
            <h3 class="owner-dashboard__stat-label">{{ t('owner.stats.staff', locale) }}</h3>
            <p class="owner-dashboard__stat-value">{{ stats.staff || 0 }}</p>
          </div>
        </div>

        <div class="owner-dashboard__quick-actions">
          <h2 class="owner-dashboard__section-title">{{ t('owner.quickActions', locale) }}</h2>
          <div class="owner-dashboard__action-grid">
            <button
              v-for="action in quickActions"
              :key="action.key"
              class="owner-dashboard__action-btn"
              @click="activeSection = action.key"
            >
              <span class="owner-dashboard__action-icon" aria-hidden="true">{{ action.icon }}</span>
              <span>{{ action.label }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Categories CRUD -->
      <div v-else-if="activeSection === 'categories'" class="owner-dashboard__content">
        <div class="owner-dashboard__crud-header">
          <h2 class="owner-dashboard__section-title">{{ t('owner.categories', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showCategoryModal = true">
            + {{ t('owner.add', locale) }}
          </Button>
        </div>
        <div v-if="categories.length === 0" class="owner-dashboard__empty">
          <EmptyState
            :icon="'📂'"
            :title="t('owner.noCategories', locale)"
            :description="t('owner.noCategoriesDesc', locale)"
          />
        </div>
        <div v-else class="owner-dashboard__list">
          <div
            v-for="category in categories"
            :key="category.id"
            class="owner-dashboard__list-item"
          >
            <span class="owner-dashboard__list-name">{{ getCategoryName(category) }}</span>
            <div class="owner-dashboard__list-actions">
              <Button variant="ghost" size="sm" @click="editCategory(category)">
                {{ t('app.edit', locale) }}
              </Button>
              <Button variant="ghost" size="sm" @click="deleteCategory(category.id)">
                {{ t('app.delete', locale) }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Products CRUD -->
      <div v-else-if="activeSection === 'products'" class="owner-dashboard__content">
        <div class="owner-dashboard__crud-header">
          <h2 class="owner-dashboard__section-title">{{ t('owner.products', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showProductModal = true">
            + {{ t('owner.add', locale) }}
          </Button>
        </div>
        <div v-if="products.length === 0" class="owner-dashboard__empty">
          <EmptyState
            :icon="'🍽️'"
            :title="t('owner.noProducts', locale)"
            :description="t('owner.noProductsDesc', locale)"
          />
        </div>
        <div v-else class="owner-dashboard__list">
          <div
            v-for="product in products"
            :key="product.id"
            class="owner-dashboard__list-item"
          >
            <span class="owner-dashboard__list-name">{{ getProductName(product) }}</span>
            <span class="owner-dashboard__list-price">{{ formatPrice(product.price) }}€</span>
            <div class="owner-dashboard__list-actions">
              <Button variant="ghost" size="sm" @click="editProduct(product)">
                {{ t('app.edit', locale) }}
              </Button>
              <Button variant="ghost" size="sm" @click="deleteProduct(product.id)">
                {{ t('app.delete', locale) }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tables CRUD -->
      <div v-else-if="activeSection === 'tables'" class="owner-dashboard__content">
        <div class="owner-dashboard__crud-header">
          <h2 class="owner-dashboard__section-title">{{ t('owner.tables', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showTableModal = true">
            + {{ t('owner.add', locale) }}
          </Button>
        </div>
        <div v-if="tables.length === 0" class="owner-dashboard__empty">
          <EmptyState
            :icon="'🪑'"
            :title="t('owner.noTables', locale)"
            :description="t('owner.noTablesDesc', locale)"
          />
        </div>
        <div v-else class="owner-dashboard__list">
          <div
            v-for="table in tables"
            :key="table.id"
            class="owner-dashboard__list-item"
          >
            <span class="owner-dashboard__list-name">{{ t('owner.tableNumber', locale, { number: table.number }) }}</span>
            <StatusBadge :variant="tableStatusBadge(table)">{{ tableStatusText(table) }}</StatusBadge>
            <div class="owner-dashboard__list-actions">
              <Button variant="ghost" size="sm" @click="editTable(table)">
                {{ t('app.edit', locale) }}
              </Button>
              <Button variant="ghost" size="sm" @click="deleteTable(table.id)">
                {{ t('app.delete', locale) }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Staff CRUD -->
      <div v-else-if="activeSection === 'staff'" class="owner-dashboard__content">
        <div class="owner-dashboard__crud-header">
          <h2 class="owner-dashboard__section-title">{{ t('owner.staff', locale) }}</h2>
          <Button variant="primary" size="sm" @click="showStaffModal = true">
            + {{ t('owner.add', locale) }}
          </Button>
        </div>
        <div v-if="staff.length === 0" class="owner-dashboard__empty">
          <EmptyState
            :icon="'👥'"
            :title="t('owner.noStaff', locale)"
            :description="t('owner.noStaffDesc', locale)"
          />
        </div>
        <div v-else class="owner-dashboard__list">
          <div
            v-for="member in staff"
            :key="member.id"
            class="owner-dashboard__list-item"
          >
            <span class="owner-dashboard__list-name">{{ member.email }}</span>
            <StatusBadge variant="info">{{ member.role }}</StatusBadge>
            <div class="owner-dashboard__list-actions">
              <Button variant="ghost" size="sm" @click="editStaff(member)">
                {{ t('app.edit', locale) }}
              </Button>
              <Button variant="ghost" size="sm" @click="deleteStaff(member.id)">
                {{ t('app.delete', locale) }}
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

const categories = ref([])
const products = ref([])
const tables = ref([])
const staff = ref([])
const stats = ref({})

const showCategoryModal = ref(false)
const showProductModal = ref(false)
const showTableModal = ref(false)
const showStaffModal = ref(false)

const sections = [
  { key: 'dashboard', label: 'Dashboard', icon: '📊' },
  { key: 'categories', label: 'Categorías', icon: '📂' },
  { key: 'products', label: 'Productos', icon: '🍽️' },
  { key: 'tables', label: 'Mesas', icon: '🪑' },
  { key: 'staff', label: 'Personal', icon: '👥' },
]

const quickActions = [
  { key: 'categories', label: 'Gestionar categorías', icon: '📂' },
  { key: 'products', label: 'Gestionar productos', icon: '🍽️' },
  { key: 'tables', label: 'Gestionar mesas', icon: '🪑' },
  { key: 'staff', label: 'Gestionar personal', icon: '👥' },
]

async function fetchDashboard() {
  try {
    const [catRes, prodRes, tableRes, staffRes] = await Promise.all([
      api.get('/v1/owner/categories'),
      api.get('/v1/owner/products'),
      api.get('/v1/owner/tables'),
      api.get('/v1/owner/staff'),
    ])

    categories.value = catRes.data.data || []
    products.value = prodRes.data.data || []
    tables.value = tableRes.data.data || []
    staff.value = staffRes.data.data || []

    stats.value = {
      orders: 0,
      revenue: '0.00',
      tables: tables.value.length,
      staff: staff.value.length,
    }
  } catch (err) {
    console.error('Failed to fetch dashboard:', err)
  }
}

function getCategoryName(category) {
  const name = category.name || ''
  if (typeof name === 'object') {
    return name[locale.value] || name.en || Object.values(name)[0] || ''
  }
  return name
}

function getProductName(product) {
  const name = product.name || ''
  if (typeof name === 'object') {
    return name[locale.value] || name.en || Object.values(name)[0] || ''
  }
  return name
}

function tableStatusText(table) {
  if (table.assistance_status === 'waiter_called') return t('room.waiter', locale.value)
  if (table.assistance_status === 'bill_requested') return t('room.bill', locale.value)
  return table.status === 'occupied' ? t('room.occupied', locale.value) : t('room.free', locale.value)
}

function tableStatusBadge(table) {
  if (table.assistance_status) return 'warning'
  return table.status === 'occupied' ? 'success' : 'info'
}

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

function editCategory(category) {
  console.log('Edit category:', category)
}

function deleteCategory(id) {
  if (confirm(t('app.confirm', locale.value))) {
    console.log('Delete category:', id)
  }
}

function editProduct(product) {
  console.log('Edit product:', product)
}

function deleteProduct(id) {
  if (confirm(t('app.confirm', locale.value))) {
    console.log('Delete product:', id)
  }
}

function editTable(table) {
  console.log('Edit table:', table)
}

function deleteTable(id) {
  if (confirm(t('app.confirm', locale.value))) {
    console.log('Delete table:', id)
  }
}

function editStaff(member) {
  console.log('Edit staff:', member)
}

function deleteStaff(id) {
  if (confirm(t('app.confirm', locale.value))) {
    console.log('Delete staff:', id)
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
.owner-dashboard {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.owner-dashboard__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.owner-dashboard__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.owner-dashboard__header-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.owner-dashboard__logout-btn {
  padding: var(--spacing-sm) var(--spacing-md);
  background-color: var(--color-error);
  color: var(--color-text-inverse);
  border-radius: var(--radius-md);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  min-height: var(--touch-target-md);
}

.owner-dashboard__nav {
  display: flex;
  gap: var(--spacing-xs);
  padding: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
  scrollbar-width: none;
}

.owner-dashboard__nav::-webkit-scrollbar {
  display: none;
}

.owner-dashboard__nav-item {
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

.owner-dashboard__nav-item:hover {
  background-color: var(--color-bg-tertiary);
}

.owner-dashboard__nav-item--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.owner-dashboard__nav-icon {
  font-size: var(--font-size-lg);
}

.owner-dashboard__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.owner-dashboard__content {
  max-width: 96rem;
  margin: 0 auto;
}

.owner-dashboard__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-lg);
  margin-bottom: var(--spacing-xl);
}

.owner-dashboard__stat-card {
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
  text-align: center;
}

.owner-dashboard__stat-label {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: 0 0 var(--spacing-sm);
}

.owner-dashboard__stat-value {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-primary);
  margin: 0;
}

.owner-dashboard__section-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0 0 var(--spacing-lg);
}

.owner-dashboard__crud-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-lg);
}

.owner-dashboard__action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-lg);
}

.owner-dashboard__action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-xl);
  background-color: var(--color-bg-secondary);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  cursor: pointer;
  min-height: var(--touch-target-lg);
}

.owner-dashboard__action-btn:hover {
  border-color: var(--color-primary);
  background-color: var(--color-primary-light);
}

.owner-dashboard__action-icon {
  font-size: 2.5rem;
}

.owner-dashboard__list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.owner-dashboard__list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.owner-dashboard__list-name {
  flex: 1;
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.owner-dashboard__list-price {
  font-weight: var(--font-weight-semibold);
  color: var(--color-success);
  margin: 0 var(--spacing-md);
}

.owner-dashboard__list-actions {
  display: flex;
  gap: var(--spacing-sm);
}

.owner-dashboard__empty {
  padding: var(--spacing-3xl);
}

@media (max-width: 480px) {
  .owner-dashboard__header {
    padding: var(--spacing-md);
  }

  .owner-dashboard__main {
    padding: var(--spacing-md);
  }

  .owner-dashboard__stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
