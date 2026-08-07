import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true, title: 'Iniciar sesi\u00f3n' }
  },
  {
    path: '/',
    redirect: '/client/menu'
  },
  {
    path: '/client',
    children: [
      {
        path: 'menu',
        name: 'ClientMenu',
        component: () => import('@/views/client/ClientMenuView.vue'),
        meta: { public: true, title: 'Men\u00fa' }
      },
      {
        path: 'menu/:token',
        name: 'ClientMenuWithToken',
        component: () => import('@/views/client/ClientMenuView.vue'),
        meta: { public: true, title: 'Men\u00fa' }
      },
      {
        path: 'order/:id',
        name: 'ClientOrder',
        component: () => import('@/views/client/ClientOrderView.vue'),
        meta: { public: true, title: 'Mi pedido' }
      },
      {
        path: 'payment/:id',
        name: 'ClientPayment',
        component: () => import('@/views/client/ClientPaymentView.vue'),
        meta: { public: true, title: 'Pago' }
      }
    ]
  },
  {
    path: '/staff',
    component: () => import('@/layouts/StaffLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/staff/room'
      },
      {
        path: 'room',
        name: 'StaffRoom',
        component: () => import('@/views/staff/StaffRoomView.vue'),
        meta: { title: 'Sala' }
      },
      {
        path: 'kitchen',
        name: 'StaffKitchen',
        component: () => import('@/views/staff/KitchenMonitorView.vue'),
        meta: { title: 'Cocina' }
      }
    ]
  },
  {
    path: '/owner',
    component: () => import('@/layouts/OwnerLayout.vue'),
    meta: { requiresAuth: true, requiresOwner: true },
    children: [
      {
        path: '',
        redirect: '/owner/dashboard'
      },
      {
        path: 'dashboard',
        name: 'OwnerDashboard',
        component: () => import('@/views/owner/OwnerDashboardView.vue'),
        meta: { title: 'Dashboard' }
      }
    ]
  },
  {
    path: '/admin',
    component: () => import('@/layouts/SuperAdminLayout.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true },
    children: [
      {
        path: '',
        redirect: '/admin/dashboard'
      },
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/superadmin/AdminDashboardView.vue'),
        meta: { title: 'Dashboard' }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  }
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  const requiresAuth = to.meta.requiresAuth
  const requiresSuperAdmin = to.meta.requiresSuperAdmin
  const requiresOwner = to.meta.requiresOwner

  if (requiresAuth && !authStore.isAuthenticated) {
    next('/login')
    return
  }

  if (requiresSuperAdmin && !authStore.isSuperAdmin) {
    next('/staff/room')
    return
  }

  if (requiresOwner && !authStore.isOwner) {
    next('/login')
    return
  }

  if (to.meta.public && authStore.isAuthenticated) {
    if (authStore.isSuperAdmin) next('/admin/dashboard')
    else if (authStore.isOwner) next('/owner/dashboard')
    else next('/staff/room')
    return
  }

  next()
})

export default router