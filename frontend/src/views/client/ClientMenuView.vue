<template>
  <div class="client-menu">
    <!-- Header -->
    <header class="client-menu__header">
      <div class="client-menu__header-top">
        <div class="client-menu__restaurant">
          <h1 class="client-menu__name">{{ restaurantName }}</h1>
          <p v-if="tableNumber" class="client-menu__table">
            {{ t('menu.table', locale, { number: tableNumber }) }}
          </p>
        </div>

        <div class="client-menu__header-actions">
          <ConnectionStatus :locale="locale" />
          
          <button
            class="client-menu__cart-btn"
            @click="showCart = true"
            :aria-label="`${cart.itemCount} ${t('cart.items', locale)}. Total: ${formatPrice(cart.total)}€`"
          >
            <span class="client-menu__cart-icon" aria-hidden="true">🛒</span>
            <span v-if="cart.itemCount > 0" class="client-menu__cart-count">
              {{ cart.itemCount }}
            </span>
            <span class="client-menu__cart-total">{{ formatPrice(cart.total) }}€</span>
          </button>
        </div>
      </div>

      <!-- Search -->
      <div class="client-menu__search">
        <label for="search-input" class="sr-only">{{ t('app.search', locale) }}</label>
        <input
          id="search-input"
          v-model="searchQuery"
          type="search"
          :placeholder="t('app.search', locale)"
          class="client-menu__search-input"
          autocomplete="off"
        />
      </div>

      <!-- Categories -->
      <CategoryNav
        :categories="categories"
        :active-category="activeCategory"
        :locale="locale"
        @update:active-category="handleCategoryChange"
      />

      <!-- Filters -->
      <div class="client-menu__filters">
        <button
          :class="['client-menu__filter-btn', { 'client-menu__filter-btn--active': showOnlyAvailable }]"
          @click="showOnlyAvailable = !showOnlyAvailable"
          :aria-pressed="showOnlyAvailable"
        >
          {{ t('menu.available', locale) }}
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="client-menu__main">
      <!-- Loading State -->
      <div v-if="loading" class="client-menu__loading">
        <Skeleton type="rect" v-for="i in 3" :key="i" class="skeleton-card" />
      </div>

      <!-- Error State -->
      <ErrorState
        v-else-if="error"
        :title="t('app.errorLoading', locale)"
        :description="error"
        :show-retry="true"
        @retry="fetchMenu"
      />

      <!-- Empty Menu -->
      <EmptyState
        v-else-if="displayProducts.length === 0 && !loading"
        :icon="'📭'"
        :title="t('menu.emptyMenu', locale)"
        :description="searchQuery ? t('app.noResults', locale) : ''"
      />

      <!-- Products -->
      <div v-else class="client-menu__products">
        <div
          v-for="product in displayProducts"
          :key="product.id"
          class="product-card-wrapper"
        >
          <ProductCard
            :product="product"
            @add="handleAddToCart"
          />
        </div>
      </div>
    </main>

    <!-- Cart Drawer -->
    <CartDrawer
      :open="showCart"
      :locale="locale"
      :can-send="canSend"
      @update:open="showCart = $event"
      @update-quantity="handleUpdateQuantity"
      @remove="handleRemoveItem"
      @update-notes="handleUpdateNotes"
      @clear="handleClearCart"
      @send-order="handleSendOrder"
    />

    <!-- Product Detail Modal -->
    <ProductDetail
      v-if="selectedProduct"
      v-model="showProductDetail"
      :product="selectedProduct"
      :locale="locale"
      @add="handleAddFromDetail"
    />

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useClientMenuStore } from '@/stores/clientMenuStore'
import { useCartStore } from '@/stores/cartStore'
import { useConnectionStore } from '@/stores/connectionStore'
import { useCart } from '@/composables/useCart'
import { useConnection } from '@/composables/useConnection'
import { useAccessibility } from '@/composables/useAccessibility'
import ProductCard from '@/components/client/ProductCard.vue'
import ProductDetail from '@/components/client/ProductDetail.vue'
import CartDrawer from '@/components/client/CartDrawer.vue'
import CategoryNav from '@/components/client/CategoryNav.vue'
import ConnectionStatus from '@/components/ui/ConnectionStatus.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ErrorState from '@/components/ui/ErrorState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const route = useRoute()
const menuStore = useClientMenuStore()
const cartStore = useCartStore()
const connectionStore = useConnectionStore()

const { cart, canSend, sendStatus, addToCart, sendOrder, syncPending } = useCart('es')
const { connection } = useConnection()
const { announce } = useAccessibility()

const locale = ref('es')
const searchQuery = ref('')
const activeCategory = ref(null)
const showOnlyAvailable = ref(true)
const showCart = ref(false)
const showProductDetail = ref(false)
const selectedProduct = ref(null)

const restaurantName = computed(() => menuStore.restaurant?.name || 'Restaurante')
const tableNumber = computed(() => menuStore.tableNumber)
const categories = computed(() => menuStore.categories)
const products = computed(() => menuStore.products)
const loading = computed(() => menuStore.loading)
const error = computed(() => menuStore.error)

const displayProducts = computed(() => {
  let filtered = products.value

  if (activeCategory.value !== null) {
    filtered = filtered.filter((p) => p.category_id === activeCategory.value)
  }

  if (showOnlyAvailable.value) {
    filtered = filtered.filter((p) => p.is_available)
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter((p) => {
      const name = typeof p.name === 'object' ? (p.name.en || Object.values(p.name)[0] || '') : p.name
      const description = typeof p.description === 'object' ? (p.description.en || Object.values(p.description)[0] || '') : p.description
      return (
        name.toLowerCase().includes(query) ||
        description.toLowerCase().includes(query)
      )
    })
  }

  return filtered
})

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

function handleCategoryChange(categoryId) {
  activeCategory.value = categoryId
}

function handleAddToCart(product) {
  addToCart(product)
  announce(`${product.name} añadido al carrito`)
}

function handleAddFromDetail({ product, quantity, notes }) {
  for (let i = 0; i < quantity; i++) {
    addToCart(product, { notes })
  }
  announce(`${product.name} añadido al carrito`)
}

function handleUpdateQuantity(index, delta) {
  cartStore.updateQuantity(index, delta)
}

function handleRemoveItem(index) {
  cartStore.removeItem(index)
}

function handleUpdateNotes(index, notes) {
  cartStore.updateNotes(index, notes)
}

function handleClearCart() {
  if (confirm(t('cart.clearConfirm', locale.value))) {
    cartStore.clear()
  }
}

async function handleSendOrder() {
  const result = await sendOrder()
  if (result.success) {
    showCart.value = false
  }
}

async function fetchMenu() {
  const token = route.params.token || null
  await menuStore.fetchMenu(token)
}

onMounted(() => {
  fetchMenu()
})
</script>

<style scoped>
.client-menu {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.client-menu__header {
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
  padding: var(--spacing-md);
}

.client-menu__header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-md);
}

.client-menu__restaurant {
  flex: 1;
}

.client-menu__name {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.client-menu__table {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: var(--spacing-xs) 0 0;
}

.client-menu__header-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.client-menu__cart-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-sm) var(--spacing-md);
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-radius: var(--radius-full);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  position: relative;
  min-height: var(--touch-target-md);
}

.client-menu__cart-btn:hover {
  background-color: var(--color-primary-dark);
}

.client-menu__cart-count {
  position: absolute;
  top: -0.5rem;
  right: -0.5rem;
  background-color: var(--color-error);
  color: var(--color-text-inverse);
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
}

.client-menu__search {
  margin-bottom: var(--spacing-md);
}

.client-menu__search-input {
  width: 100%;
  padding: var(--spacing-sm) var(--spacing-md);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  background-color: var(--color-bg);
  color: var(--color-text);
  min-height: var(--touch-target-md);
}

.client-menu__search-input:focus {
  outline: none;
  border-color: var(--color-focus);
}

.client-menu__filters {
  display: flex;
  gap: var(--spacing-sm);
  margin-bottom: var(--spacing-md);
}

.client-menu__filter-btn {
  padding: var(--spacing-xs) var(--spacing-sm);
  background-color: var(--color-bg);
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  cursor: pointer;
  min-height: var(--touch-target-sm);
}

.client-menu__filter-btn--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.client-menu__main {
  flex: 1;
  padding: var(--spacing-md);
}

.client-menu__loading {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.skeleton-card {
  height: 16rem;
  border-radius: var(--radius-lg);
}

.client-menu__products {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--spacing-lg);
}

.product-card-wrapper {
  animation: fadeIn var(--transition-normal);
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 480px) {
  .client-menu__products {
    grid-template-columns: 1fr;
  }

  .client-menu__header {
    padding: var(--spacing-sm);
  }

  .client-menu__main {
    padding: var(--spacing-sm);
  }
}
</style>
